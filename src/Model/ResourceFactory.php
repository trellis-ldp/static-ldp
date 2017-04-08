<?php

namespace Trellis\StaticLdp\Model;

class ResourceFactory
{
    public static function create($path, $formats)
    {
        if (is_file($path)) {
            $filenameChunks = explode('.', $path);
            $extension = array_pop($filenameChunks);
            if (array_search($extension, array_column($formats, 'extension')) !== false) {
                // It is a RDF file
                return new RDFSource($path, $formats);
            } else {
                return new NonRDFSource($path, $formats);
            }
        }
        return new BasicContainer($path, $formats);
    }
}
