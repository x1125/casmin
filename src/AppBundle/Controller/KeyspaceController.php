<?php

namespace AppBundle\Controller;

use AppBundle\Service\CassandraService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class KeyspaceController extends Controller
{
    /**
     * @Route("/keyspaces/{cluster}", name="keyspaces")
     */
    public function defaultAction($cluster)
    {
        $response = array(
            'status' => false,
            'keyspaces' => array(),
            'message' => null
        );

        try
        {
            $cassandra = new CassandraService($this->container, CassandraService::clusterConfig($cluster));

            $response['keyspaces'] = $cassandra->getKeyspaces();
            $response['status'] = true;
        }
        catch (\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return $this->render('AppBundle:keyspaces:index.html.twig', array(
            'response' => $response,
            'keyspaceClasses' => CassandraService::keyspaceClasses
        ));
    }

    /**
     * @Route("/api/keyspaces/add", name="keyspaces_add")
     */
    public function addKeyspaceAction(Request $request)
    {
        $response = array(
            'status' => false,
            'message' => null
        );

        try
        {
            $params = $request->request->all();

            $cassandra = new CassandraService($this->container, CassandraService::clusterConfig($params['cluster']));

            $query = $cassandra->addKeyspaceQuery($params);

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
     * @Route("/keyspaces/remove/{cluster}/{keyspace}", name="keyspaces_remove")
     */
    public function removeKeyspaceAction($cluster, $keyspace)
    {
        try
        {
            $cassandra = new CassandraService($this->container, CassandraService::clusterConfig($cluster));

            $query = $cassandra->removeKeyspaceQuery($keyspace);
            $cassandra->execute($query);
        }
        catch (\Exception $e)
        {
            // TODO: output
        }

        return $this->redirectToRoute('keyspaces', array(
            'cluster' => $cluster
        ));
    }
}