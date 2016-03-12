<?php

namespace AppBundle\Controller;

use AppBundle\Service\CassandraService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ExecuteController extends Controller
{
    /**
     * @Route("/execute", name="execute")
     */
    public function defaultAction()
    {
        return $this->render('AppBundle:execute:index.html.twig', array(
            'clusters' => CassandraService::getClusters($this->container)
        ));
    }

    /**
     * @Route("/api/execute/command", name="execute_command")
     */
    public function executeCommandAction(Request $request)
    {
        $response = array(
            'status' => false,
            'message' => null
        );

        try
        {
            $params = $request->request->all();

            $cassandra = new CassandraService($this->container, CassandraService::clusterConfig($params['cluster']));

            $output = $cassandra->command($params['command']);

            $response['status'] = true;
            $response['output'] = $output;
        }
        catch(\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return new JsonResponse($response);
    }
}