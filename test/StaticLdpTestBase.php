<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StaticLdpTestBase extends WebTestCase
{

    /**
     * @var \Symfony\Component\BrowserKit\Client
     */
    protected $client;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->client->catchExceptions(false);
    }
}
