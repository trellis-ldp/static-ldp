<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A class representing an LDP NonRDFSource
 */
class NonRDFSource extends Resource
{
    /**
     * {@inheritdoc}
     */
    public function respond(Application $app, Request $request, array $options = array())
    {
        $contentDisposition = true;
        if (array_key_exists("contentDisposition", $options)) {
            $contentDisposition = $options['contentDisposition'];
        }
        $res = new Response();
        $res->setPublic();
        $res->headers->add($this->getHeaders($contentDisposition));
        $res->setLastModified(\DateTime::createFromFormat('U', filemtime($this->path)));
        $res->setEtag($this->getEtag($request->headers->get('range')));
        if (!$res->isNotModified($request)) {
            $algorithm = $this->getDigestAlgorithm($request->headers->get('want-digest'));
            switch ($algorithm) {
                case "md5":
                    $res->headers->set('Digest', "md5=" . $this->md5());
                    break;
                case "sha1":
                    $res->headers->set('Digest', "sha1=" . $this->sha1());
                    break;
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

    private function getEtag($range)
    {
        $mtime = filemtime($this->path);
        $size = filesize($this->path);
        $byteRange = $range ? $range : "";
        return sha1($mtime . $size . $byteRange);
    }
}
