<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RDFSource extends Resource
{
    public function __construct($path, $responseType, $responseMimeType, $typeData)
    {
        $this->path = $path;
        $this->typeData = $typeData;
        if ($responseType === null || $responseMimeType === null) {
            $this->responseType = "turtle";
            $this->responseMimeType = "text/turtle";
        } else {
            $this->responseType = $responseType;
            $this->responseMimeType = $responseMimeType;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(Application $app, Request $request)
    {
        $res = new Response();
        $res->headers->add($this->getHeaders());
        $res->setLastModified(\DateTime::createFromFormat('U', filemtime($this->path)));
        $res->setEtag($this->getEtag());
        if (!$res->isNotModified($request)) {
            if ($this->canStream()) {
                $filename = $this->path;
                $stream = function () use ($filename) {
                    readfile($filename);
                };
                return $app->stream($stream, 200, $res->headers->all());
            } else {
                $graph = new \EasyRdf_Graph();
                $graph->parseFile($this->path, $this->getInputFormat(), $request->getURI());
                if ($this->responseType == "html") {
                    $data = json_decode($graph->serialise("jsonld"), true);
                    $dataset = $this->mapJsonLdForHTML($data, $app['config']['prefixes']);
                    $template = $app['config']['template'];
                    return $app['twig']->render($template, ["id" => $request->getURI(), "dataset" => $dataset]);
                } else {
                    $res->setContent($graph->serialise($this->responseType));
                }
            }
        }
        return $res;
    }

    private function getEtag()
    {
        $mtime = filemtime($this->path);
        $size = filesize($this->path);
        return sha1($mtime . $size . $this->responseType);
    }

    private function canStream()
    {
        $inputFormat = $this->getInputFormat();
        return $inputFormat === null || $inputFormat == $this->responseType;
    }

    private function getInputFormat()
    {
        $filenameChunks = explode('.', $this->path);
        $extension = array_pop($filenameChunks);
        foreach ($this->typeData as $type) {
            if ($type['extension'] == $extension) {
                return $type['format'];
            }
        }
        return null;
    }

    private function getHeaders()
    {
        return [
            "Content-Type" => $this->responseMimeType,
            "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                       "<".self::LDP_NS."RDFSource>; rel=\"type\""],
            "Content-Length" => filesize($this->path),
            "Vary" => "Accept"
        ];
    }
}
