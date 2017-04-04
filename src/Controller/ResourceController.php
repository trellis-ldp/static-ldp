<?php

namespace Trellis\StaticLdp\Controller;

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

        //
        // Define routing referring to controller services
        //

        // Options
        $controllers->options("/{path}", "staticldp.resourcecontroller:options")
            ->bind('staticldp.serverOptions');
        // Generic GET.
        $controllers->match("/{path}", "staticldp.resourcecontroller:getOrHead")
            ->method('HEAD|GET')
            ->bind('staticldp.resourceGetOrHead');

        return $controllers;
    }

    /**
     * Perform the GET or HEAD request.
     *
     * @param \Silex\Application $app
     *   The Silex application.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     * @param $path
     *   The path parameter from the request.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getOrHead(Application $app, Request $request, $path)
    {

        // Get default responseFormat.
        $responseFormat = $app['config']['defaultRdfFormat'];

        // Better to define it at the beginning
        $response = new Response();
          
        $docroot = $app['config']['sourceDirectory'];
        if (!empty($path)) {
            $path = "/{$path}";
        }

        $requested_path = "{$docroot}{$path}";
        if (!file_exists($requested_path)) {
            return new Response("Not Found", 404);
        }

        if ($request->headers->has('accept')) {
            $format = $this->getResponseFormat($app['config']['validRdfFormats'], $request);
            if (!is_null($format)) {
                $responseFormat = $format;
            }
        }

        $this->modifiedTime = \DateTime::createFromFormat('U', filemtime($requested_path));

        // It is a file.
        if (is_file($requested_path)) {
            // Common to all existing files.
            //
            $this->contentLength = filesize($requested_path);
            $response->setETag($this->provideEtag());
            $response->setLastModified($this->modifiedTime);
            $response->headers->set("Vary", "Accept");
            $response->setPublic();
           
            if (!$response->isNotModified($request)) {
                $response = $this->getFile(
                    $app,
                    $request,
                    $response,
                    $requested_path,
                    $responseFormat,
                    $app['config']['validRdfFormats']
                );
            }
        } elseif (strpos($request->headers->get('Accept'), "text/html") !== false) {
            // Assume it is a directory, a basic container
            $response = $this->getDirectoryHTML(
                $app,
                $request,
                $requested_path
            );
        } else {
            // We assume it's a directory.
            $response = $this->getDirectory(
                $request,
                $response,
                $requested_path,
                $responseFormat,
                $app['config']['validRdfFormats']
            );
        }
        // We are trusting we have response here
        
        return $response;
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
     * Find the valid mimeType and
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
            }
        }
        return null;
    }

    /**
     * Serve a file from the filesystem.
     *
     * @param \Silex\Application $app
     *   The Silex application.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     * @param \Symfony\Component\HttpFoundation\Response $response
     *   The outgoing response.
     * @param $requested_path,
     *   Path to file we are serving.
     * @param $responseFormat
     *   The format to respond in, if it is a RDFSource.
     * @param array $validRdfFormats
     *   The configured validRdfFormats.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function getFile(
        Application $app,
        Request $request,
        Response $response,
        $requested_path,
        $responseFormat,
        array $validRdfFormats
    ) {
        // Plain might be RDF, check the file extension.
        $dirChunks = explode(DIRECTORY_SEPARATOR, $requested_path);
        $filename = array_pop($dirChunks);
        $filenameChunks = explode('.', $filename);
       
        $extension = array_pop($filenameChunks);

        $index = array_search($extension, array_column($validRdfFormats, 'extension'));
        if ($index !== false) {
            // This is a RDF file.
            $inputFormat = $validRdfFormats[$index]['format'];

            // Converting RDF from something to something else.
            $graph = new \EasyRdf_Graph();
            $graph->parseFile($requested_path, $inputFormat, $request->getUri());
            $content = $graph->serialise($responseFormat);
            $response->setContent($content);
            $headers = [
                "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                           "<".self::LDP_NS."RDFSource>; rel=\"type\"" ],
                "Content-Length" => strlen($content),
            ];

            $index = array_search($responseFormat, array_column($validRdfFormats, 'format'));
            if ($index !== false) {
                $headers['Content-Type'] = $validRdfFormats[$index]['mimeType'];
            }
            $response->headers->add($headers);
        } else {
            // This is not a RDF file.
            $contentLength = filesize($requested_path);
            $responseMimeType = mime_content_type($requested_path);
            $filename = explode("/", $requested_path);
            $filename = end($filename);
            $headers = [
                "Content-Type" => $responseMimeType,
                "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                           "<".self::LDP_NS."NonRDFSource>; rel=\"type\""],
                "Content-Length" => $contentLength,
                "Content-Disposition" => "attachment; filename=\"{$filename}\"",
            ];

            $response->headers->add($headers);

            if ($request->getMethod() == 'GET') {
                $stream = function () use ($requested_path) {
                    readfile($requested_path);
                };

                return $app->stream($stream, 200, $response->headers->all());
            }
        }

        return $response;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     * @param $requested_path
     *   Path to file we are serving.
     * @return \EasyRDF_Graph
     */
    private function getGraphForPath(Request $request, $requested_path)
    {
        $subject = $request->getUri();
        $predicate = self::LDP_NS . "contains";
        
        $namespaces = new \EasyRdf_Namespace();
        $namespaces->set("ldp", self::LDP_NS);
        $namespaces->set("dc", self::DCTERMS_NS);

        $graph = new \EasyRdf_Graph();
        $graph->addLiteral($subject, self::DCTERMS_NS . "modified", $this->modifiedTime);
        $graph->addResource($subject, self::RDF_NS . "type", self::LDP_NS . "Resource");
        $graph->addResource($subject, self::RDF_NS . "type", self::LDP_NS . "BasicContainer");

        foreach (new \DirectoryIterator($requested_path) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            $filename = rtrim($subject, '/') . '/' . ltrim($fileInfo->getFilename(), '/');
            $graph->addResource($subject, $predicate, $filename);
        }
        return $graph;
    }

    /**
     * @param \Silex\Application $app
     *   The Silex application.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     * @param $requested_path
     *   Path to file we are serving.
    * @return \Symfony\Component\HttpFoundation\Response
     */
    private function getDirectoryHTML(Application $app, Request $request, $requested_path)
    {
        $headers = [
            "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                       "<".self::LDP_NS."BasicContainer>; rel=\"type\""],
            "Content-Type" => "text/html"
        ];

        $options = [
            "compact" => true,
            "context" => (object) [
                'id' => '@id',
                'type' => '@type',
                'modified' => (object) [
                    '@id' => self::DCTERMS_NS . 'modified',
                    '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime'
                ],
                'contains' => (object) [
                    '@id' => self::LDP_NS . 'contains',
                    '@type' => '@id'
                ]
            ]
        ];

        $jsonld = $this->getGraphForPath($request, $requested_path)->serialise("jsonld", $options);
        $template = $app['config']['template'];

        return $app['twig']->render($template, json_decode($jsonld, true));
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     * @param \Symfony\Component\HttpFoundation\Response $response
     *   The outgoing response.
     * @param $requested_path
     *   Path to file we are serving.
     * @param $responseFormat
     *   The format to respond in, if it is a RDFSource.
     * @param array $validRdfFormats
     *   The configured validRdfFormats.
    * @return \Symfony\Component\HttpFoundation\Response
     */
    private function getDirectory(
        Request $request,
        Response $response,
        $requested_path,
        $responseFormat,
        array $validRdfFormats
    ) {
    

        $index = array_search($responseFormat, array_column($validRdfFormats, 'format'));
        if ($index !== false) {
            $responseMimeType = $validRdfFormats[$index]['mimeType'];
        }

        $content = '';
        $options = [];
        if ($responseFormat == 'jsonld' && $this->useCompactJsonLd($request->headers->get("Accept"))) {
            $options = [
                "compact" => true,
                "context" => (object) [
                    'dcterms' => self::DCTERMS_NS,
                    'ldp' => self::LDP_NS,
                    'xsd' => 'http://www.w3.org/2001/XMLSchema#',
                    'id' => '@id',
                    'type' => '@type',
                    'modified' => (object) [
                        '@id' => 'dcterms:modified',
                        '@type' => 'xsd:dateTime'
                    ],
                    'contains' => (object) [
                        '@id' => 'ldp:contains',
                        '@type' => '@id'
                    ]
                ]
            ];
        }

        $content = $this->getGraphForPath($request, $requested_path)->serialise($responseFormat, $options);

        $headers = [
            "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                       "<".self::LDP_NS."BasicContainer>; rel=\"type\""],
            "Content-Type" => $responseMimeType,
            "Content-Length" => strlen($content)
        ];

        if ($request->getMethod() == 'GET') {
            $response->setContent($content);
        }
        $response->headers->add($headers);
        return $response;
    }

    /**
     * Simple Convulsive Etag generator for files
     *
     * @return array
     */
    private function provideEtag()
    {
        $etag = function () {
            // closure preserves scope
            $this->eTag = sha1($this->modifiedTime->format("YmdHis") . $this->contentLength);
            return $this->eTag;
        };
        return $this->eTag ? $this->eTag : $etag();
    }

    /**
     * @param $accept
     *   The accept header
     * @return true if the request is for compact json-ld; false otherwise
     */
    private function useCompactJsonLd($accept)
    {
        foreach (explode(",", $accept) as $a) {
            $parts = explode(';', $a);
            if (trim($parts[0]) == "application/ld+json") {
                for ($i = 1; $i < count($parts); $i++) {
                    $params = explode("=", $parts[$i]);
                    if (trim($params[0]) == "profile" && count($params) == 2) {
                        if (strpos($params[1], "http://www.w3.org/ns/json-ld#compacted") !== false) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
}
