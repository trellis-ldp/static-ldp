<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicContainer extends Resource
{
    public function __construct($path, $responseType, $responseMimeType)
    {
        $this->path = $path;
        if ($responseType === null || $responseMimeType === null) {
            $this->responseType = "turtle";
            $this->responseMimeType = "text/turtle";
        } else {
            $this->responseType = $responseType;
            $this->responseMimeType = $responseMimeType;
        }
    }

    public function get(Application $app, Request $request)
    {
        $modifiedTime = \DateTime::createFromFormat('U', filemtime($this->path));
        $headers = [
            "Content-Type" => $this->responseMimeType,
            "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                       "<".self::LDP_NS."BasicContainer>; rel=\"type\""],
            "Vary" => "Accept"
        ];

        $res = new Response();
        $res->headers->add($headers);
        $res->setLastModified($modifiedTime);
        $res->setEtag($this->getEtag());

        if (!$res->isNotModified($request)) {
            $subject = $request->getUri();
            $predicate = self::LDP_NS . "contains";

            $namespaces = new \EasyRdf_Namespace();
            $namespaces->set("ldp", self::LDP_NS);
            $namespaces->set("dc", self::DCTERMS_NS);

            $graph = new \EasyRdf_Graph();
            $graph->addLiteral($subject, self::DCTERMS_NS . "modified", $modifiedTime);
            $graph->addResource($subject, self::RDF_NS . "type", self::LDP_NS . "Resource");
            $graph->addResource($subject, self::RDF_NS . "type", self::LDP_NS . "BasicContainer");

            foreach (new \DirectoryIterator($this->path) as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }
                $filename = rtrim($subject, '/') . '/' . ltrim($fileInfo->getFilename(), '/');
                $graph->addResource($subject, $predicate, $filename);
            }

            $accept = $request->headers->get('accept');
            if ($this->responseType == "jsonld") {
                $content = $graph->serialise($this->responseType, $this->getSerialisationOptions($accept));
            } elseif ($this->responseType == "html") {
                $options = [
                    "compact" => true,
                    "context" => (object) [
                        'id' => '@id',
                        'type' => '@type',
                        'modified' => (object) [
                            '@id' => self::DCTERMS_NS . 'modified',
                            '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime'
                        ],
                        'contains' => (object) [
                            '@id' => self::LDP_NS . 'contains',
                            '@type' => '@id'
                        ]
                    ]
                ];

                $content = $graph->serialise("jsonld", $options);
                $template = $app['config']['template'];
                return $app['twig']->render($template, json_decode($content, true));
            } else {
                $content = $graph->serialise($this->responseType);
            }

            $res->setContent($content);
        }

        return $res;
    }

    private function getEtag()
    {
        $mtime = filemtime($this->path);
        return sha1($mtime . $this->path . $this->responseType);
    }

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

    private function getSerialisationOptions($accept)
    {
        if ($this->useCompactJsonLd($accept)) {
            return [
                "compact" => true,
                "context" => (object) [
                    'dcterms' => self::DCTERMS_NS,
                    'ldp' => self::LDP_NS,
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
        return [];
    }
}
