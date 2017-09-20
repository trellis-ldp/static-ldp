<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A class representing an LDP BasicContainer
 */
class BasicContainer extends Resource
{
    /**
     * {@inheritdoc}
     */
    public function respond(Application $app, Request $request, array $options = array())
    {
        $responseMimeType = $this->getResponseMimeType($request);
        $responseFormat = $this->getResponseFormat($request);

        $modifiedTime = \DateTime::createFromFormat('U', filemtime($this->path));
        $headers = [
            "Content-Type" => $responseMimeType,
            "Link" => ["<".self::LDP_NS."Resource>; rel=\"type\"",
                       "<".self::LDP_NS."BasicContainer>; rel=\"type\""],
            "Vary" => "Accept"
        ];

        $res = new Response();
        $res->setPublic();
        $res->headers->add($headers);
        $res->setLastModified($modifiedTime);
        $res->setEtag($this->getEtag($responseFormat, $request->headers->get('range')));

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

            $extraPropsFilename = $app['config']['extraPropertiesFilename'];
            foreach (new \DirectoryIterator($this->path) as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }
                $name = $fileInfo->getBasename("." . $fileInfo->getExtension());
                # load extra properties for this container if available or add a containment triple
                if ($name == $extraPropsFilename) {
                       $rdf = $fileInfo->openFile()->fread($fileInfo->getSize());
                       $format = $this->getInputFormat($fileInfo->getFilename());
                       $graph->parse($rdf, $format, $subject);
                } else {
                    $filename = rtrim($subject, '/') . '/' . ltrim($fileInfo->getFilename(), '/');
                    $graph->addResource($subject, $predicate, $filename);
                }
            }

            $accept = $request->headers->get('accept');
            if ($responseFormat == "jsonld") {
                $content = $graph->serialise($responseFormat, $this->getSerialisationOptions($accept));
            } elseif ($responseFormat == "html") {
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

                $data = json_decode($graph->serialise("jsonld"), true);
                $dataset = $this->mapJsonLdForHTML($data, $app['config']['prefixes']);
                $template = $app['config']['template'];
                $content = $app['twig']->render($template, ["id" => $subject, "dataset" => $dataset]);
            } else {
                $content = $graph->serialise($responseFormat);
            }

            $res->setContent($content);
        }

        return $res;
    }

    private function getEtag($responseFormat, $range)
    {
        $mtime = filemtime($this->path);
        $byteRange = $range ? $range : "";
        return sha1($mtime . $this->path . $responseFormat . $byteRange);
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
