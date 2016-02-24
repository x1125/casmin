<?php

namespace AppBundle\Service;

use Cassandra;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CassandraService {

    private $container, $cluster, $session, $config;

    public function __construct(ContainerInterface $container, $clusterConfig = array())
    {
        $this->container = $container;

        $this->cluster = Cassandra::cluster()
            ->withContactPoints($clusterConfig['host'])
            ->withPort(intval($clusterConfig['port']))
            ->build();

        $this->session = $this->cluster->connect();

        $this->config = array(
            'ColumnFamily' => $container->get('config_service')->getConfiguration('ColumnFamily'),
            'ColumnFamilyCompactionSubOptions' => $container->get('config_service')->getConfiguration('ColumnFamilyCompactionSubOptions'),
            'ColumnFamilyCompressionSubOptions' => $container->get('config_service')->getConfiguration('ColumnFamilyCompressionSubOptions'),
        );
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

    public function removeKeyspaceQuery($name)
    {
        return 'DROP KEYSPACE ' . $name;
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
        $fieldNames = array();
        foreach ($familyColumnConfig['field'] as $fieldName)
        {
            if (strlen($fieldName) < 1)
                throw new \Exception('Empty field name given (Row #' . (count($fieldNames) + 1) . ')');

            if (in_array($fieldName, $fieldNames))
                throw new \Exception('Duplicate field name ("' . $fieldName . '")');

            $fieldNames[] = $fieldName;
        }

        // count primary keys
        $primaryKeyCount = 0;
        foreach ($familyColumnConfig['prefix'] as $prefix)
        {
            if ($prefix == 'primary')
                $primaryKeyCount++;
        }

        // check for primary key
        if ($primaryKeyCount < 1)
            throw new \Exception('You need at least one primary key');

        $columnDefinitions = array();
        $properties = array();

        // parse all string properties
        foreach (array('compaction', 'comment', 'compression', 'caching') as $key)
        {
            if (@$familyColumnConfig[$key])
                $properties[] = "$key = '{$familyColumnConfig[$key]}'";
        }

        // parse all int/float properties
        foreach (array('bloom_filter_fp_chance', 'dclocal_read_repair_chance', 'gc_grace_seconds', 'read_repair_chance') as $key)
        {
            if (@$familyColumnConfig[$key])
                $properties[] = "$key = {$familyColumnConfig[$key]}";
        }

        // parse all bool properties
        foreach (array('populate_io_cache_on_flush', 'replicate_on_write') as $key)
            $properties[] = "$key = " . (@$familyColumnConfig[$key] ? 'true' : 'false');

        $query = sprintf(
            "CREATE TABLE\n%s.%s\n(%s)\nWITH\n%s",
            $keyspace,
            $familyColumnConfig['name'],
            implode(",\n", $columnDefinitions),
            implode(" AND\n", $properties)
        );

        $query .= "\r\n" . print_r($familyColumnConfig, true);

        return $query;
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

}