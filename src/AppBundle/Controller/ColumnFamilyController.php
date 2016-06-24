<?php

namespace AppBundle\Controller;

use AppBundle\Service\CqlshService;
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

        try
        {
            $cassandra = new CqlshService($this->container, CqlshService::clusterConfig($cluster));

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

            $cassandra = new CqlshService($this->container, CqlshService::clusterConfig($params['cluster']));

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

    /**
     * @Route("/columnFamilies/remove/{cluster}/{keyspace}/{columnfamily}", name="columnfamily_remove")
     */
    public function removeKeyspaceAction($cluster, $keyspace, $columnfamily)
    {
        try
        {
            $cassandra = new CqlshService($this->container, CqlshService::clusterConfig($cluster));

            $query = $cassandra->removeColumnFamilyQuery($keyspace, $columnfamily);
            $cassandra->execute($query);
        }
        catch (\Exception $e)
        {
            // TODO: output
        }

        return $this->redirectToRoute('columnFamilies', array(
            'cluster' => $cluster,
            'keyspace' => $keyspace
        ));
    }
}