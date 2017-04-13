<?php

namespace Trellis\StaticLdp\Model;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * A class representing an LDP Resource
 */
abstract class Resource
{
    const LDP_NS = "http://www.w3.org/ns/ldp#";
    const RDF_NS = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
    const DCTERMS_NS = "http://purl.org/dc/terms/";

    /**
     * Create a LDP Resource
     * @param $path
     *    the file path to the resource
     * @param $formats array
     *    the supported RDF formats
     */
    public function __construct($path, $formats)
    {
        $this->path = $path;
        $this->formats = $formats;
    }

    /**
     * Get a representation of the given resource
     *
     * @param \Silex\Application $app
     *   The Silex application.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request
     * @param array $options
     *   Optional parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    abstract public function respond(Application $app, Request $request, array $options);

    /**
     * Compute the SHA1 checksum of a file
     * @return string
     *    The SHA1 checksum of the file
     */
    public function sha1($content = null)
    {
        if ($content !== null) {
            return sha1($content);
        } elseif (is_file($this->path)) {
            return sha1_file($this->path);
        }
        return null;
    }

    /**
     * Compute the MD5 checksum of a file
     * @return string
     *    The MD5 checksum of the file
     */
    public function md5($content = null)
    {
        if ($content !== null) {
            return md5($content);
        } elseif (is_file($this->path)) {
            return md5_file($this->path);
        }
        return null;
    }

    /**
     * Given a want-digest header, return the applicable algorithm
     *
     * @param $wantDigestHeader
     *      The Want-Digest header
     * @return string
     *      The algorithm name, if any
     */
    protected function getDigestAlgorithm($wantDigestHeader)
    {
        $validAlgorithms = ["md5", "sha1"];
        $bestAlgorithm = null;
        if ($wantDigestHeader) {
            $maxQVal = 0.0;
            foreach (explode(",", $wantDigestHeader) as $algorithm) {
                $parts = explode(";", $algorithm);
                $qVal = 1.0;
                if (count($parts) > 1 && strpos($parts[1], "q=") === 0) {
                    $qVal = floatval(trim(substr($parts[1], 2)));
                }
                $alg = strtolower(trim($parts[0]));
                if ($qVal > $maxQVal && in_array($alg, $validAlgorithms)) {
                    $bestAlgorithm = $alg;
                    $maxQVal = $qVal;
                }
            }
        }
        return $bestAlgorithm;
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

    /**
     * Find the valid RDF format
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     *
     * @return string
     *   EasyRdf "format" or null if not supported.
     */
    protected function getResponseFormat(Request $request)
    {
        if ($request->headers->has('accept')) {
            $accept = $request->getAcceptableContentTypes();
            foreach ($accept as $item) {
                foreach ($this->formats as $format => $data) {
                    if ($data['mimeType'] === $item) {
                        return $format;
                    }
                }
                if (strpos($item, "text/html") !== false) {
                    return "html";
                }
            }
        }
        return "turtle";
    }

    /**
     * Find the mimeType for the request
     *
     * @param array $validRdfFormats
     *   Supported formats from the config.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     *
     * @return string
     *   MimeType or null if not defined.
     */
    protected function getResponseMimeType(Request $request)
    {
        if ($request->headers->has('accept')) {
            $accept = $request->getAcceptableContentTypes();
            foreach ($accept as $item) {
                foreach ($this->formats as $format => $data) {
                    if ($data['mimeType'] === $item) {
                        return $item;
                    }
                }
                if (strpos($item, "text/html") !== false) {
                    return "text/html";
                }
            }
        }
        return "text/turtle";
    }
    
    protected function getInputFormat($path)
    {
        $filenameChunks = explode('.', $path);
        $extension = array_pop($filenameChunks);
        foreach ($this->formats as $format => $data) {
            if ($data ['extension'] == $extension) {
                return $format;
            }
        }
        return null;
    }
}
