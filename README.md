# static-ldp
A simple way to expose static HTTP assets as an LDP server.

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.5-8892BF.svg?style=flat-square)](https://php.net/)
[![Build Status](https://travis-ci.org/trellis-ldp/static-ldp.svg?branch=master)](https://travis-ci.org/trellis-ldp/static-ldp)


Clone this repository and setup your Apache to use it as
document root. Then configure the `sourceDirectory` to point
to the root directory of your static resources.

Individual files are served as `ldp:NonRDFSource` resources,
and directories are served as `ldp:BasicContainer` resources.

## Requirements

* PHP 5.6+
* [Composer](https://getcomposer.org/)

## Installation

To install `static-ldp`, follow these steps:

1. clone this repository into a location on a webserver
2. run `composer install`
3. copy `config/settings.yml.sample` to `config/settings.yml` and update any values
