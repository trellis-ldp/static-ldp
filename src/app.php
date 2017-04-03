<?php

namespace Trellis\StaticLdp;

require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;
use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Trellis\StaticLdp\Controller\ResourceController;
use Trellis\StaticLdp\Provider\ServiceProvider;

date_default_timezone_set('UTC');

$CONSTRAINED_BY = "http://www.w3.org/ns/ldp#constrainedBy";
$READ_ONLY_RESOURCE = "http://acdc.amherst.edu/ns/trellis#ReadOnlyResource";

$app = new Application();

$app['debug'] = false;
$app['basePath'] = __DIR__;
$app->register(new ServiceControllerServiceProvider());
$app->register(new ServiceProvider());
$app->register(new TwigServiceProvider(), array(
  'twig.path' => __DIR__ . '/../templates',
));
$app->mount("/", new ResourceController());

$app->error(function (\Exception $e, Request $req, $code) {
    $headers = [];
    switch ($code) {
        case (405):
            $message = "Method Not Allowed";
            $headers["Link"] = "<{$READ_ONLY_RESOURCE}>; rel=\"{$CONSTRAINED_BY}\"";
            break;
        default:
            $message = "Something went wrong";
    }
    return new Response($message, $code, $headers);
});

return $app;
