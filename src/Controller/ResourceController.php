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
	
	// Closesure  makes sure that all paths look the same
  // Normally we would normalize adding one Slash at the end
  // but we need to match filestructures here.
	$normalizepath = function ($path, Request $request) use ($app) {
            return rtrim($path, '/');
        };

    // Apply same middleware to all Methods on this controller collection
    // @TODO: clean of dots the route. Of matching files with extensions
    // becomes impossible. Related to _format parameter-
    $controllers = $app['controllers_factory']
      ->assert('path', '.+')
      ->value('path', '')
      ->convert('path', $normalizepath);

    // Define routing referring to controller services
	
    // Options
    $controllers->options("/{path}", "staticldp.resourcecontroller:options")
      ->bind('staticldp.serverOptions');

    // Generic GET.
    $controllers->match("/{path}", "staticldp.resourcecontroller:getOrHead")
      ->method('HEAD|GET')
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

    $filename = NULL;

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
      $filename = explode("/", $requested_path);
      $filename = end($filename);
      $mimeType = mime_content_type($requested_path);
      $headers = [
        "Link" => "<http://www.w3.org/ns/ldp#NonRDFSource>; rel=\"type\"",
        "Content-Type" => $mimeType,
        "Content-Length" => filesize($requested_path),
        "Content-Disposition" => "attachment; filename=\"{$filename}\"", 
      ];
      // Only read if we are going to use it.
      if ($request->getMethod() == 'GET') {
     
        $stream = function () use ($requested_path) {
        readfile($requested_path);
        
        };
        // @TODO not sure if streaming and Etags are friends
        // sha1 can be used incrementally, need to research.
        // Should cache the ETag in a dot file?
        return $app->stream($stream, 200, $headers)
          ->setEtag(sha1_file($requested_path),false)
          ->setLastModified(\DateTime::createFromFormat('U', filemtime($requested_path)));
      }
    } else {
      // Else we assume it is a directory.
      $namespaces = new \EasyRdf_Namespace();
      $namespaces->set("ldp", "http://www.w3.org/ns/ldp#");

      $graph = new \EasyRdf_Graph();

      $subject = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath() . $path;
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
      $response = new Response($content, 200, $headers);
    }

    if ($request->getMethod() == "HEAD") {
      $content = "";
      $response = new Response($content, 200, $headers);
      if ($filename) {
        // This makes HEAD slow. We should cache our ETag
        $response = $response->setEtag(sha1_file($requested_path), false)
          ->setLastModified(\DateTime::createFromFormat('U', filemtime($requested_path)));
      }
    }
    return $response;
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
