<?php

namespace AppBundle\Controller;

use AppBundle\Service\CassandraService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ListController extends Controller
{
    /**
     * @Route("/columnfamilies/{cluster}/{keyspace}/{columnfamily}", name="list")
     */
    public function defaultAction($cluster, $keyspace, $columnfamily)
    {
        $response = array(
            'status' => false,
            'columns' => array(),
            'data' => array(),
            'message' => null
        );

        try
        {
            $cassandra = new CassandraService($this->container, CassandraService::clusterConfig($cluster));

            $response['columns'] = $cassandra->getColumns($keyspace, $columnfamily);
            $response['data'] = $cassandra->getData($keyspace, $columnfamily);
            $response['status'] = true;
        }
        catch (\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return $this->render('AppBundle:list:index.html.twig', $response);
    }
}