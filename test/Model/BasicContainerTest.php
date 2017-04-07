<?php
/**
 * Created by PhpStorm.
 * User: whikloj
 * Date: 2017-04-06
 * Time: 9:26 PM
 */

namespace Trellis\StaticLdp\Model;

use Trellis\StaticLdp\StaticLdpTestBase;

/**
 * @coversDefaultClass \Trellis\StaticLdp\Model\BasicContainer
 * @group unittest
 */
class BasicContainerTest extends StaticLdpTestBase
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
     * Test Get of RDF directory listing/ BasicContainer
     * @covers ::get
     * @covers ::__construct
     * @covers ::getEtag
     */
    public function testGetTurtle()
    {
        $expected_links = [
            "<" . Resource::LDP_NS . "Resource>; rel=\"type\"",
            "<" . Resource::LDP_NS . "BasicContainer>; rel=\"type\""
        ];
        $expected_vary = "Accept";
        $request_mime = "text/turtle";

        $this->client->request('GET', "/", [], [], ['HTTP_ACCEPT' => $request_mime]);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "GET should be allowed.");
        $response = $this->client->getResponse();
        $charset = $response->getCharset();
        $expected_mime = "{$request_mime}; charset={$charset}";
        $expected_body = "@prefix dc: <http://purl.org/dc/terms/> .\n" .
            "@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .\n" .
            "@prefix ldp: <http://www.w3.org/ns/ldp#> .\n\n" .
            "<http://localhost/>\n" . "  dc:modified \"2017-04-07T04:38:23Z\"^^xsd:dateTime ;\n" .
            "  a ldp:Resource, ldp:BasicContainer ;\n" .
            "  ldp:contains <http://localhost/nobel_914.ttl>, <http://localhost/riel.jpg> .\n\n";

        $this->assertTrue($response->headers->has('Link'), "Missing Link header");
        $this->assertEquals($expected_links, $response->headers->get("Link", null, false), "Link headers incorrect.");

        $this->assertTrue($response->headers->has('Vary'), "Missing Vary header");
        $this->assertEquals($expected_vary, $response->headers->get('Vary'), "Vary headers incorrect.");

        $this->assertTrue($response->headers->has("Content-Type"), "Missing Content-Type header");
        $this->assertEquals($expected_mime, $response->headers->get('Content-Type'), "Content-Type header incorrect");

        $this->assertTrue($response->headers->has("etag"), "Missing Etag header.");
        $etag = $response->headers->get('etag');

        $this->client->request('GET', "/", [], [], ['HTTP_ACCEPT' => $expected_mime]);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "GET should be allowed a second time.");
        $this->assertTrue($this->client->getResponse()->headers->has("etag"), "Missing Etag header.");
        $this->assertEquals($etag, $this->client->getResponse()->headers->get('etag'), "Etags don't match.");
        $this->assertEquals($expected_body, $this->client->getResponse()->getContent(), "Body does not match.");
    }

    /**
     * Test Get of RDF directory listing/ BasicContainer
     * @covers ::get
     * @covers ::__construct
     * @covers ::getEtag
     */
    public function testGetJsonLD()
    {
        $expected_links = [
            "<" . Resource::LDP_NS . "Resource>; rel=\"type\"",
            "<" . Resource::LDP_NS . "BasicContainer>; rel=\"type\""
        ];
        $expected_vary = "Accept";
        $expected_mime = "application/ld+json";
        $expected_body = "[{\"@id\":\"http://localhost/\",\"http://purl.org/dc/terms/modified\":" .
            "[{\"@value\":\"2017-04-07T04:38:23Z\",\"@type\":\"http://www.w3.org/2001/XMLSchema#dateTime\"}]," .
            "\"@type\":[\"http://www.w3.org/ns/ldp#Resource\",\"http://www.w3.org/ns/ldp#BasicContainer\"]," .
            "\"http://www.w3.org/ns/ldp#contains\":[{\"@id\":\"http://localhost/nobel_914.ttl\"}," .
            "{\"@id\":\"http://localhost/riel.jpg\"}]},{\"@id\":\"http://localhost/nobel_914.ttl\"}," .
            "{\"@id\":\"http://localhost/riel.jpg\"},{\"@id\":\"http://www.w3.org/ns/ldp#BasicContainer\"}," .
            "{\"@id\":\"http://www.w3.org/ns/ldp#Resource\"}]";

        $this->client->request('GET', "/", [], [], ['HTTP_ACCEPT' => $expected_mime]);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "GET should be allowed.");
        $response = $this->client->getResponse();

        $this->assertTrue($response->headers->has('Link'), "Missing Link header");
        $this->assertEquals($expected_links, $response->headers->get("Link", null, false), "Link headers incorrect.");

        $this->assertTrue($response->headers->has('Vary'), "Missing Vary header");
        $this->assertEquals($expected_vary, $response->headers->get('Vary'), "Vary headers incorrect.");

        $this->assertTrue($response->headers->has("Content-Type"), "Missing Content-Type header");
        $this->assertEquals($expected_mime, $response->headers->get('Content-Type'), "Content-Type header incorrect");

        $this->assertTrue($response->headers->has("etag"), "Missing Etag header.");
        $etag = $response->headers->get('etag');

        $this->client->request('GET', "/", [], [], ['HTTP_ACCEPT' => $expected_mime]);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "GET should be allowed a second time.");
        $this->assertTrue($this->client->getResponse()->headers->has("etag"), "Missing Etag header.");
        $this->assertEquals($etag, $this->client->getResponse()->headers->get('etag'), "Etags don't match.");

        $this->assertJson($this->client->getResponse()->getContent(), "Is not valid JSON");
        $this->assertJsonStringEqualsJsonString(
            $expected_body,
            $this->client->getResponse()->getContent(),
            "JSON response does not match expected."
        );
    }
}
