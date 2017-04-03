<?php

namespace Trellis\StaticLdp\Controller;

use Silex\Api\ControllerProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResourceController implements ControllerProviderInterface
{
    private $LDP_NS = "http://www.w3.org/ns/ldp#";
    private $RDF_NS = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
    private $DCTERMS_NS = "http://purl.org/dc/terms/";

    /**
     * {@inheritdoc}
     */
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];
        //
        // Define routing referring to controller services
        //

        // Options
        $controllers->options("/{path}", "staticldp.resourcecontroller:options")
            ->assert('path', '.+')
            ->value('path', '')
            ->bind('staticldp.serverOptions');

        // Generic GET.
        $controllers->match("/{path}", "staticldp.resourcecontroller:getOrHead")
            ->method('HEAD|GET')
            ->assert('path', '.+')
            ->value('path', '')
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

        // It is a file.
        if (is_file($requested_path)) {
            $response = $this->getFile(
                $app,
                $request,
                $requested_path,
                $responseFormat,
                $app['config']['validRdfFormats'],
                $request->getMethod() == 'GET'
            );
        } elseif (strpos($request->headers->get('Accept'), "text/html") !== false) {
            // Assume it is a directory
            $response = $this->getDirectoryHTML(
                $app,
                $request,
                $requested_path,
                $request->getMethod() == 'GET'
            );
        } else {
            // We assume it's a directory.
            $response = $this->getDirectory(
                $request,
                $requested_path,
                $responseFormat,
                $app['config']['validRdfFormats'],
                $request->getMethod() == 'GET'
            );
        }
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
     * @param $path
     *   Path to file we are serving.
     * @param $responseFormat
     *   The format to respond in, if it is a RDFSource.
     * @param array $validRdfFormats
     *   The configured validRdfFormats.
     * @param boolean $doGet
     *   Whether we are doing a GET or HEAD request.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function getFile(
        Application $app,
        Request $request,
        $path,
        $responseFormat,
        array $validRdfFormats,
        $doGet = false
    ) {
        $headers = [];
        // Plain might be RDF, check the file extension.
        $dirChunks = explode(DIRECTORY_SEPARATOR, $path);
        $filename = array_pop($dirChunks);
        $filenameChunks = explode('.', $filename);
        $modifiedTime = new \DateTime(date('c', filemtime($path)));
        $extension = array_pop($filenameChunks);
        $index = array_search($extension, array_column($validRdfFormats, 'extension'));
        if ($index !== false) {
            // This is a RDF file.
            $inputFormat = $validRdfFormats[$index]['format'];

            // Converting RDF from something to something else.
            $graph = new \EasyRdf_Graph();
            $graph->parseFile($path, $inputFormat, $request->getUri());
            $content = $graph->serialise($responseFormat);

            $headers = [
                "Last-Modified" => $modifiedTime->format(\DateTime::W3c),
                "Link" => [
                    "<{$this->LDP_NS}Resource>; rel=\"type\"",
                    "<{$this->LDP_NS}RDFSource>; rel=\"type\""
                ],
                "Vary" => "Accept",
                "Content-Length" => strlen($content)
            ];

            $index = array_search($responseFormat, array_column($validRdfFormats, 'format'));
            if ($index !== false) {
                $headers['Content-Type'] = $validRdfFormats[$index]['mimeType'];
            }
        } else {
            // This is not a RDF file.
            $contentLength = filesize($path);
            $responseMimeType = mime_content_type($path);
            $headers = [
                "Last-Modified" => $modifiedTime->format(\DateTime::W3C),
                "Content-Type" => $responseMimeType,
                "Link" => ["<{$this->LDP_NS}Resource>; rel=\"type\"",
                           "<{$this->LDP_NS}NonRDFSource>; rel=\"type\""],
                "Content-Length" => $contentLength,
            ];

            if ($doGet) {
                $stream = function () use ($path) {
                    readfile($path);
                };

                return $app->stream($stream, 200, $headers);
            }
        }
        if (!$doGet) {
            $content = '';
        }
        return new Response($content, 200, $headers);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     * @param $path
     *   Path to file we are serving.
     * @return \EasyRDF_Graph
     */
    private function getGraphForPath(Request $request, $path)
    {
        $subject = $request->getUri();
        $predicate = $this->LDP_NS . "contains";
        $modifiedTime = new \DateTime(date('c', filemtime($path)));

        $namespaces = new \EasyRdf_Namespace();
        $namespaces->set("ldp", $this->LDP_NS);
        $namespaces->set("dc", $this->DCTERMS_NS);

        $graph = new \EasyRdf_Graph();
        $graph->addLiteral($subject, $this->DCTERMS_NS . "modified", $modifiedTime);
        $graph->addResource($subject, $this->RDF_NS . "type", $this->LDP_NS . "Resource");
        $graph->addResource($subject, $this->RDF_NS . "type", $this->LDP_NS . "BasicContainer");

        foreach (new \DirectoryIterator($path) as $fileInfo) {
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
     * @param $path
     *   Path to file we are serving.
     * @param boolean $doGet
     *   Whether we are doing a GET or HEAD request.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function getDirectoryHTML(Application $app, Request $request, $path, $doGet = false)
    {
        $modifiedTime = new \DateTime(date('c', filemtime($path)));
        $headers = [
            "Last-Modified" => $modifiedTime->format(\DateTime::W3C),
            "Link" => ["<{$this->LDP_NS}Resource>; rel=\"type\"",
                       "<{$this->LDP_NS}BasicContainer>; rel=\"type\""],
            "Vary" => "Accept",
            "Content-Type" => "text/html"
        ];

        $options = [
            "compact" => true,
            "context" => (object) [
                'id' => '@id',
                'type' => '@type',
                'modified' => (object) [
                    '@id' => $this->DCTERMS_NS . 'modified',
                    '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime'
                ],
                'contains' => (object) [
                    '@id' => $this->LDP_NS . 'contains',
                    '@type' => '@id'
                ]
            ]
        ];

        $jsonld = $this->getGraphForPath($request, $path)->serialise("jsonld", $options);
        $template = $app['config']['template'];

        return $app['twig']->render($template, json_decode($jsonld, true));
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     * @param $path
     *   Path to file we are serving.
     * @param $responseFormat
     *   The format to respond in, if it is a RDFSource.
     * @param array $validRdfFormats
     *   The configured validRdfFormats.
     * @param boolean $doGet
     *   Whether we are doing a GET or HEAD request.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function getDirectory(Request $request, $path, $responseFormat, array $validRdfFormats, $doGet = false)
    {
        $modifiedTime = new \DateTime(date('c', filemtime($path)));

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
                    'dcterms' => $this->DCTERMS_NS,
                    'ldp' => $this->LDP_NS,
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

        $content = $this->getGraphForPath($request, $path)->serialise($responseFormat, $options);

        $headers = [
            "Last-Modified" => $modifiedTime->format(\DateTime::W3C),
            "Link" => ["<{$this->LDP_NS}Resource>; rel=\"type\"",
                       "<{$this->LDP_NS}BasicContainer>; rel=\"type\""],
            "Vary" => "Accept",
            "Content-Type" => $responseMimeType,
            "Content-Length" => strlen($content)
        ];

        if (!$doGet) {
            $content = '';
        }
        return new Response($content, 200, $headers);
    }

    /**
     * @param $accept
     *   The accept header
     * @return true if the request if for compact json-ld; false otherwise
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
