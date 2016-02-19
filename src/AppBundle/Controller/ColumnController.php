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

        list($host, $port) = explode(':', $cluster);

        try
        {
            $cassandra = new CassandraService($this->container, array(
                'host' => $host,
                'port' => $port
            ));

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
}