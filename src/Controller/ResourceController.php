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

        $formats = $app['config']['validRdfFormats'];
        $options = [
            "contentDisposition" => $app['config']['contentDisposition']
        ];

        $resource = ResourceFactory::create($requestedPath, $formats);
        return $resource->respond($app, $request, $options);
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
