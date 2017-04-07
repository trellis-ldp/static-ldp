<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NonRDFSource extends Resource
{
    public function __construct($path, $contentDisposition = true)
    {
        $this->path = $path;
        $this->contentDisposition = $contentDisposition;
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
        $headers = [
            "Content-Type" => mime_content_type($this->path),
            "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                       "<".self::LDP_NS."NonRDFSource>; rel=\"type\""],
            "Content-Length" => filesize($this->path),
        ];
        if ($this->contentDisposition) {
            $filename = basename($this->path);
            $headers['Content-Disposition'] = "attachment; filename=\"{$filename}\"";
        }
        return $headers;
    }

    private function getEtag()
    {
        $mtime = filemtime($this->path);
        $size = filesize($this->path);
        return sha1($mtime . $size);
    }
}
