# static-ldp

A simple way to expose static assets as a read-only <a href="https://www.w3.org/TR/ldp/">LDP</a> server.

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg?style=flat-square)](https://php.net/)
[![Build Status](https://travis-ci.org/trellis-ldp/static-ldp.svg?branch=master)](https://travis-ci.org/trellis-ldp/static-ldp)
[![Build status](https://ci.appveyor.com/api/projects/status/pad3wdgd43lg1nou?svg=true)](https://ci.appveyor.com/project/acoburn/static-ldp)

## Requirements

* PHP 5.6+
* [Composer](https://getcomposer.org/)
* The <a href="http://php.net/manual/en/book.mbstring.php">mbstring</a> extension
* The <a href="http://php.net/manual/en/book.pcre.php">pcre</a> extension

## Installation

To install `static-ldp`, follow these steps:

1. run `composer create-project trellis-ldp/static-ldp`
2. create a `./static-ldp/config/settings.yml` file like this:

```
sourceDirectory: /path/to/data/directory
```

## Basics

By installing `static-ldp` and configuring the `sourceDirectory` to point
to the location of your static resources, you have a simple, read-only linked data server.

## LDP Resources

Individual files are served as `ldp:NonRDFSource` resources,
and directories are served as `ldp:BasicContainer` resources.
If a static file is a RDF file, then it is served as an `ldp:RDFSource`.

## Describing an LDP NonRDFSources

It is also possible to describe `ldp:NonRDFSource` resources via the `Link: <IRI>; rel="describedby"`
header. For example, if a JPEG file is named `rosid_rosaceae.jpg`, then by adding an RDF file with
the name `rosid_rosaceae.jpg.ttl` (or any valid RDF format such as `rosid_rosaceae.jpg.jsonld`), then
requests to `rosid_rosaceae.jpg` will include a link header pointing to the `ldp:RDFSource`.
Similarly, the `ldp:RDFSource` will contain a link header (`rel="describes"`) pointing to the
`ldp:NonRDFSource`.

## Support for instance digests

All resources support instance digests as defined in <a href="https://tools.ietf.org/html/rfc3230">RFC 3230</a>.
What this means is that the response can include a `Digest` header so that it is possible to ensure end-to-end
data integrity. Requests that include the header: `Want-Digest: md5` or `Want-Digest: sha1` will include responses
that contain a digest corresponding to the on-disk resource.

Only `md5` and `sha1` algorithms are supported; other algorithms will be ignored. It should be noted that, for large
files, `Want-Digest` requests may perform considerably slower, as the digest will need to be computed before a
response is sent.

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

    extraPropertiesFilename: properties

For each directory that becomes a `ldp:BasicContainer` resource, an RDF file within that directory
with this name will have its contents added to the RDF presented as a response for that resource
(instead of becoming a child resource in its own right). This provides a means by which to add
user-controlled properties to `ldp:BasicContainer`s.

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
`jsonld`) must be an RDF serialization format supported by <a href="http://www.easyrdf.org/">EasyRdf</a>.

    prefixes:
        dc: "http://purl.org/dc/terms/"
        rdf: "http://www.w3.org/1999/02/22-rdf-syntax-ns#"
        ...

The default HTML display template will present IRIs in short (prefixed) form if those
prefixes are registered. By default a number of common prefixes are included, but
any prefix may be registered here.

