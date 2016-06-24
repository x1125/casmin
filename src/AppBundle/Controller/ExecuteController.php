<?php

namespace AppBundle\Controller;

use AppBundle\Service\CqlshService;
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
            'clusters' => CqlshService::getClusters($this->container)
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

            $cassandra = new CqlshService($this->container, CqlshService::clusterConfig($params['cluster']));

            $output = $cassandra->execute($params['command']);

            $response['status'] = true;
            $response['output'] = htmlspecialchars($output);
        }
        catch(\Exception $e)
        {
            $response['message'] = htmlspecialchars($e->getMessage());
        }

        return new JsonResponse($response);
    }
}