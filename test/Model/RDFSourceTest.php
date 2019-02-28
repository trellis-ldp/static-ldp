<?php

namespace App\Tests\Model;

use App\Tests\StaticLdpTestBase;
use App\Model\Resource;

/**
 * Unit Test of RDFSource class.
 *
 * @coversDefaultClass \App\Model\RDFSource
 * @group unittest
 */
class RDFSourceTest extends StaticLdpTestBase
{

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
        $charset = $response->getCharset();

        $this->assertTrue($response->headers->has('Link'), "Missing Link header");
        $this->assertEquals($expected_links, $response->headers->get("Link", null, false), "Link headers incorrect.");

        $this->assertTrue($response->headers->has('Vary'), "Missing Vary header");
        $this->assertEquals($expected_vary, $response->headers->get('Vary'), "Vary headers incorrect.");

        $this->assertTrue($response->headers->has("Content-Type"), "Missing Content-Type header");
        $this->assertContains($response->headers->get('Content-Type'), $request_mime, "Content-Type header incorrect");

        $this->assertTrue($response->headers->has("etag"), "Missing Etag header.");
        $etag = $response->headers->get('etag');

        $size = filesize("./test/resources/test_directory/nobel_914.ttl");
        $this->assertTrue($response->headers->has("Content-Length"), "Missing Content-Length header");
        $this->assertEquals($size, $response->headers->get('Content-Length'), "Content-Length header incorrect");

        $this->client->request('GET', "/nobel_914.ttl", [], [], ['HTTP_ACCEPT' => $request_mime]);
        $response = $this->client->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), "GET should be allowed a second time.");
        $this->assertTrue($response->headers->has("etag"), "Missing Etag header.");
        $this->assertEquals($etag, $response->headers->get('etag'), "Etags don't match.");

        $headers = [
            'HTTP_ACCEPT' => $request_mime,
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
