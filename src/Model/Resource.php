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
     * Apply a want-digest header, if applicable
     *
     * @param $wantDigestHeader
     *      The Want-Digest header
     * @return string
     *      The instance digest, if relevant
     */
    protected function wantDigest($wantDigestHeader)
    {
        if ($wantDigestHeader) {
            $maxQVal = 0.0;
            $bestAlgorithm = null;
            foreach (explode(",", $wantDigestHeader) as $algorithm) {
                $parts = explode(";", $algorithm);
                $qVal = 1.0;
                if (count($parts) > 1 && strpos($parts[1], "q=") == 0) {
                    $qVal = floatval(trim(substr($parts[1], 2)));
                }
                $alg = strtolower(trim($parts[0]));
                if ($qVal > $maxQVal && in_array($alg, ["md5", "sha1"])) {
                    $bestAlgorithm = $alg;
                    $maxQVal = $qVal;
                }
            }
            switch($bestAlgorithm) {
                case "md5":
                    return "md5=" . $this->md5();
                case "sha1":
                    return "sha1=" . $this->sha1();
            }
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
