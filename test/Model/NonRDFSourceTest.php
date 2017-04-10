<?php

namespace Trellis\StaticLdp\Model;

use Trellis\StaticLdp\StaticLdpTestBase;

/**
 * Unit Test of NonRDFSource class.
 *
 * @coversDefaultClass \Trellis\StaticLdp\Model\NonRDFSource
 * @group unittest
 */
class NonRDFSourceTest extends StaticLdpTestBase
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
     * Test GET a NonRDFSource
     */
    public function testGetNonRDFSource()
    {
        $expected_links = [
            "<" . Resource::LDP_NS . "Resource>; rel=\"type\"",
            "<" . Resource::LDP_NS . "NonRDFSource>; rel=\"type\""
        ];
        $crawler = $this->client->request('GET', "/riel.jpg");
        $response = $this->client->getResponse();

        $this->assertEquals($response->getStatusCode(), 200);

        $this->assertTrue($response->headers->has("Content-Type"), "Missing Content-Type header");
        $this->assertEquals("image/jpeg", $response->headers->get('Content-Type'), "Content-Type header incorrect");

        $this->assertTrue($response->headers->has('Link'), "Missing Link header");
        $this->assertEquals($expected_links, $response->headers->get("Link", null, false), "Link headers incorrect.");

        $this->assertTrue($response->headers->has("etag"), "Missing Etag header.");
    }
}
