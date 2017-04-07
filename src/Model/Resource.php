<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

abstract class Resource
{
    const LDP_NS = "http://www.w3.org/ns/ldp#";
    const RDF_NS = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
    const DCTERMS_NS = "http://purl.org/dc/terms/";

    /**
     * Get a representation of the given resource
     *
     * @param \Silex\Application $app
     *   The Silex application.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    abstract public function get(Application $app, Request $request);

    /**
     * Map an expanded JSON-LD datastructure into a format
     * suitable for the HTML templating system
     *
     * @param $jsonld
     *      The expanded JSON-LD parsed into a PHP array
     * @param $prefixes
     *      User-defined prefixes to use
     * @return Array
     *      A new array suitable for the Twig templates
     */
    protected function mapJsonLdForHTML($jsonld, $prefixes)
    {
        $namespaces = new \EasyRdf_Namespace();
        foreach ($prefixes as $prefix => $uri) {
            $namespaces->set($prefix, $uri);
        }

        $data = [];
        foreach ($jsonld as $elem) {
            $graph = [];
            foreach ($elem as $key => $value) {
                if ($key == "@id") {
                    $graph['id'] = $value;
                } elseif ($key == "@type") {
                    $prop = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
                    $graph[$prop] = [
                        "label" => "rdf:type",
                        "triples" => []
                    ];
                    foreach ($value as $val) {
                        $graph[$prop]["triples"][] = [
                            "url" => $val,
                            "label" => $namespaces->shorten($val)
                        ];
                    }
                } elseif (is_array($value)) {
                    $graph[$key] = [
                        "label" => $namespaces->shorten($key),
                        "triples" => []
                    ];
                    foreach ($value as $val) {
                        if (isset($val['@id'])) {
                            $label = $namespaces->shorten($val['@id']);
                            $graph[$key]["triples"][] = [
                                "url" => $val['@id'],
                                "label" => $label ? $label : $val['@id']
                            ];
                        } elseif (isset($val['@value'])) {
                            $graph[$key]["triples"][] = [
                                "label" => $val['@value']
                            ];
                        }
                    }
                }
            }
            $data[] = $graph;
        }
        return $data;

    }
}
