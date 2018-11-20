<?php

namespace App\Model;

class ResourceFactory
{
    public static function create($path, $formats, $resourceConfig)
    {
        if (is_file($path)) {
            $filenameChunks = explode('.', $path);
            $extension = array_pop($filenameChunks);
            if (array_search($extension, array_column($formats, 'extension')) !== false) {
                // It is a RDF file
                return new RDFSource($path, $formats, $resourceConfig);
            } else {
                return new NonRDFSource($path, $formats, $resourceConfig);
            }
        }
        return new BasicContainer($path, $formats, $resourceConfig);
    }
}
