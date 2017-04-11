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
         $expected_links = [
            "<" . Resource::LDP_NS . "Resource>; rel=\"type\"",
            "<" . Resource::LDP_NS . "RDFSource>; rel=\"type\""
        ];
        $expected_vary = "Accept";
        $request_mime = "text/turtle";

        $this->client->request('GET', "/nobel_914.ttl", [], [], ['HTTP_ACCEPT' => $request_mime]);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "GET should be allowed.");
        $response = $this->client->getResponse();
        $response->sendContent();
        $charset = $response->getCharset();
        $expected_mime = "{$request_mime}; charset={$charset}";

        $this->assertTrue($response->headers->has('Link'), "Missing Link header");
        $this->assertEquals($expected_links, $response->headers->get("Link", null, false), "Link headers incorrect.");

        $this->assertTrue($response->headers->has('Vary'), "Missing Vary header");
        $this->assertEquals($expected_vary, $response->headers->get('Vary'), "Vary headers incorrect.");

        $this->assertTrue($response->headers->has("Content-Type"), "Missing Content-Type header");
        $this->assertEquals($expected_mime, $response->headers->get('Content-Type'), "Content-Type header incorrect");

        $this->assertTrue($response->headers->has("etag"), "Missing Etag header.");
        $etag = $response->headers->get('etag');

        $size = filesize("./test/resources/test_directory/nobel_914.ttl");
        $this->assertTrue($response->headers->has("Content-Length"), "Missing Content-Length header");
        $this->assertEquals($size, $response->headers->get('Content-Length'), "Content-Length header incorrect");

        $this->client->request('GET', "/nobel_914.ttl", [], [], ['HTTP_ACCEPT' => $expected_mime]);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "GET should be allowed a second time.");
        $this->assertTrue($this->client->getResponse()->headers->has("etag"), "Missing Etag header.");
        $this->assertEquals($etag, $this->client->getResponse()->headers->get('etag'), "Etags don't match.");

        $headers = [
            'HTTP_ACCEPT' => $expected_mime,
            'HTTP_IF_NONE_MATCH' => "{$etag}"
        ];
        $this->client->request('GET', "/nobel_914.ttl", [], [], $headers);
        $this->assertEquals(304, $this->client->getResponse()->getStatusCode(), "Conditional GET should return a 304");
    }

    /**
     * Test GET a RDFSource as JSON-LD
     */
    public function testGetRDFSourceJSON()
    {
         $expected_links = [
            "<" . Resource::LDP_NS . "Resource>; rel=\"type\"",
            "<" . Resource::LDP_NS . "RDFSource>; rel=\"type\""
        ];
        $expected_vary = "Accept";
        $request_mime = "application/ld+json";

        $this->client->request('GET', "/nobel_914.ttl", [], [], ['HTTP_ACCEPT' => $request_mime]);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "GET should be allowed.");
        $response = $this->client->getResponse();
        $content = $response->getContent();

        $this->assertTrue($response->headers->has('Link'), "Missing Link header");
        $this->assertEquals($expected_links, $response->headers->get("Link", null, false), "Link headers incorrect.");

        $this->assertTrue($response->headers->has('Vary'), "Missing Vary header");
        $this->assertEquals($expected_vary, $response->headers->get('Vary'), "Vary headers incorrect.");

        $this->assertTrue($response->headers->has("Content-Type"), "Missing Content-Type header");
        $this->assertEquals($request_mime, $response->headers->get('Content-Type'), "Content-Type header incorrect");

        $this->assertTrue($response->headers->has("etag"), "Missing Etag header.");
        $etag = $response->headers->get('etag');

        $this->client->request('GET', "/nobel_914.ttl", [], [], ['HTTP_ACCEPT' => $request_mime]);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "GET should be allowed a second time.");
        $this->assertTrue($this->client->getResponse()->headers->has("etag"), "Missing Etag header.");
        $this->assertEquals($etag, $this->client->getResponse()->headers->get('etag'), "Etags don't match.");

        $headers = [
            'HTTP_ACCEPT' => $request_mime,
            'HTTP_IF_NONE_MATCH' => "{$etag}"
        ];
        $this->client->request('GET', "/nobel_914.ttl", [], [], $headers);
        $this->assertEquals(304, $this->client->getResponse()->getStatusCode(), "Conditional GET should return a 304");
    }
}
