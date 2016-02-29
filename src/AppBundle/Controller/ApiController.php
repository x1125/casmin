<?php

namespace AppBundle\Controller;

use AppBundle\Service\CassandraService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiController extends Controller
{
    /**
     * @Route("/api/execute", name="api_execute")
     */
    public function executeAction(Request $request)
    {
        $response = array(
            'status' => false,
            'columnFamilies' => array(),
            'message' => null
        );

        $params = $request->request->all();
        $cluster = @$params['cluster'];
        $query = @$params['query'];

        try
        {
            $cassandra = new CassandraService($this->container, CassandraService::clusterConfig($cluster));

            $ret = $cassandra->execute($query);
            $response['status'] = true;
            $response['ret'] = $ret;
        }
        catch (\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return new JsonResponse($response);
    }
}