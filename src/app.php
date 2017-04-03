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

return $app;
