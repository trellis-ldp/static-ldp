<?php

namespace Trellis\StaticLdp;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class TrellisConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('staticldp');

        $rootNode
            ->children()
                ->scalarNode('sourceDirectory')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('template')
                    ->defaultValue('default.twig')
                ->end()
                ->scalarNode('defaultRdfFormat')
                    ->defaultValue('turtle')
                ->end()
                ->booleanNode('debug')
                    ->defaultFalse()
                ->end()
                ->arrayNode('validRdfFormats')
                    ->prototype("array")
                        ->children()
                            ->scalarNode('format')->end()
                            ->scalarNode('mimeType')->end()
                            ->scalarNode('extension')->end()
                        ->end()
                    ->end()
                    ->defaultValue([[
                        "format" => "turtle",
                        "mimeType" => "text/turtle",
                        "extension" => "ttl"
                    ], [
                        "format" => "jsonld",
                        "mimeType" => "application/ld+json",
                        "extension" => "jsonld"
                    ], [
                        "format" => "ntriples",
                        "mimeType" => "application/n-triples",
                        "extension" => "nt"
                    ]])
                ->end()
                ->arrayNode('prefixes')
                    ->prototype('scalar')->end()
                    ->defaultValue([
                        "dc" => "http://purl.org/dc/elements/1.1/",
                        "dcterms" => "http://purl.org/dc/terms/",
                        "foaf" => "http://xmlns.com/foaf/0.1/",
                        "ldp" => "http://www.w3.org/ns/ldp#",
                        "prov" => "http://www.w3.org/ns/prov#",
                        "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
                        "rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
                        "skos" => "http://www.w3.org/2004/02/skos/core#"
                    ])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
