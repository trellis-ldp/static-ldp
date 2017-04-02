<?php

namespace Trellis\StaticLdp\Controller;

use Silex\Api\ControllerProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResourceController implements ControllerProviderInterface
{

  /**
   * {@inheritdoc}
   */
  public function connect(Application $app)
  {
    $controllers = $app['controllers_factory'];
    //
    // Define routing referring to controller services
    //

    // Options
    $controllers->options("/{path}", "staticldp.resourcecontroller:options")
      ->assert('path', '.+')
      ->value('path', '')
      ->bind('staticldp.serverOptions');

    // Generic GET.
    $controllers->match("/{path}", "staticldp.resourcecontroller:getOrHead")
      ->method('HEAD|GET')
      ->assert('path', '.+')
      ->value('path', '')
      ->bind('staticldp.resourceGetOrHead');




    return $controllers;
  }

  /**
   * Perform the GET or HEAD request.
   *
   * @param \Silex\Application $app
   *   The Silex application.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   * @param $path
   *   The path parameter from the request.
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function getOrHead(Application $app, Request $request, $path)
  {

    $valid_types = [
      "text/turtle" => "turtle",
      "application/ld+json" => "jsonld",
      "application/n-triples" => "ntriples"];

    $format = "text/turtle";

    if ($request->headers->has('accept') && array_key_exists($request->headers->get('accept'), $valid_types)) {
      $format = $request->headers->get('accept');
    }

    $docroot = $app['config']['sourceDirectory'];
    if (!empty($path)) {
      $path = "/{$path}";
    }

    $requested_path = "{$docroot}{$path}";
    if (!file_exists($requested_path)) {
      return new Response("Not Found", 404);
    }

    // It is a file.
    if (is_file($requested_path)) {
      $mimeType = mime_content_type($requested_path);
      $headers = [
        "Link" => "<http://www.w3.org/ns/ldp#NonRDFSource>; rel=\"type\"",
        "Content-Type" => $mimeType,
        "Content-Length" => filesize($requested_path),
      ];
      // Only read if we are going to use it.
      if ($request->getMethod() == 'GET') {
        // Probably best to stream the data out.
        // http://silex.sensiolabs.org/doc/2.0/usage.html#streaming
        $content = file_get_contents($requested_path);
      }
    } else {
      // Else we assume it is a directory.
      $namespaces = new \EasyRdf_Namespace();
      $namespaces->set("ldp", "http://www.w3.org/ns/ldp#");

      $graph = new \EasyRdf_Graph();

      $subject = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
      $predicate = "http://www.w3.org/ns/ldp#contains";

      $headers = [
        "Link" => "<http://www.w3.org/ns/ldp#BasicContainer>; rel=\"type\"",
        "Content-Type" => $format,
      ];

      foreach (scandir($requested_path) as $filename) {
        if (substr($filename, 0, 1) !== ".") {
          $graph->addResource($subject, $predicate, $subject . (substr($subject, -1) != '/' ? '/' : '') . $filename);
        }
      }
      $content = $graph->serialise($valid_types[$format]);
      $headers["Content-Length"] = strlen($content);
    }

    if ($request->getMethod() == "HEAD") {
      $content = "";
    }
    return new Response($content, 200, $headers);
  }

  /**
   * Response to a generic options request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function options()
  {
    $headers = [
      "Allow" => "OPTIONS, GET, HEAD",
    ];
    return new Response('', 200, $headers);
  }
}
