<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RDFSource extends Resource
{
    /**
     * {@inheritdoc}
     */
    public function respond(Application $app, Request $request, array $options = array())
    {
        $responseMimeType = $this->getResponseMimeType($request);
        $responseFormat = $this->getResponseFormat($request);

        $res = new Response();
        $res->headers->add($this->getHeaders($responseMimeType));
        $res->setLastModified(\DateTime::createFromFormat('U', filemtime($this->path)));
        $res->setEtag($this->getEtag($responseFormat, $request->headers->get('range')));
        if (!$res->isNotModified($request)) {
            $extParts = explode(".", $this->path);
            if (count($extParts) > 1) {
                $ext = array_pop($extParts);
                $described = implode(".", $extParts);
                if (file_exists(implode(".", $extParts))) {
                    $uri = $request->getUri();
                    $link = "<" . substr($uri, 0, strlen($uri) - strlen($ext) - 1) . ">; rel=\"describes\"";
                    $res->headers->set("Link", $link, false);
                }
            }

            if ($this->canStream()) {
                $digest = $this->wantDigest($request->headers->get('want-digest'));
                if ($digest) {
                    $res->headers->set('Digest', $digest);
                }
                $filename = $this->path;
                $stream = function () use ($filename) {
                    readfile($filename);
                };
                return $app->stream($stream, 200, $res->headers->all());
            } else {
                $graph = new \EasyRdf_Graph();
                $graph->parseFile($this->path, $this->getInputFormat(), $request->getURI());
                if ($responseFormat == "html") {
                    $data = json_decode($graph->serialise("jsonld"), true);
                    $dataset = $this->mapJsonLdForHTML($data, $app['config']['prefixes']);
                    $template = $app['config']['template'];
                    return $app['twig']->render($template, ["id" => $request->getURI(), "dataset" => $dataset]);
                } else {
                    $res->setContent($graph->serialise($responseFormat));
                }
            }
        }
        return $res;
    }

    private function getEtag($responseFormat, $range)
    {
        $mtime = filemtime($this->path);
        $size = filesize($this->path);
        $byteRange = $range ? $range : "";
        return sha1($mtime . $size . $responseFormat . $range);
    }

    private function canStream($responseFormat)
    {
        $inputFormat = $this->getInputFormat();
        return $inputFormat === null || $inputFormat == $responseFormat;
    }

    private function getInputFormat()
    {
        $filenameChunks = explode('.', $this->path);
        $extension = array_pop($filenameChunks);
        foreach ($this->formats as $type) {
            if ($type['extension'] == $extension) {
                return $type['format'];
            }
        }
        return null;
    }

    private function getHeaders($responseMimeType)
    {
        return [
            "Content-Type" => $responseMimeType,
            "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                       "<".self::LDP_NS."RDFSource>; rel=\"type\""],
            "Content-Length" => filesize($this->path),
            "Vary" => "Accept"
        ];
    }
}
