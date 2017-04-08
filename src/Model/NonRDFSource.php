<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NonRDFSource extends Resource
{
    /**
     * {@inheritdoc}
     */
    public function respond(Application $app, Request $request, $options = array())
    {
        $contentDisposition = true;
        if (array_key_exists("contentDisposition", $options)) {
            $contentDisposition = $options['contentDisposition'];
        }
        $res = new Response();
        $res->headers->add($this->getHeaders($contentDisposition));
        $res->setLastModified(\DateTime::createFromFormat('U', filemtime($this->path)));
        $res->setEtag($this->getEtag());
        if (!$res->isNotModified($request)) {
            $digest = $this->wantDigest($request->headers->get('want-digest'));
            if ($digest) {
                $res->headers->set('Digest', $digest);
            }
            foreach ($this->formats as $type) {
                $description = $this->path . "." . $type['extension'];
                if (file_exists($description)) {
                    $link = "<" . $request->getUri().".".$type['extension'].">; rel=\"describedby\"";
                    $res->headers->set("Link", $link, false);
                }
            }
            $filename = $this->path;
            $stream = function () use ($filename) {
                readfile($filename);
            };
            return $app->stream($stream, 200, $res->headers->all());
        }
        return $res;
    }

    private function getHeaders($contentDisposition)
    {
        $headers = [
            "Content-Type" => mime_content_type($this->path),
            "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                       "<".self::LDP_NS."NonRDFSource>; rel=\"type\""],
            "Content-Length" => filesize($this->path),
        ];
        if ($contentDisposition) {
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
