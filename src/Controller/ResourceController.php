<?php

namespace Trellis\StaticLdp\Controller;

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
                    "<http://www.w3.org/ns/ldp#Resource>; rel=\"type\"",
                    "<http://www.w3.org/ns/ldp#RDFSource>; rel=\"type\""
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
                "Link" => ["<http://www.w3.org/ns/ldp#Resource>; rel=\"type\"",
                           "<http://www.w3.org/ns/ldp#NonRDFSource>; rel=\"type\""],
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
        $predicate = "http://www.w3.org/ns/ldp#contains";
        $modifiedTime = new \DateTime(date('c', filemtime($path)));

        $namespaces = new \EasyRdf_Namespace();
        $namespaces->set("ldp", "http://www.w3.org/ns/ldp#");
        $namespaces->set("dc", "http://purl.org/dc/terms/");

        $graph = new \EasyRdf_Graph();
        $graph->addLiteral($subject, "http://purl.org/dc/terms/modified", $modifiedTime);

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
            "Link" => ["<http://www.w3.org/ns/ldp#Resource>; rel=\"type\"",
                       "<http://www.w3.org/ns/ldp#BasicContainer>; rel=\"type\""],
            "Vary" => "Accept",
            "Content-Type" => "text/html"
        ];

        $options = [
            "compact" => true,
            "context" => (object) [
                'dcterms' => 'http://purl.org/dc/terms/',
                'ldp' => 'http://www.w3.org/ns/ldp#',
                'xsd' => 'http://www.w3.org/2001/XMLSchema#',
                'id' => '@id',
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
        $headers = [
            "Last-Modified" => $modifiedTime->format(\DateTime::W3C),
            "Link" => ["<http://www.w3.org/ns/ldp#Resource>; rel=\"type\"",
                       "<http://www.w3.org/ns/ldp#BasicContainer>; rel=\"type\""],
            "Vary" => "Accept",
            "Content-Type" => $responseMimeType,
        ];

        $content = $this->getGraphForPath($request, $path)->serialise($responseFormat);
        $headers["Content-Length"] = strlen($content);
        if (!$doGet) {
            $content = '';
        }
        return new Response($content, 200, $headers);
    }
}
