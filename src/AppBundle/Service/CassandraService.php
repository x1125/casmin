<?php

namespace AppBundle\Service;

use Cassandra;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CassandraService {

    private $container, $cluster, $clusterConfig, $session, $config;

    const keyspaceClasses = array('SimpleStrategy', 'NetworkTopologyStrategy');

    const cassandraVersions = array(
        '2.2',
        '3.3'
    );

    public static function clusterConfig($cluster)
    {
        $clusterConfig = explode(':', $cluster);

        if (count($clusterConfig) !== 3)
            throw new \Exception('Invalid amount of cluster parameters');

        return array(
            'host' => $clusterConfig[0],
            'port' => $clusterConfig[1],
            'version' => $clusterConfig[2]
        );
    }

    public function __construct(ContainerInterface $container, $clusterConfig = array())
    {
        $this->container = $container;
        $this->clusterConfig = $clusterConfig;

        $this->cluster = Cassandra::cluster()
            ->withContactPoints($clusterConfig['host'])
            ->withPort(intval($clusterConfig['port']))
            ->build();

        $this->session = $this->cluster->connect();
        $this->version = $clusterConfig['version'];

        if (!in_array($this->version, self::cassandraVersions))
            throw new \Exception('Unknown cassandra version (' . $this->version . ')');

        $this->config = array(
            'ColumnFamily' => $container->get('config_service')->getConfiguration('ColumnFamily'),
            'ColumnFamilyCompactionSubOptions' => $container->get('config_service')->getConfiguration('ColumnFamilyCompactionSubOptions'),
            'ColumnFamilyCompressionSubOptions' => $container->get('config_service')->getConfiguration('ColumnFamilyCompressionSubOptions'),
        );
    }

    public static function getCqlshBinary()
    {
        $buf = shell_exec('whereis cqlsh');
        $parts = explode(' ', $buf);
        if (count($parts) < 2)
            return false;

        return $parts[1];
    }

    public function getKeyspaces($name = null)
    {
        $arr = array();
        $res = $this->session->execute(new Cassandra\SimpleStatement('SELECT * FROM system.schema_keyspaces'));
        foreach ($res as $row)
        {
            if ($name)
                $arr[] = $row[$name];
            else
                $arr[] = $row;
        }

        return $arr;
    }

    public function addKeyspaceQuery($keyspaceConfig = array())
    {
        if (in_array($keyspaceConfig['name'], $this->getKeyspaces('keyspace_name')))
            throw new \Exception('Keyspace already exists');

        if (strlen($keyspaceConfig['name']) < 3)
            throw new \Exception('Keyspace name is too short');

        if (!in_array($keyspaceConfig['class'], self::keyspaceClasses))
            throw new \Exception('Invalid keyspace class ("' . $keyspaceConfig['class'] . '")');

        if ($keyspaceConfig['class'] == 'SimpleStrategy')
            $replication = '\'replication_factor\' : ' . $keyspaceConfig['replication_factor'];
        else
        {
            $replication = '';
            for ($i=0; $i<count($keyspaceConfig['replication']); $i++)
            {
                $replication .= '\'' . $keyspaceConfig['replication'][$i]['datacenter'] . '\' : ' . $keyspaceConfig['replication'][$i]['factor'];
                if ($i < count($keyspaceConfig['replication'])-1)
                    $replication .= ', ';
            }
        }

        $query = sprintf(
            'CREATE KEYSPACE IF NOT EXISTS %s WITH REPLICATION = { \'class\' : \'%s\', %s } AND DURABLE_WRITES = %s;',
            $keyspaceConfig['name'],
            $keyspaceConfig['class'],
            $replication,
            @$keyspaceConfig['durable_writes'] ? 'true' : 'false'
        );

        return $query;
    }

    public function removeKeyspaceQuery($keyspace)
    {
        return 'DROP KEYSPACE ' . $keyspace;
    }

    public function removeColumnFamilyQuery($keyspace, $columnFamily)
    {
        return sprintf('DROP TABLE %s.%s', $keyspace, $columnFamily);
    }

    public function removeColumnQuery($keyspace, $columnFamily, $column)
    {
        return sprintf('ALTER TABLE %s.%s DROP %s', $keyspace, $columnFamily, $column);
    }

    private function parseFieldsFromRequestConfig($config = array(), &$fields, &$pkeys, &$skeys)
    {
        $fields = array();
        $pkeys = array();
        $skeys = array();
        foreach ($config['field'] as $index => $fieldName)
        {
            if (strlen($fieldName) < 1)
                throw new \Exception('Empty field name given (Row #' . (count($fields) + 1) . ')');

            if (in_array($fieldName, $fields))
                throw new \Exception('Duplicate field name ("' . $fieldName . '")');

            $fields[$fieldName] = $this->parseFieldTypeFromArray($config['type'][$index]);

            if ($config['prefix'][$index] == 'primary')
                $pkeys[] = $fieldName;
            elseif ($config['prefix'][$index] == 'secondary')
                $skeys[] = $fieldName;
        }
    }

    public function addColumnFamilyQuery($familyColumnConfig = array())
    {
        $keyspace = $familyColumnConfig['keyspace'];

        // check if the column family already exists
        if (in_array($familyColumnConfig['name'], $this->getColumnFamilies($keyspace, 'columnfamily_name')))
            throw new \Exception('ColumnFamily already exists');

        // check for valid name
        if (strlen($familyColumnConfig['name']) < 3)
            throw new \Exception('ColumnFamily name is too short');

        // require at least one field
        if (count(@$familyColumnConfig['prefix']) < 1)
            throw new \Exception('No fields given');

        // check for empty or duplicate field names
        $fields = array();
        $pkeys = array();
        $skeys = array();

        // parse fields
        $this->parseFieldsFromRequestConfig($familyColumnConfig, $fields, $pkeys, $skeys);

        // check for primary key
        if (count($pkeys) < 1)
            throw new \Exception('You need at least one primary key');

        $columnDefinitions = array();

        // build column definitions
        foreach ($fields as $fieldName => $fieldType)
            $columnDefinitions[] = $fieldName . ' ' . $fieldType;

        // build index information
        $pkeyInfo = count($pkeys) > 1 ? ('(' . implode(',', $pkeys) . ')') : $pkeys[0];
        $skeyInfo = count($skeys) > 0 ? ($pkeyInfo . ', ' . implode(',', $skeys)) : $pkeyInfo;
        $columnDefinitions[] = sprintf('PRIMARY KEY (%s)', $skeyInfo);


        // parse generic properties
        $properties = array();
        $skipProperties = array('name', 'caching_keys', 'caching_rows_per_partition', 'compaction', 'compression', 'compact_storage');
        foreach ($this->config['ColumnFamily']['ColumnFamily'] as $fieldName => $field)
        {
            // skip some properties; we'll care about this later
            if (in_array($fieldName, $skipProperties))
                continue;

            // the value
            $value = @$familyColumnConfig[$fieldName];

            // special condition for "speculative_retry"
            if ($fieldName == 'speculative_retry' && in_array($value, array('Xpercentile', 'Yms')))
            {
                $value = str_replace('X', $familyColumnConfig['speculative_retry_value'], $value);
                $value = str_replace('Y', $familyColumnConfig['speculative_retry_value'], $value);
            }

            // add property to array
            $properties[] = sprintf(
                '%s = %s',
                $fieldName,
                $this->formattedFieldValue($field, $value)
            );
        }

        // caching
        $cachingFields = array('caching_keys', 'caching_rows_per_partition');
        $cachingValues = array();
        foreach ($cachingFields as $cachingFieldName)
        {
            $cachingField = $this->config['ColumnFamily']['ColumnFamily'][$cachingFieldName];
            $cachingValue = $familyColumnConfig[$cachingFieldName];

            // override field and value when using "number"
            if ($cachingFieldName == 'caching_rows_per_partition' && $cachingValue == 'number')
            {
                $cachingField = $this->config['ColumnFamily']['ColumnFamily']['caching_rows_per_partition']['sub']['rows_per_partition_num'];
                $cachingValue = $familyColumnConfig['rows_per_partition_num'];
            }

            $cachingValues[] = sprintf(
                '\'%s\': %s',
                str_replace('caching_', '', $cachingFieldName),
                $this->formattedFieldValue($cachingField, $cachingValue)
            );
        }
        $cachingValue = "{\n" . implode(",\n", $cachingValues) . "\n}";
        $properties[] = "caching = $cachingValue";

        // compression
        if (@$familyColumnConfig['compression'] == '')
            $compressionValue = "''";
        else
        {
            $compressionFields = $this->config['ColumnFamilyCompressionSubOptions']['Compression'];
            $compressionValues = array(
                "'sstable_compression': '" . $familyColumnConfig['compression'] . "'"
            );
            foreach ($compressionFields as $compressionFieldName => $compressionField)
                $compressionValues[] = sprintf(
                    '\'%s\': %s',
                    $compressionFieldName,
                    $this->formattedFieldValue($compressionField, $familyColumnConfig[$compressionFieldName])
                );

            $compressionValue = "{\n" . implode(",\n", $compressionValues) . "\n}";
        }
        $properties[] = "compression = $compressionValue";

        // compaction
        $compactionFields = $this->config['ColumnFamilyCompactionSubOptions'][$familyColumnConfig['compaction']];
        $compactionValues = array(
            "'class': '" . $familyColumnConfig['compaction'] . "'"
        );
        foreach ($compactionFields as $compactionFieldName => $compactionField)
            $compactionValues[] = sprintf(
                '\'%s\': %s',
                $compactionFieldName,
                $this->formattedFieldValue($compactionField, @$familyColumnConfig[$compactionFieldName])
            );

        $compactionValue = "{\n" . implode(",\n", $compactionValues) . "\n}";
        $properties[] = "compaction = $compactionValue";

        // compact_storage
        if (@$familyColumnConfig['compact_storage'])
            $properties[] = 'COMPACT STORAGE';

        // clustering order
        if (@$familyColumnConfig['clustering_order'] && in_array($familyColumnConfig['clustering_order'], $fields))
            $properties[] = sprintf(
                'CLUSTERING ORDER BY (%s %s)',
                $familyColumnConfig['clustering_order'],
                @$familyColumnConfig['clustering_order_direction'] == 'asc' ? 'ASC' : 'DESC'
            );

        $query = sprintf(
            "CREATE TABLE\n%s.%s\n(\n%s\n)\nWITH\n%s;",
            $keyspace,
            $familyColumnConfig['name'],
            implode(",\n", $columnDefinitions),
            implode(" AND\n", $properties)
        );

        return $query;
    }

    public function addColumnQuery($columnConfig = array())
    {
        // check for empty or duplicate field names
        $fields = array();
        $pkeys = array();
        $skeys = array();

        // parse fields
        $this->parseFieldsFromRequestConfig($columnConfig, $fields, $pkeys, $skeys);

        $query = array();
        foreach ($fields as $fieldName => $fieldType)
            $query[] = sprintf(
                'ALTER TABLE %s.%s ADD %s %s;',
                $columnConfig['keyspace'],
                $columnConfig['columnFamily'],
                $fieldName,
                $fieldType
            );

        return implode("\n", $query);
    }

    public static function parseFieldTypeFromArray($type)
    {
        // return value directly, if type is string
        if (gettype($type) === 'string')
            return $type;

        // if type is array, check the kind
        if (gettype($type) === 'array')
        {
            $inherits = '';
            $i = 1;
            foreach ($type as $subType)
            {
                $inherits .= self::parseFieldTypeFromArray($subType);
                if ($i++ < count($type))
                    $inherits .= ',';
            }
            reset($type);
            if (gettype(key($type)) === 'integer')
                return $inherits;
            else
                return sprintf('%s<%s>', key($type), $inherits);
        }
    }

    private function formattedFieldValue($field, $value)
    {
        if ($field['type'] == 'bool')
            $fieldValue = $value ? 'true' : 'false';
        elseif (in_array($field['type'], array('int', 'float')))
            $fieldValue = $value;
        else
            $fieldValue = "'$value'";

        return $fieldValue;
    }

    public function execute($query)
    {
        $this->session->execute(new Cassandra\SimpleStatement($query));
        return true;
    }

    public function getColumnFamilies($keyspace = null, $name = null)
    {
        $query = 'SELECT * FROM system.schema_columnfamilies';
        if ($keyspace)
            $query .= " WHERE keyspace_name = '$keyspace'";

        $arr = array();
        $res = $this->session->execute(new Cassandra\SimpleStatement($query));
        foreach ($res as $row)
        {
            if ($name)
                $arr[] = $row[$name];
            else
                $arr[] = $row;
        }

        return $arr;
    }

    public function getColumns($keyspace = null, $columnFamily = null, $name = null)
    {
        $query = 'SELECT * FROM system.schema_columns';
        if ($keyspace)
            $query .= " WHERE keyspace_name = '$keyspace'";

        if ($columnFamily)
            $query .= ($keyspace ? ' AND ' : ' WHERE ') . "columnfamily_name = '$columnFamily'";

        $columnTypeMapping = $this->getColumnTypeMapping($keyspace . '.' . $columnFamily);

        $arr = array();
        $res = $this->session->execute(new Cassandra\SimpleStatement($query));
        foreach ($res as $row)
        {
            if ($name)
                $arr[] = $row[$name];
            else
                $arr[] = $row;
        }

        if (!$name)
        {
            // sort by component_index
            usort($arr, function ($a, $b) {
                return strcmp($a['component_index'], $b['component_index']);
            });

            // add type
            foreach ($arr as $index => $column)
                $arr[$index]['data_type'] = @$columnTypeMapping[$column['column_name']];
        }

        return $arr;
    }

    public function cqlshDescribe($name)
    {
        $cqlshBinary = self::getCqlshBinary();
        if (!$cqlshBinary)
            return false;

        $host = $this->clusterConfig['host'];
        $port = $this->clusterConfig['port'];

        return trim(shell_exec("$cqlshBinary $host $port -e 'DESCRIBE $name;'"));
    }

    public function getColumnTypeMapping($name)
    {
        $arr = array();

        $buf = $this->cqlshDescribe($name);
        $start = strpos($buf, '(');
        $end = strpos($buf, ')') - $start;
        $buf = substr($buf, $start + 1, $end);

        foreach (explode(',', $buf) as $row)
        {
            $parts = explode(' ', trim($row));
            if (count($parts) !== 2)
                continue;

            $arr[$parts[0]] = $parts[1];
        }

        return $arr;
    }

}