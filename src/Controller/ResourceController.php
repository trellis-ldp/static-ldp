<?php

namespace Trellis\StaticLdp\Controller;


use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResourceController implements ControllerProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function connect(Application $app) {
    $controllers = $app['controllers_factory'];
    //
    // Define routing referring to controller services
    //

    $controllers->get("/{path}", "staticldp.resourcecontroller:get")
      ->assert('path','.+')
      ->value('path', '')
      ->bind('staticldp.resourceGet');

    return $controllers;
  }

  /**
   * Perform the GET request
   *
   * @param \Silex\Application $app
   *   The Silex application.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   * @param $path
   *   The path parameter from the request.
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function get(Application $app, Request $request, $path) {

    $valid_types = [
      "text/turtle" => "turtle",
      "application/ld+json" => "jsonld",
      "application/n-triples" => "ntriples"];

    $format = "text/turtle";

    if ($request->headers->has('accept') && array_key_exists($request->headers->get('accept'), $valid_types)) {
      $format = $request->headers->get('accept');
    }

    $headers = [
      "Link" => "<http://www.w3.org/ns/ldp#BasicContainer>; rel=\"type\"",
      "Content-Type" => $format,
    ];

    $graph = new \EasyRdf_Graph();

    $subject = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
    $predicate = "http://www.w3.org/ns/ldp#contains";

    $docroot = $app['config']['sourceDirectory'];
    if (!empty($path)) {
      $path = "/{$path}";
    }

    $requested_path = "{$docroot}{$path}";
    if (!file_exists($requested_path)) {
      return new Response("Not Found", 404);
    }

    foreach(scandir($requested_path) as $filename) {
      if (substr($filename, 0, 1) !== ".") {
        $graph->addResource($subject, $predicate, $subject . (substr($subject, -1) != '/' ? '/' : '')  . $filename);
      }
    }

    return new Response($graph->serialise($valid_types[$format]), 200, $headers);
  }
}