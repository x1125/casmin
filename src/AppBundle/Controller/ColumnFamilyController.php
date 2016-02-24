<?php

namespace AppBundle\Controller;

use AppBundle\Service\CassandraService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ColumnFamilyController extends Controller
{
    /**
     * @Route("/columnfamilies/{cluster}/{keyspace}", name="columnFamilies")
     */
    public function defaultAction($cluster, $keyspace)
    {
        $response = array(
            'status' => false,
            'columnFamilies' => array(),
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
            $response['status'] = true;
        }
        catch (\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return $this->render('AppBundle:columnFamilies:index.html.twig', array(
            'response' => $response,
            'ColumnFamily' => $this->get('config_service')->getConfiguration('ColumnFamily'),
            'ColumnFamilyCompactionSubOptions' => $this->get('config_service')->getConfiguration('ColumnFamilyCompactionSubOptions'),
            'ColumnFamilyCompressionSubOptions' => $this->get('config_service')->getConfiguration('ColumnFamilyCompressionSubOptions'),
        ));
    }

    /**
     * @Route("/api/columnFamilies/add", name="columnFamily_add")
     */
    public function addColumnFamilyAction(Request $request)
    {
        $response = array(
            'status' => false,
            'message' => null
        );

        try
        {
            $params = $request->request->all();

            list($host, $port) = explode(':', $params['cluster']);
            $cassandra = new CassandraService($this->container, array(
                'host' => $host,
                'port' => $port
            ));

            $query = $cassandra->addColumnFamilyQuery($params);

            $response['status'] = true;
            $response['query'] = $query;
        }
        catch(\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return new JsonResponse($response);
    }
}