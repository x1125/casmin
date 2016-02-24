<?php

namespace AppBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Parser;

class ConfigService {

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getConfiguration($name)
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/../Config');

        $targetConfigFile = $name . '.yml';

        /**
         * @var $file SplFileInfo
         */
        $configFiles = array();
        foreach ($finder as $file)
            $configFiles[$file->getBasename()] = $file->getRealPath();

        if (!array_key_exists($targetConfigFile, $configFiles))
            throw new \Exception('Configuration file not found ("' . $targetConfigFile . '")');

        $yaml = new Parser();
        return $yaml->parse(file_get_contents($configFiles[$targetConfigFile]));
    }

}