<?php

namespace App\Controller;

use App\TrellisConfiguration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Model\ResourceFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

class ResourceController extends AbstractController
{

    /**
     * The twig environment to render HTML.
     *
     * @var \Twig\Environment
     */
    private $twig_provider;

    /**
     * The configuration.
     *
     * @var mixed
     */
    private $configuration;

    /**
     * RequestStatck to get the current request.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    private $requestStack;

    /**
     * ResourceController constructor.
     *
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The RequestStack.
     * @param \Twig\Environment $twig_provider
     *   The Twig environment.
     * @param $configuration
     *   The default configuration.
     */
    public function __construct(RequestStack $request_stack, Environment $twig_provider, $configuration)
    {
        $this->requestStack = $request_stack;
        $this->twig_provider = $twig_provider;
        $this->configuration = $configuration;
        $this->loadConfig();
    }

    /**
     * Load a configuration file if one exists in the TRELLIS_CONFIG_DIR
     */
    public function loadConfig()
    {
        $filename = rtrim(realpath($this->configuration['configuration_dir']), '/') . '/settings.yml';
        if (file_exists($filename)) {
            $config = Yaml::parse(file_get_contents($filename));
            $processor = new Processor();
            $trellis_configuration = new TrellisConfiguration();
            $this->configuration = $processor->processConfiguration(
                $trellis_configuration,
                [$config]
            );
        }
    }

    /**
     * Respond to a GET or HEAD request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function get($path)
    {

        $docroot = $this->configuration['sourceDirectory'];
        if (!empty($path)) {
            $path = "/{$path}";
        }

        $requestedPath = "{$docroot}{$path}";
        if (!file_exists($requestedPath)) {
            return new Response("Not Found", 404);
        }

        $formats = $this->configuration['validRdfFormats'];
        $options = [
            "contentDisposition" => $this->configuration['contentDisposition'],
            'extraPropertiesFilename' => $this->configuration['extraPropertiesFilename'],
        ];

        $resource = ResourceFactory::create($requestedPath, $formats, $this->configuration);

        return $resource->respond($this->requestStack->getCurrentRequest(), $this->twig_provider, $options);
    }

    /**
     * Response to a generic options request.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function options()
    {
        $headers = [
            "Allow" => "OPTIONS, GET, HEAD",
        ];
        return new Response('', 200, $headers);
    }
}
