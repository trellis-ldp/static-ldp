<?php

namespace Trellis\StaticLdp;

use Silex\WebTestCase;

class StaticLdpTestBase extends WebTestCase
{

    public function createApplication()
    {
        $_ENV['env'] = 'test';
      // must return an Application instance
        return  (require __DIR__ . '/../src/app.php');
    }
}
