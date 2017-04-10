<?php

namespace Trellis\StaticLdp\Model;

use Trellis\StaticLdp\StaticLdpTestBase;

/**
 * Unit Test of RDFSource class.
 *
 * @coversDefaultClass \Trellis\StaticLdp\Model\RDFSource
 * @group unittest
 */
class RDFSourceTest extends StaticLdpTestBase
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
     * Test GET a RDFSource
     */
    public function testGetRDFSource()
    {
        $expected_charset = "text/turtle; charset=UTF-8";
        $expected_links = [
            "<" . Resource::LDP_NS . "Resource>; rel=\"type\"",
            "<" . Resource::LDP_NS . "RDFSource>; rel=\"type\""
        ];
        $crawler = $this->client->request('GET', "/nobel_914.ttl", [], [], ["HTTP_ACCEPT" => "text/turtle"]);
        $response = $this->client->getResponse();

        $this->assertEquals($response->getStatusCode(), 200);

        $this->assertTrue($response->headers->has("Content-Type"), "Missing Content-Type header");
        $this->assertEquals($expected_charset, $response->headers->get('Content-Type'), "Content-Type incorrect");

        $this->assertTrue($response->headers->has('Link'), "Missing Link header");
        $this->assertEquals($expected_links, $response->headers->get("Link", null, false), "Link headers incorrect.");

        $this->assertTrue($response->headers->has("etag"), "Missing Etag header.");
    }
}
