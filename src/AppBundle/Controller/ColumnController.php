<?php

namespace AppBundle\Controller;

use AppBundle\Service\CassandraService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ColumnController extends Controller
{
    /**
     * @Route("/columns/{cluster}/{keyspace}/{columnFamily}", name="columns")
     */
    public function defaultAction($cluster, $keyspace, $columnFamily)
    {
        $response = array(
            'status' => false,
            'columnFamilies' => array(),
            'columns' => array(),
            'message' => null
        );

        try
        {
            $cassandra = new CassandraService($this->container, CassandraService::clusterConfig($cluster));

            $response['columnFamilies'] = $cassandra->getColumnFamilies($keyspace);
            $response['columns'] = $cassandra->getColumns($keyspace, $columnFamily);
            $response['status'] = true;
        }
        catch (\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return $this->render('AppBundle:columns:index.html.twig', array(
            'response' => $response
        ));
    }

    /**
     * @Route("/columns/remove/{cluster}/{keyspace}/{columnfamily}/{column}", name="column_remove")
     */
    public function removeColumnAction($cluster, $keyspace, $columnfamily, $column)
    {
        try
        {
            $cassandra = new CassandraService($this->container, CassandraService::clusterConfig($cluster));

            $query = $cassandra->removeColumnQuery($keyspace, $columnfamily, $column);
            $cassandra->execute($query);
        }
        catch (\Exception $e)
        {
            // TODO: output
        }

        return $this->redirectToRoute('columns', array(
            'cluster' => $cluster,
            'keyspace' => $keyspace,
            'columnFamily' => $columnfamily
        ));
    }
}