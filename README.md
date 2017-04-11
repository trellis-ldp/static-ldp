# static-ldp

A simple way to expose static assets as a read-only LDP server.

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg?style=flat-square)](https://php.net/)
[![Build Status](https://travis-ci.org/trellis-ldp/static-ldp.svg?branch=master)](https://travis-ci.org/trellis-ldp/static-ldp)

## Requirements

* PHP 5.6+
* [Composer](https://getcomposer.org/)

## Basics

Clone this repository and setup your Apache to use it as
document root. Then configure the `sourceDirectory` to point
to the root directory of your static resources.

Individual files are served as `ldp:NonRDFSource` resources,
and directories are served as `ldp:BasicContainer` resources.
If a static file is a RDF file, then it is served as an `ldp:RDFSource`.

It is also possible to describe `ldp:NonRDFSource` resources via the `Link: <IRI>; rel="describedby"`
header. For example, if a JPEG file is named `rosid_rosaceae.jpg`, then by adding an RDF file with
the name `rosid_rosaceae.jpg.ttl` (or any valid RDF format such as `rosid_rosaceae.jpg.jsonld`), then
requests to `rosid_rosaceae.jpg` will include a link header pointing to the `ldp:RDFSource`.
Similarly, the `ldp:RDFSource` will contain a link header (`rel="describes"`) pointing to the
`ldp:NonRDFSource`.

## Installation

To install `static-ldp`, follow these steps:

1. clone this repository into a location on a webserver
2. run `composer install`
3. create a `./config/settings.yml` file like this:

    sourceDirectory: /path/to/data/directory

## Configuration

There are many configuration options available. Only the `sourceDirectory` _must_ be defined.
Other options include:

    template: default.twig

If you wish to override the HTML template with one of your own design, you can change this
value to point to a different location. Alternately, you can edit the `default.twig` file
in the `templates` directory. Though if you plan to customize the template, it is recommended
that you use a separate file.

    defaultRdfFormat: turtle

For requests without an `Accept` header, this is the RDF format used in responses (for
`ldp:RDFSource` and `ldp:BasicContainer` resources).

    contentDisposition: false

For `ldp:NonRDFSource` resources, this controls whether to include a `Content-Disposition`
header in responses.

    validRdfFormats:
        turtle:
            mimeType: text/turtle
            extension: ttl
        jsonld:
            mimeType: application/ld+json
            extension: jsonld
        ntriples:
            mimeType: application/n-triples
            extension: nt

Generally speaking, the RDF formats should not be changed unless there is a need to
support a serialization that is not included here. The RDF format (e.g. `turtle`,
`jsonld`) must be an RDF serialization format supported by EasyRdf.

    prefixes:
        dc: "http://purl.org/dc/terms/"
        rdf: "http://www.w3.org/1999/02/22-rdf-syntax-ns#"
        ...

The default HTML display template will present IRIs in short (prefixed) form if those
prefixes are registered. By default a number of common prefixes are included, but
any prefix may be registered here.

