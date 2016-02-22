<?php

namespace AppBundle\Service;

use Cassandra;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CassandraService {

    private $container, $cluster, $session;

    const keyspaceClasses = array('SimpleStrategy', 'NetworkTopologyStrategy');
    const columnFamilyDataTypes = array('asci', 'bigint', 'blob', 'boolean', 'counter', 'decimal', 'double', 'float', 'inet', 'int',
        'list', 'map', 'set', 'text', 'timestamp', 'uuid', 'timeuuid', 'varchar', 'varint');
    const columnFamilyCachingTypes = array('all', 'keys_only', 'rows_only', 'none');
    const columnFamilyCachingDefault = 'keys_only';
    const columnFamilyCompactionTypes = array('SizeTieredCompactionStrategy', 'DateTieredCompactionStrategy', 'LeveledCompactionStrategy');
    const columnFamilyCompactionDefault = 'SizeTieredCompactionStrategy';
    const columnFamilyCompressionTypes = array('LZ4Compressor', 'SnappyCompressor', 'DeflateCompressor', '');
    const columnFamilyCompressionDefault = 'SnappyCompressor';

    public function __construct(ContainerInterface $container, $clusterConfig = array())
    {
        $this->container = $container;

        $this->cluster = Cassandra::cluster()
            ->withContactPoints($clusterConfig['host'])
            ->withPort(intval($clusterConfig['port']))
            ->build();

        $this->session = $this->cluster->connect();
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

    public function addKeyspace($keyspaceConfig = array())
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

        $this->session->execute(new Cassandra\SimpleStatement($query));
        return true;
    }

    public function removeKeyspace($name)
    {
        $this->session->execute(new Cassandra\SimpleStatement('DROP KEYSPACE ' . $name));
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