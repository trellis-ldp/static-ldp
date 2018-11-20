<?php

namespace App\Tests\Model;

use App\Tests\StaticLdpTestBase;
use App\Model\Resource;

/**
 * Unit Test of NonRDFSource class.
 *
 * @coversDefaultClass \App\Model\NonRDFSource
 * @group unittest
 */
class NonRDFSourceTest extends StaticLdpTestBase
{

    /**
     * Test GET a NonRDFSource
     */
    public function testGetNonRDFSource()
    {
        $expected_links = [
            "<" . Resource::LDP_NS . "Resource>; rel=\"type\"",
            "<" . Resource::LDP_NS . "NonRDFSource>; rel=\"type\""
        ];
        $this->client->request('GET', "/riel.jpg");
        $response = $this->client->getResponse();

        $this->assertEquals($response->getStatusCode(), 200);

        $this->assertTrue($response->headers->has("Content-Type"), "Missing Content-Type header");
        $this->assertEquals("image/jpeg", $response->headers->get('Content-Type'), "Content-Type header incorrect");

        $this->assertTrue($response->headers->has('Link'), "Missing Link header");
        $this->assertEquals($expected_links, $response->headers->get("Link", null, false), "Link headers incorrect.");

        $this->assertTrue($response->headers->has("etag"), "Missing Etag header.");
    }
}
