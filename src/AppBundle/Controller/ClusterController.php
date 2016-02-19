<?php

namespace AppBundle\Controller;

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
            'clusters' => $this->getClusters()
        ));
    }

    /**
     * @Route("/clusters/remove/{cluster}", name="clusters_remove")
     */
    public function removeClusterAction($cluster)
    {
        $buf = file_get_contents($this->getClusterConfigurationFile());
        $buf = str_replace($cluster . "\n", '', $buf);
        file_put_contents($this->getClusterConfigurationFile(), $buf);

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
            $config = $params['host'] . ':' . $params['port'];

            if (in_array($config, $this->getClusters()))
                throw new \Exception('Entry already exists');

            file_put_contents($this->getClusterConfigurationFile(), $config . "\n", FILE_APPEND);

            $response['status'] = true;
        }
        catch(\Exception $e)
        {
            $response['message'] = $e->getMessage();
        }

        return new JsonResponse($response);
    }

    private function getClusterConfigurationFile()
    {
        $vendorPath = realpath($this->get('kernel')->getRootDir() . '/../var');
        if (!$vendorPath)
            throw new \Exception('Vendor path not found');

        $clusterConfigurationPath = $vendorPath . '/clusters';
        if (is_file($clusterConfigurationPath))
            return $clusterConfigurationPath;

        touch($clusterConfigurationPath);
        if (is_file($clusterConfigurationPath))
            return $clusterConfigurationPath;

        throw new \Exception('Unable to create cluster configuration dir ("' . $clusterConfigurationPath . '")');
    }

    private function getClusters()
    {
        return file($this->getClusterConfigurationFile(), FILE_IGNORE_NEW_LINES);
    }
}
