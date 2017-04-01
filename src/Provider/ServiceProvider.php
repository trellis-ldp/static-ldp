<?php

namespace Trellis\StaticLdp\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Yaml\Yaml;
use Trellis\StaticLdp\Controller\ResourceController;

class ServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(Application $app) {
    # Register the ResourceService
    $app['staticldp.resourcecontroller'] = $app->share(
      function () use ($app) {
        return new ResourceController($app);
      }
    );

    /**
     * Ultra simplistic YAML settings loader.
     */
    if (!isset($app['config'])) {
      $app['config'] = $app->share(
        function () use ($app) {
          $configFile = $app['basePath'].'/../config/settings.yml';
          $settings = Yaml::parse(file_get_contents($configFile));
          return $settings;
        }
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function boot(Application $app) {
    // TODO: Implement boot() method.
  }

}