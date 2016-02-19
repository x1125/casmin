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

        list($host, $port) = explode(':', $cluster);

        try
        {
            $cassandra = new CassandraService($this->container, array(
                'host' => $host,
                'port' => $port
            ));

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

            list($host, $port) = explode(':', $params['cluster']);
            $cassandra = new CassandraService($this->container, array(
                'host' => $host,
                'port' => $port
            ));

            $cassandra->addKeyspace($params);

            $response['status'] = true;
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
            list($host, $port) = explode(':', $cluster);
            $cassandra = new CassandraService($this->container, array(
                'host' => $host,
                'port' => $port
            ));

            $cassandra->removeKeyspace($keyspace);
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