<?php

namespace AppBundle\Controller;

use AppBundle\Service\CassandraService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

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
            'columnFamilyDataTypes' => CassandraService::columnFamilyDataTypes,
            'columnFamilyCachingTypes' => CassandraService::columnFamilyCachingTypes,
            'columnFamilyCachingDefault' => CassandraService::columnFamilyCachingDefault,
            'columnFamilyCompactionTypes' => CassandraService::columnFamilyCompactionTypes,
            'columnFamilyCompactionDefault' => CassandraService::columnFamilyCompactionDefault,
            'columnFamilyCompressionTypes' => CassandraService::columnFamilyCompressionTypes,
            'columnFamilyCompressionDefault' => CassandraService::columnFamilyCompressionDefault
        ));
    }
}