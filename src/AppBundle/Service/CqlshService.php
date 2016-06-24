<?php

namespace AppBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

class CqlshService {

    private $container, $clusterConfig, $config, $cqlshBinaries;

    const keyspaceClasses = array('SimpleStrategy', 'NetworkTopologyStrategy');

    const cqlVersions = array(
        '3.1', // cassandra ~2.1
        '3.3'  // cassandra ~3.x
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

        $this->cqlshBinaries = $container->getParameter('cqlsh');
        if (!$this->cqlshBinaries)
            throw new \Exception('cqlsh binaries are not configured in parameters.yml');

        $this->cqlVersion = $clusterConfig['version'];

        if (!in_array($this->cqlVersion, self::cqlVersions))
            throw new \Exception('Unknown cql version (' . $this->cqlVersion . ')');

        $this->config = array(
            'ColumnFamily' => $container->get('config_service')->getConfiguration('ColumnFamily'),
            'ColumnFamilyCompactionSubOptions' => $container->get('config_service')->getConfiguration('ColumnFamilyCompactionSubOptions'),
            'ColumnFamilyCompressionSubOptions' => $container->get('config_service')->getConfiguration('ColumnFamilyCompressionSubOptions'),
        );
    }

    private function parseCqlTable($buf)
    {
        $arr = array();
        $cellHeaders = array();
        $cqlTableRows = explode("\n", $buf);

        // get cell headers
        $cells = explode('|', array_shift($cqlTableRows)); // remove index 0
        foreach ($cells as $cell)
            $cellHeaders[] = trim($cell);

        // remove spacing line
        array_shift($cqlTableRows); // remove index 1

        // get cell data
        $i = 0;
        foreach ($cqlTableRows as $row)
        {
            $cells = explode('|', $row);

            // amount of cells does not match - skip
            if (count($cells) !== count($cellHeaders))
                continue;

            foreach ($cells as $index => $cell)
                $arr[$i][$cellHeaders[$index]] = $this->parseCqlTableValue(trim($cell));

            $i++;
        }

        return $arr;
    }

    private function parseCqlTableValue($buf)
    {
        // replace booleans
        if ($buf === 'True')
            return true;
        elseif ($buf === 'False')
            return false;

        if (substr($buf, 0, 1) === '{' && substr($buf, -1) === '}')
            return json_decode(str_replace('\'', '"', $buf), true);

        return $buf;
    }

    public function getKeyspaces($name = null)
    {
        $arr = array();
        $buf = $this->execute(
            'SELECT * FROM ' . ($this->cqlVersion === '3.1' ? 'system.schema_keyspaces' : 'system_schema.keyspaces')
        );

        $res = $this->parseCqlTable($buf);

        // add some backwards compatibility
        if ($this->cqlVersion === '3.3')
        {
            foreach ($res as &$row)
            {
                if (array_key_exists('replication', $row))
                {
                    $row['strategy_class'] = $row['replication']['class'];
                    $row['strategy_options'] = array();

                    if (array_key_exists('replication_factor', $row['replication']))
                        $row['strategy_options']['replication_factor'] = $row['replication']['replication_factor'];
                }
            }
        }

        foreach ($res as $resRow)
        {
            if ($name)
                $arr[] = $resRow[$name];
            else
                $arr[] = $resRow;
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

    public function getColumnFamilies($keyspace = null, $name = null)
    {
        //$query = 'SELECT * FROM system.schema_columnfamilies';
        $query = 'SELECT * FROM ' . ($this->cqlVersion === '3.1' ? 'system.schema_columnfamilies' : 'system_schema.tables');
        if ($keyspace)
            $query .= " WHERE keyspace_name = '$keyspace'";

        $arr = array();
        $buf = $this->execute($query);

        $res = $this->parseCqlTable($buf);

        // add some backwards compatibility
        if ($this->cqlVersion === '3.3')
        {
            foreach ($res as &$row)
            {
                $row['columnfamily_name'] = $row['table_name'];
                $row['column_name'] = $row['table_name'];
                //$row['comment'] = '-';
                $row['type'] = 'n/a';
            }
        }

        foreach ($res as $resRow)
        {
            if ($name)
                $arr[] = $resRow[$name];
            else
                $arr[] = $resRow;
        }

        return $arr;
    }

    public function getColumns($keyspace = null, $columnFamily = null, $name = null)
    {
        $query = 'SELECT * FROM ' . ($this->cqlVersion === '3.1' ? 'system.schema_columns' : 'system_schema.columns');
        if ($keyspace)
            $query .= " WHERE keyspace_name = '$keyspace'";

        if ($columnFamily)
        {
            $query .= $keyspace ? ' AND ' : ' WHERE ';
            $query .= sprintf(
                "%s = '$columnFamily'",
                ($this->cqlVersion === '3.1' ? 'columnfamily_name' : 'table_name')
            );
        }

        $columnTypeMapping = $this->getColumnTypeMapping($keyspace . '.' . $columnFamily);

        $arr = array();
        $buf = $this->execute($query);

        $res = $this->parseCqlTable($buf);

        // add some backwards compatibility
        if ($this->cqlVersion === '3.3')
        {
            foreach ($res as &$row)
            {
                $row['component_index'] = $row['position'];
                $row['index_name'] = $row['kind'];
                $row['index_type'] = 'null';
                $row['index_options'] = $row['type'];
            }
        }

        foreach ($res as $resRow)
        {
            if ($name)
                $arr[] = $resRow[$name];
            else
                $arr[] = $resRow;
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

    public function getData($keyspace = null, $columnFamily = null)
    {
        $query = "SELECT * FROM $keyspace.$columnFamily";

        $arr = array();
        $buf = $this->execute($query);

        $res = $this->parseCqlTable($buf);

        foreach ($res as $row)
            $arr[] = $row;

        return $arr;
    }

    public function cqlExecuteFile($file)
    {
        $cqlshBinary = self::getCqlshBinary();
        if (!$cqlshBinary)
            return false;

        $host = $this->clusterConfig['host'];
        $port = $this->clusterConfig['port'];

        return trim(shell_exec("$cqlshBinary $host $port -f $file 2>&1"));
    }

    public function cqlshDescribe($name)
    {
        return $this->execute("DESCRIBE $name");
    }

    public function getColumnTypeMapping($name)
    {
        $arr = array();

        $buf = $this->cqlshDescribe($name);
        $start = strpos($buf, '(');
        $end = strpos($buf, ')') - $start;
        $buf = substr($buf, $start + 1, $end);
        $buf = trim($buf);

        foreach (explode("\n", $buf) as $row)
        {
            $parts = explode(' ', trim($row), 2);
            if (count($parts) !== 2)
                continue;

            $arr[$parts[0]] = substr(trim($parts[1]), 0, -1);
        }

        return $arr;
    }

    public static function getClusterConfigurationFile(ContainerInterface $container)
    {
        $vendorPath = realpath($container->get('kernel')->getRootDir() . '/../var');
        if (!$vendorPath)
            throw new \Exception('Vendor path not found');

        $clusterConfigurationPath = $vendorPath . '/clusters';
        if (is_file($clusterConfigurationPath))
            return $clusterConfigurationPath;

        touch($clusterConfigurationPath);
        if (is_file($clusterConfigurationPath))
            return $clusterConfigurationPath;

        throw new \Exception('Unable to create cluster configuration dir ("' . $clusterConfigurationPath . '")');
    }

    public static function getClusters(ContainerInterface $container)
    {
        return file(self::getClusterConfigurationFile($container), FILE_IGNORE_NEW_LINES);
    }

    public function execute($query)
    {
        // append semicolon if not already set as last sign
        if (substr($query, -1) !== ';')
            $query .= ';';

        // create temporary file with query
        $tmpFile = tempnam('/tmp', 'casmin');
        file_put_contents($tmpFile, $query);

        // select the cqlsh binary matching the cql version
        $cqlshBinary = $this->cqlshBinaries[$this->cqlVersion];
        if (!$cqlshBinary)
            throw new \Exception('cqlsh binary was not found');

        // execute process
        $proc = proc_open(
            sprintf(
                "%s --no-color %s %s -f %s",
                $cqlshBinary,
                $this->clusterConfig['host'],
                $this->clusterConfig['port'],
                $tmpFile
            ),
            [
                1 => ['pipe','w'],
                2 => ['pipe','w'],
            ],
            $pipes
        );

        // read stdout and stderr
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($proc);

        // remove query file
        unlink($tmpFile);

        if ($stderr !== '')
            throw new \Exception('error running query: ' . $stderr);

        return trim($stdout);
    }

}