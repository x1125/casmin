<?php

namespace AppBundle\Controller;

use AppBundle\Service\CqlshService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ColumnController extends Controller
{
    /**
     * @Route("/columns/{cluster}/{keyspace}/{columnfamily}", name="columns")
     */
    public function defaultAction($cluster, $keyspace, $columnfamily)
    {
        $response = array(
            'status' => false,
            'columnFamilies' => array(),
            'columns' => array(),
            'message' => null
        );

        try
        {
            $cassandra = new CqlshService($this->container, CqlshService::clusterConfig($cluster));

            $response['columnFamilies'] = $cassandra->getColumnFamilies($keyspace);
            $response['columns'] = $cassandra->getColumns($keyspace, $columnfamily);
            $response['status'] = true;
        }
        catch (\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return $this->render('AppBundle:columns:index.html.twig', array(
            'response' => $response,
            'ColumnFamily' => $this->get('config_service')->getConfiguration('ColumnFamily')
        ));
    }

    /**
     * @Route("/api/column/add", name="column_add")
     */
    public function addColumnAction(Request $request)
    {
        $response = array(
            'status' => false,
            'message' => null
        );

        try
        {
            $params = $request->request->all();

            $cassandra = new CqlshService($this->container, CqlshService::clusterConfig($params['cluster']));

            $query = $cassandra->addColumnQuery($params);

            $response['status'] = true;
            $response['query'] = $query;
        }
        catch(\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/columns/remove/{cluster}/{keyspace}/{columnfamily}/{column}", name="column_remove")
     */
    public function removeColumnAction($cluster, $keyspace, $columnfamily, $column)
    {
        try
        {
            $cassandra = new CqlshService($this->container, CqlshService::clusterConfig($cluster));

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
            'columnfamily' => $columnfamily
        ));
    }
}