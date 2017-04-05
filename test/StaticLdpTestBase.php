<?php

namespace Trellis\StaticLdp;

use Silex\WebTestCase;

class StaticLdpTestBase extends WebTestCase
{

    public function createApplication()
    {
      // must return an Application instance
        return (require __DIR__ . '/../src/app.php');
    }
}
