<?php

namespace AppBundle\Controller;

use AppBundle\Service\CqlshService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ClusterController extends Controller
{
    /**
     * @Route("/", name="start")
     */
    public function defaultAction()
    {
        return $this->redirectToRoute('clusters');
    }

    /**
     * @Route("/clusters", name="clusters")
     */
    public function clustersAction()
    {
        // replace this example code with whatever you need
        return $this->render('AppBundle:clusters:index.html.twig', array(
            'clusters' => CqlshService::getClusters($this->container),
            'cqlVersions' => CqlshService::cqlVersions
        ));
    }

    /**
     * @Route("/clusters/remove/{cluster}", name="clusters_remove")
     */
    public function removeClusterAction($cluster)
    {
        $buf = file_get_contents(CqlshService::getClusterConfigurationFile($this->container));
        $buf = str_replace($cluster . "\n", '', $buf);
        file_put_contents(CqlshService::getClusterConfigurationFile($this->container), $buf);

        return $this->redirectToRoute('clusters');
    }

    /**
     * @Route("/api/clusters/add", name="clusters_add")
     */
    public function addClusterAction(Request $request)
    {
        $response = array(
            'status' => false,
            'message' => null
        );

        try
        {
            $params = $request->request->all();
            $config = sprintf('%s:%s:%s:%s', $params['host'], $params['port'], $params['version'], $params['name']);

            if (in_array($config, CqlshService::getClusters($this->container)))
                throw new \Exception('Entry already exists');

            file_put_contents(CqlshService::getClusterConfigurationFile($this->container), $config . "\n", FILE_APPEND);

            $response['status'] = true;
        }
        catch(\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return new JsonResponse($response);
    }
}
