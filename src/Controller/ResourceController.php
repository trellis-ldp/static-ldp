<?php

namespace Trellis\StaticLdp\Controller;

use Trellis\StaticLdp\Model\RDFSource;
use Trellis\StaticLdp\Model\NonRDFSource;
use Trellis\StaticLdp\Model\BasicContainer;
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

        $responseFormat = $this->getResponseFormat($app['config']['validRdfFormats'], $request);
        $responseMimeType = $this->getResponseMimeType($app['config']['validRdfFormats'], $request);

        $resource = null;
        if (is_file($requestedPath)) {
            $filenameChunks = explode('.', $requestedPath);
            $extension = array_pop($filenameChunks);
            $formats = $app['config']['validRdfFormats'];
            if (array_search($extension, array_column($formats, 'extension')) !== false) {
                // It is a RDF file
                $resource = new RDFSource($requestedPath, $responseFormat, $responseMimeType, $formats);
            } else {
                $resource = new NonRDFSource($requestedPath);
            }
        } else {
            $resource = new BasicContainer($requestedPath, $responseFormat, $responseMimeType);
        }
        return $resource->get($app, $request);
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

    /**
     * Find the valid RDF format
     *
     * @param array $validRdfFormats
     *   Supported formats from the config.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     *
     * @return string
     *   EasyRdf "format" or null if not supported.
     */
    private function getResponseFormat(array $validRdfFormats, Request $request)
    {
        if ($request->headers->has('accept')) {
            $accept = $request->getAcceptableContentTypes();
            foreach ($accept as $item) {
                $index = array_search($item, array_column($validRdfFormats, 'mimeType'));
                if ($index !== false) {
                    return $validRdfFormats[$index]['format'];
                }
                if (strpos($item, "text/html") >= 0) {
                    return "html";
                }
            }
        }
        return null;
    }

    /**
     * Find the mimeType for the request
     *
     * @param array $validRdfFormats
     *   Supported formats from the config.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     *
     * @return string
     *   MimeType or null if not defined.
     */
    private function getResponseMimeType(array $validRdfFormats, Request $request)
    {
        if ($request->headers->has('accept')) {
            $accept = $request->getAcceptableContentTypes();
            foreach ($accept as $item) {
                $index = array_search($item, array_column($validRdfFormats, 'mimeType'));
                if ($index !== false) {
                    return $item;
                }
                if (strpos($item, "text/html") >= 0) {
                    return "text/html";
                }
            }
        }
        return null;
    }
}
