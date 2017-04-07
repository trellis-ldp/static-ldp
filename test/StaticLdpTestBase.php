<?php

namespace Trellis\StaticLdp;

use Silex\WebTestCase;

class StaticLdpTestBase extends WebTestCase
{

    public function createApplication()
    {
      // must return an Application instance
        $_ENV['configDir'] = __DIR__ . '/resources/config';
        return (require __DIR__ . '/../src/app.php');
    }
}
