<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NonRDFSource extends Resource
{
    public function __construct($path)
    {
        $this->path = $path;
    }

    public function get(Application $app, Request $request)
    {
        $filename = $this->path;
        $stream = function () use ($filename) {
            readfile($filename);
        };
        $res = new Response();
        $res->headers->add($this->getHeaders());
        $res->setLastModified(\DateTime::createFromFormat('U', filemtime($this->path)));
        $res->setEtag($this->getEtag());
        if (!$res->isNotModified($request)) {
            return $app->stream($stream, 200, $res->headers->all());
        } else {
            return $res;
        }
    }

    private function getHeaders()
    {
        $filename = basename($this->path);
        return [
            "Content-Type" => mime_content_type($this->path),
            "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                       "<".self::LDP_NS."NonRDFSource>; rel=\"type\""],
            "Content-Length" => filesize($this->path),
            "Content-Disposition" => "attachment; filename=\"{$filename}\""
        ];
    }

    private function getEtag()
    {
        $mtime = filemtime($this->path);
        $size = filesize($this->path);
        return sha1($mtime . $size);
    }
}
