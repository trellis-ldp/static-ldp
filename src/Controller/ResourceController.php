<?php

namespace Trellis\StaticLdp\Controller;

use Trellis\StaticLdp\Model\BasicContainer;
use Trellis\StaticLdp\Model\NonRDFSource;
use Trellis\StaticLdp\Model\RDFSource;
use Trellis\StaticLdp\Model\ResourceFactory;
use Silex\Api\ControllerProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResourceController implements ControllerProviderInterface
{
    const LDP_NS = "http://www.w3.org/ns/ldp#";
    const RDF_NS = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
    const DCTERMS_NS = "http://purl.org/dc/terms/";

    // Its a class, no need to pass all via arguments around.
    private $modifiedTime = null;
    private $contentLength = 0;
    private $eTag = null;

    /**
     * {@inheritdoc}
     */
    public function connect(Application $app)
    {

        // Shared Controller collection Middleware
        $controllers = $app['controllers_factory']
          ->assert('path', '.+')
          ->value('path', '');

        // Options
        $controllers->options("/{path}", "staticldp.resourcecontroller:options")
            ->bind('staticldp.serverOptions');
        // Generic GET.
        $controllers->match("/{path}", "staticldp.resourcecontroller:get")
            ->method('HEAD|GET')
            ->bind('staticldp.resourceGet');

        return $controllers;
    }

    /**
     * {@inheritdoc}
     */
    public function get(Application $app, Request $request, $path)
    {
        $docroot = $app['config']['sourceDirectory'];
        if (!empty($path)) {
            $path = "/{$path}";
        }

        $requestedPath = "{$docroot}{$path}";
        if (!file_exists($requestedPath)) {
            return new Response("Not Found", 404);
        }

        //$responseFormat = $this->getResponseFormat($app['config']['validRdfFormats'], $request);
        //$responseMimeType = $this->getResponseMimeType($app['config']['validRdfFormats'], $request);
        $formats = $app['config']['validRdfFormats'];

        $resource = ResourceFactory::create($requestedPath, $formats);
        return $resource->respond($app, $request);
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
