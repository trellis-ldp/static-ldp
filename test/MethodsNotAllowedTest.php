<?php

namespace Trellis\StaticLdp;

/**
 * Unit Test of methods not allowed response.
 *
 * @coversDefaultClass \Trellis\StaticLdp
 * @group unittest
 */
class MethodsNotAllowedTest extends StaticLdpTestBase
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
        $this->client = $this->createClient();
    }

    /**
     * Test Post returns 405 with constrainedBy header
     */
    public function testPostMethod()
    {
        $crawler = $this->client->request('POST', "/");
        $this->assertEquals($this->client->getResponse()->getStatusCode(), 405, "POST should not be allowed.");
        $this->assertTrue($this->client->getResponse()->headers->has('Link'), "Missing Link header");
        $headers = $this->client->getResponse()->headers->get('Link');
        if (!is_array($headers)) {
            $headers = [$headers];
        }
        foreach ($headers as $h) {
            if ($h == TrellisConstants::READ_ONLY_RESOURCE_LINK) {
                $this->assertEquals(TrellisConstants::READ_ONLY_RESOURCE_LINK, $h);
                return;
            }
        }
        $this->fail();
    }

    /**
     * Test Patch returns 405 with constrainedBy header
     */
    public function testPatchMethod()
    {
        $crawler = $this->client->request('PATCH', "/");
        $this->assertEquals($this->client->getResponse()->getStatusCode(), 405, "PATCH should not be allowed.");
        $this->assertTrue($this->client->getResponse()->headers->has('Link'), "Missing Link header");
        $headers = $this->client->getResponse()->headers->get('Link');
        if (!is_array($headers)) {
            $headers = [$headers];
        }
        foreach ($headers as $h) {
            if ($h == TrellisConstants::READ_ONLY_RESOURCE_LINK) {
                $this->assertEquals(TrellisConstants::READ_ONLY_RESOURCE_LINK, $h);
                return;
            }
        }
        $this->fail();
    }

    /**
     * Test Put returns 405 with constrainedBy header
     */
    public function testPutMethod()
    {
        $crawler = $this->client->request('PUT', "/");
        $this->assertEquals($this->client->getResponse()->getStatusCode(), 405, "PATCH should not be allowed.");
        $this->assertTrue($this->client->getResponse()->headers->has('Link'), "Missing Link header");
        $headers = $this->client->getResponse()->headers->get('Link');
        if (!is_array($headers)) {
            $headers = [$headers];
        }
        foreach ($headers as $h) {
            if ($h == TrellisConstants::READ_ONLY_RESOURCE_LINK) {
                $this->assertEquals(TrellisConstants::READ_ONLY_RESOURCE_LINK, $h);
                return;
            }
        }
        $this->fail();
    }
}
