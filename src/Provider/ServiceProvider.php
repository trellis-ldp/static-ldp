<?php

namespace Trellis\StaticLdp\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Yaml\Yaml;
use Trellis\StaticLdp\Controller\ResourceController;

class ServiceProvider implements ServiceProviderInterface
{

    /**
     * {@inheritdoc}
     */
    public function register(Container $app)
    {
        # Register the ResourceService
        $app['staticldp.resourcecontroller'] = function ($app) {
            return new ResourceController($app);
        };

        /**
         * Ultra simplistic YAML settings loader.
         */
        if (!isset($app['config'])) {
            $app['config'] = function ($app) {
                $configFile = $app['basePath'] . '/../config/settings.yml';
                $settings = Yaml::parse(file_get_contents($configFile));
                return $settings;
            };
        }
    }
}
