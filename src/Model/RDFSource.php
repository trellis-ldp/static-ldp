<?php

namespace App\Model;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A class representing an LDP RDFSource
 */
class RDFSource extends Resource
{
    /**
     * {@inheritdoc}
     */
    public function respond(Request $request, Environment $twig_provider, array $options = array())
    {
        $responseMimeType = $this->getResponseMimeType($request);
        $responseFormat = $this->getResponseFormat($request);

        $res = new Response();
        $res->setPublic();
        $res->headers->add($this->getHeaders($responseMimeType));
        $res->setLastModified(\DateTime::createFromFormat('U', filemtime($this->path)));
        $res->setEtag($this->getEtag($responseFormat, $request->headers->get('range')));
        if (!$res->isNotModified($request)) {
            $extParts = explode(".", $this->path);
            if (count($extParts) > 1) {
                $ext = array_pop($extParts);
                if (file_exists(implode(".", $extParts))) {
                    $uri = $request->getUri();
                    $link = "<" . substr($uri, 0, strlen($uri) - strlen($ext) - 1) . ">; rel=\"describes\"";
                    $res->headers->set("Link", $link, false);
                }
            }
            switch ($this->getDigestAlgorithm($request->headers->get('want-digest'))) {
                case "md5":
                    $res->headers->set('Digest', 'md5=' . $this->md5());
                    break;
                case "sha1":
                    $res->headers->set('Digest', 'sha1=' . $this->sha1());
                    break;
            }
            if ($this->canStream($responseFormat)) {
                return new BinaryFileResponse($this->path, 200, $res->headers->all());
            } else {
                $graph = new \EasyRdf_Graph();
                $graph->parseFile($this->path, $this->getInputFormat($this->path), $request->getURI());
                if ($responseFormat == "html") {
                    $data = json_decode($graph->serialise("jsonld"), true);
                    $dataset = $this->mapJsonLdForHTML($data, $this->resourceConfig['prefixes']);
                    $template = $this->resourceConfig['template'];
                    $content = $twig_provider->render($template, ["id" => $request->getURI(), "dataset" => $dataset]);
                } else {
                    $content = $graph->serialise($responseFormat);
                }
                // Content-length needs to be corrected or responses can be cut off.
                $res->headers->set('Content-Length', strlen($content), true);
                $res->setContent($content);
            }
        }
        return $res;
    }

    private function getEtag($responseFormat, $range)
    {
        $mtime = filemtime($this->path);
        $size = filesize($this->path);
        return sha1($mtime . $size . $responseFormat . $range);
    }

    private function canStream($responseFormat)
    {
        $inputFormat = $this->getInputFormat($this->path);
        return $inputFormat === null || $inputFormat == $responseFormat;
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
