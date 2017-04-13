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


$app = new Application();

$app['basePath'] = __DIR__;
if (isset($_ENV['TRELLIS_CONFIG_DIR']) && file_exists($_ENV['TRELLIS_CONFIG_DIR'])) {
    $app['configDir'] = $_ENV['TRELLIS_CONFIG_DIR'];
} else {
    $app['configDir'] = __DIR__ . '/../config';
}
$app->register(new ServiceControllerServiceProvider());
$app->register(new ServiceProvider());
$app->register(new TwigServiceProvider(), array(
  'twig.path' => __DIR__ . '/../templates',
));
$app['debug'] = $app['config']['debug'];

$app->mount("/", new ResourceController());

$app->error(function (\Exception $e, Request $req, $code) {
    $headers = [];
    switch ($code) {
        case (405):
            $message = "Method Not Allowed";
            $headers["Link"] = TrellisConstants::READ_ONLY_RESOURCE_LINK;
            break;
        case (500):
                $message = "Something went wrong!\n" . $e->getTraceAsString();
            break;
        default:
                $message = "Something went wrong!\n";
    }
    return new Response($message, $code, $headers);
});

return $app;
