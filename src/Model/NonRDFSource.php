<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NonRDFSource extends Resource
{
    public function __construct($path, $contentDisposition = true)
    {
        parent::__construct($path);
        $this->contentDisposition = $contentDisposition;
    }

    /**
     * {@inheritdoc}
     */
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
            // Apache httpd doesn't support the Expect header
            // for anything that isn't 100-continue.
            $expect = $request->headers->get('x-expect');
            $expects202 = strpos($expect, "202-digest") === 0;
            if (!$this->verifyDigest($expect)) {
                $res->setStatusCode(417);
                $res->setContent("Entity digest failed");
            } else {
                return $app->stream($stream, $expects202 ? 202 : 200, $res->headers->all());
            }
        }
        return $res;
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
