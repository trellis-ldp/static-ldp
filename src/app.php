<?php

namespace Trellis\StaticLdp;

require_once __DIR__ . '/../vendor/autoload.php';

use Silex\Application;
use Psr\Http\Message\ResponseInterface;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Trellis\StaticLdp\Controller\ResourceController;
use Trellis\StaticLdp\Provider\ServiceProvider;

date_default_timezone_set('UTC');

$app = new Application();

$app['debug'] = false;
$app['basePath'] = __DIR__;
$app->register(new ServiceControllerServiceProvider());
$app->register(new ServiceProvider());
$app->register(new TwigServiceProvider(), array(
  'twig.path' => array(
    __DIR__ . '/templates',
  ),
));
$app->mount("/", new ResourceController());

$app->error(function (\Exception $e, Request $req, $code) {
    $headers = [];
    switch ($code) {
    case (405):
        $message = "Method Not Allowed";
        $headers["Link"] = "<http://acdc.amherst.edu/ns/trellis#ReadOnlyResource>; rel=\"http://www.w3.org/ns/ldp#constrainedBy\"";
        break;
    default:
        $message = "Something went wrong";
    }
    return new Response($message, $code, $headers);
});

return $app;
