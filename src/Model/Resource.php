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
     * Create a LDP Resource
     * @param $path
     *    the file path to the resource
     */
    protected function __construct($path)
    {
        $this->path = $path;
    }

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
     * Compute the SHA1 checksum of a file
     * @return string
     *    The SHA1 checksum of the file
     */
    public function sha1()
    {
        if (is_file($this->path)) {
            return sha1_file($this->path);
        }
        return null;
    }

    /**
     * Compute the MD5 checksum of a file
     * @return string
     *    The MD5 checksum of the file
     */
    public function md5()
    {
        if (is_file($this->path)) {
            return md5_file($this->path);
        }
        return null;
    }

    /**
     * Verify Expect header
     * @return boolean
     *    True if the expectation succeeds, false otherwise
     */
    public function verifyDigest($expect)
    {
        // 202-digest; md5=abcdef0987654321==
        $parts = explode(";", $expect, 2);
        if (strtolower($parts[0]) == "202-digest") {
            if (count($parts) == 2) {
                $expectation = explode("=", trim($parts[1]), 2);
                if (count($expectation) == 2) {
                    $algorithm = trim(strtolower($expectation[0]));
                    $digest = trim(strtolower($expectation[1]));
                    switch($algorithm) {
                        case "md5":
                            return $this->md5() == $digest;
                        case "sha1":
                            return $this->sha1() == $digest;
                    }
                }
            }
            return false;
        }
        return true;
    }

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
