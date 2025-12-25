<?php

declare(strict_types=1);

namespace Tests;

use Omegaalfa\HttpPromise\Http\HttpProcessorTrait;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for HttpProcessorTrait.
 */
class HttpProcessorTraitTest extends TestCase
{
    private object $processor;

    protected function setUp(): void
    {
        // Create anonymous class using the trait
        $this->processor = new class {
            use HttpProcessorTrait {
                formatHeaders as public;
                formatParams as public;
                encodeJson as public;
                decodeJson as public;
                getContentType as public;
                mergeHeaders as public;
                buildUrl as public;
                parseUrl as public;
            }
        };
    }

    public function testFormatHeadersBasic(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer token123'
        ];

        $formatted = $this->processor->formatHeaders($headers);

        $this->assertCount(2, $formatted);
        $this->assertContains('Content-Type: application/json', $formatted);
        $this->assertContains('Authorization: Bearer token123', $formatted);
    }

    public function testFormatHeadersSkipsEmptyValues(): void
    {
        $headers = [
            'Valid-Header' => 'value',
            'Empty-String' => '',
            'Null-Value' => null,
            'Another-Valid' => 'test'
        ];

        $formatted = $this->processor->formatHeaders($headers);

        $this->assertCount(2, $formatted);
        $this->assertContains('Valid-Header: value', $formatted);
        $this->assertContains('Another-Valid: test', $formatted);
    }

    public function testFormatHeadersWithBooleanValues(): void
    {
        $headers = [
            'X-Boolean-True' => true,
            'X-Boolean-False' => false
        ];

        $formatted = $this->processor->formatHeaders($headers);

        $this->assertContains('X-Boolean-True: true', $formatted);
        $this->assertContains('X-Boolean-False: false', $formatted);
    }

    public function testFormatHeadersWithNumericValues(): void
    {
        $headers = [
            'X-Integer' => 123,
            'X-Float' => 45.67
        ];

        $formatted = $this->processor->formatHeaders($headers);

        $this->assertContains('X-Integer: 123', $formatted);
        $this->assertContains('X-Float: 45.67', $formatted);
    }

    public function testFormatParamsNull(): void
    {
        $result = $this->processor->formatParams(null, []);
        $this->assertNull($result);
    }

    public function testFormatParamsString(): void
    {
        $result = $this->processor->formatParams('raw string', []);
        $this->assertEquals('raw string', $result);
    }

    public function testFormatParamsJsonContentType(): void
    {
        $params = ['key' => 'value', 'number' => 42];
        $headers = ['Content-Type' => 'application/json'];

        $result = $this->processor->formatParams($params, $headers);

        $this->assertJson($result);
        $this->assertStringContainsString('"key":"value"', $result);
        $this->assertStringContainsString('"number":42', $result);
    }

    public function testFormatParamsFormData(): void
    {
        $params = ['foo' => 'bar', 'baz' => 123];
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];

        $result = $this->processor->formatParams($params, $headers);

        $this->assertEquals('foo=bar&baz=123', $result);
    }

    public function testFormatParamsArrayWithoutContentType(): void
    {
        $params = ['key1' => 'value1', 'key2' => 'value2'];
        $result = $this->processor->formatParams($params, []);

        $this->assertEquals('key1=value1&key2=value2', $result);
    }

    public function testEncodeJson(): void
    {
        $data = ['message' => 'Hello', 'count' => 5];
        $result = $this->processor->encodeJson($data);

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertEquals('Hello', $decoded['message']);
        $this->assertEquals(5, $decoded['count']);
    }

    public function testGetContentType(): void
    {
        $headers = ['Content-Type' => 'application/json; charset=utf-8'];
        $result = $this->processor->getContentType($headers);

        $this->assertEquals('application/json; charset=utf-8', $result);
    }

    public function testGetContentTypeDefault(): void
    {
        $result = $this->processor->getContentType([]);
        $this->assertEquals('application/x-www-form-urlencoded', $result);
    }

    public function testGetContentTypeCaseInsensitive(): void
    {
        $headers = ['content-type' => 'text/html'];
        $result = $this->processor->getContentType($headers);

        $this->assertEquals('text/html', $result);
    }

    public function testDecodeJson(): void
    {
        $json = '{"message":"Hello","count":5}';
        $result = $this->processor->decodeJson($json);

        $this->assertEquals('Hello', $result['message']);
        $this->assertEquals(5, $result['count']);
    }

    public function testDecodeJsonAsObject(): void
    {
        $json = '{"name":"Test"}';
        $result = $this->processor->decodeJson($json, false);

        $this->assertIsObject($result);
        $this->assertEquals('Test', $result->name);
    }

    public function testMergeHeaders(): void
    {
        $defaults = [
            'User-Agent' => 'DefaultAgent',
            'Accept' => 'application/json'
        ];
        $custom = [
            'Authorization' => 'Bearer token',
            'Accept' => 'text/html'
        ];

        $result = $this->processor->mergeHeaders($custom, $defaults);

        $this->assertArrayHasKey('User-Agent', $result);
        $this->assertArrayHasKey('Authorization', $result);
        $this->assertEquals('text/html', $result['Accept']); // Custom should override
    }

    public function testMergeHeadersCaseInsensitive(): void
    {
        $defaults = ['Content-Type' => 'application/json'];
        $custom = ['content-type' => 'text/plain'];

        $result = $this->processor->mergeHeaders($custom, $defaults);

        // Should have only one content-type (custom overrides)
        $this->assertCount(1, $result);
    }

    public function testBuildUrl(): void
    {
        $url = 'https://api.example.com/endpoint';
        $params = ['page' => 1, 'limit' => 10];

        $result = $this->processor->buildUrl($url, $params);

        $this->assertStringContainsString('page=1', $result);
        $this->assertStringContainsString('limit=10', $result);
        $this->assertStringStartsWith('https://api.example.com/endpoint?', $result);
    }

    public function testBuildUrlWithExistingQuery(): void
    {
        $url = 'https://api.example.com/endpoint?existing=true';
        $params = ['new' => 'param'];

        $result = $this->processor->buildUrl($url, $params);

        $this->assertStringContainsString('existing=true', $result);
        $this->assertStringContainsString('new=param', $result);
        $this->assertStringContainsString('&', $result);
    }

    public function testBuildUrlWithNullParams(): void
    {
        $url = 'https://api.example.com/endpoint';
        $result = $this->processor->buildUrl($url, null);

        $this->assertEquals($url, $result);
    }

    public function testBuildUrlWithEmptyParams(): void
    {
        $url = 'https://api.example.com/endpoint';
        $result = $this->processor->buildUrl($url, []);

        $this->assertEquals($url, $result);
    }

    public function testParseUrl(): void
    {
        $url = 'https://user:pass@example.com:8080/path?query=value#fragment';
        $result = $this->processor->parseUrl($url);

        $this->assertEquals('https', $result['scheme']);
        $this->assertEquals('example.com', $result['host']);
        $this->assertEquals(8080, $result['port']);
        $this->assertEquals('/path', $result['path']);
        $this->assertStringContainsString('query=value', $result['query']);
    }

    public function testFormatParamsWithObject(): void
    {
        $obj = new \stdClass();
        $obj->prop1 = 'value1';
        $obj->prop2 = 'value2';

        $result = $this->processor->formatParams($obj, []);

        $this->assertStringContainsString('prop1=value1', $result);
        $this->assertStringContainsString('prop2=value2', $result);
    }

    public function testFormatParamsScalar(): void
    {
        $result = $this->processor->formatParams(12345, []);
        $this->assertEquals('12345', $result);
    }
}
