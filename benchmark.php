<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise\Utils as GuzzlePromise;
use Omegaalfa\HttpPromise\HttpPromise;
use Omegaalfa\HttpPromise\Promise\Promise;

/**
 * Simple PSR-7 Response implementation for HttpPromise.
 */
class SimpleResponse implements \Psr\Http\Message\ResponseInterface
{
    private int $statusCode = 200;
    private string $reasonPhrase = 'OK';
    /** @var array<string, array<string>> */
    private array $headers = [];
    private \Psr\Http\Message\StreamInterface $body;
    private string $protocolVersion = '1.1';

    public function __construct()
    {
        $this->body = new SimpleStream();
    }

    public function getStatusCode(): int { return $this->statusCode; }
    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase ?: match ($code) {
            200 => 'OK', 201 => 'Created', 204 => 'No Content',
            400 => 'Bad Request', 401 => 'Unauthorized', 404 => 'Not Found',
            500 => 'Internal Server Error', default => '',
        };
        return $clone;
    }
    public function getReasonPhrase(): string { return $this->reasonPhrase; }
    public function getProtocolVersion(): string { return $this->protocolVersion; }
    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this; $clone->protocolVersion = $version; return $clone;
    }
    public function getHeaders(): array { return $this->headers; }
    public function hasHeader(string $name): bool { return isset($this->headers[strtolower($name)]); }
    public function getHeader(string $name): array { return $this->headers[strtolower($name)] ?? []; }
    public function getHeaderLine(string $name): string { return implode(', ', $this->getHeader($name)); }
    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = is_array($value) ? $value : [$value];
        return $clone;
    }
    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $existing = $clone->headers[strtolower($name)] ?? [];
        $clone->headers[strtolower($name)] = array_merge($existing, is_array($value) ? $value : [$value]);
        return $clone;
    }
    public function withoutHeader(string $name): static
    {
        $clone = clone $this; unset($clone->headers[strtolower($name)]); return $clone;
    }
    public function getBody(): \Psr\Http\Message\StreamInterface { return $this->body; }
    public function withBody(\Psr\Http\Message\StreamInterface $body): static
    {
        $clone = clone $this; $clone->body = $body; return $clone;
    }
    public function __clone() { $this->body = new SimpleStream(); }
}

class SimpleStream implements \Psr\Http\Message\StreamInterface
{
    private string $content = '';
    private int $position = 0;

    public function __toString(): string { return $this->content; }
    public function close(): void { $this->content = ''; $this->position = 0; }
    public function detach() { return null; }
    public function getSize(): ?int { return strlen($this->content); }
    public function tell(): int { return $this->position; }
    public function eof(): bool { return $this->position >= strlen($this->content); }
    public function isSeekable(): bool { return true; }
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->position = match ($whence) {
            SEEK_SET => $offset, SEEK_CUR => $this->position + $offset,
            SEEK_END => strlen($this->content) + $offset, default => $offset,
        };
    }
    public function rewind(): void { $this->position = 0; }
    public function isWritable(): bool { return true; }
    public function write(string $string): int { $this->content .= $string; return strlen($string); }
    public function isReadable(): bool { return true; }
    public function read(int $length): string
    {
        $result = substr($this->content, $this->position, $length);
        $this->position += strlen($result);
        return $result;
    }
    public function getContents(): string { return substr($this->content, $this->position); }
    public function getMetadata(?string $key = null): mixed { return $key === null ? [] : null; }
}

// ============================================================================
// Benchmark Configuration
// ============================================================================

$config = [
    'iterations' => 2,           // Number of iterations for each benchmark
    'warmup' => 1,               // Warmup iterations (not counted)
    'concurrent_requests' => 5,  // Number of concurrent requests
    'endpoints' => [
        'simple' => 'https://httpbin.org/get',
        'json' => 'https://httpbin.org/json',
        'delay' => 'https://httpbin.org/delay/1',
        'uuid' => 'https://httpbin.org/uuid',
        'post' => 'https://httpbin.org/post',
    ],
];

// ============================================================================
// Helper Functions
// ============================================================================

function printHeader(string $title): void
{
    echo "\n" . str_repeat('═', 70) . "\n";
    echo "  $title\n";
    echo str_repeat('═', 70) . "\n\n";
}

function printSubHeader(string $title): void
{
    echo "\n  ── $title ──\n\n";
}

function formatTime(float $ms): string
{
    if ($ms < 1) {
        return sprintf('%.3f ms', $ms);
    }
    return sprintf('%.2f ms', $ms);
}

function formatMemory(int $bytes): string
{
    if ($bytes < 1024) {
        return "$bytes B";
    }
    if ($bytes < 1024 * 1024) {
        return sprintf('%.2f KB', $bytes / 1024);
    }
    return sprintf('%.2f MB', $bytes / (1024 * 1024));
}

function printResult(string $label, float $time, int $memory, ?float $comparison = null): void
{
    $timeStr = formatTime($time);
    $memStr = formatMemory($memory);
    
    if ($comparison !== null) {
        $diff = (($time - $comparison) / $comparison) * 100;
        $diffStr = $diff > 0 ? sprintf('+%.1f%%', $diff) : sprintf('%.1f%%', $diff);
        $emoji = $diff < 0 ? '🚀' : ($diff > 20 ? '🐢' : '');
        printf("    %-20s %12s | %10s | %s %s\n", $label, $timeStr, $memStr, $diffStr, $emoji);
    } else {
        printf("    %-20s %12s | %10s\n", $label, $timeStr, $memStr);
    }
}

function printComparisonTable(array $results): void
{
    echo "\n  ┌─────────────────────────┬──────────────────┬──────────────────┬────────────┐\n";
    echo "  │ Benchmark               │ HttpPromise      │ Guzzle           │ Winner     │\n";
    echo "  ├─────────────────────────┼──────────────────┼──────────────────┼────────────┤\n";
    
    foreach ($results as $name => $data) {
        $httpTime = formatTime($data['http_promise']);
        $guzzleTime = formatTime($data['guzzle']);
        
        if ($data['http_promise'] < $data['guzzle']) {
            $winner = '🏆 HttpPromise';
            $diff = (1 - ($data['http_promise'] / $data['guzzle'])) * 100;
        } else {
            $winner = '🏆 Guzzle';
            $diff = (1 - ($data['guzzle'] / $data['http_promise'])) * 100;
        }
        
        printf("  │ %-23s │ %16s │ %16s │ %-10s │\n", $name, $httpTime, $guzzleTime, $winner);
    }
    
    echo "  └─────────────────────────┴──────────────────┴──────────────────┴────────────┘\n";
}

function runBenchmark(callable $fn, int $iterations, int $warmup): array
{
    // Warmup
    for ($i = 0; $i < $warmup; $i++) {
        $fn();
    }
    
    gc_collect_cycles();
    $times = [];
    $memoryPeak = 0;
    
    for ($i = 0; $i < $iterations; $i++) {
        gc_collect_cycles();
        $memStart = memory_get_usage(true);
        $start = hrtime(true);
        
        $fn();
        
        $end = hrtime(true);
        $memEnd = memory_get_usage(true);
        
        $times[] = ($end - $start) / 1_000_000; // Convert to ms
        $memoryPeak = max($memoryPeak, $memEnd - $memStart);
    }
    
    // Calculate average (excluding outliers)
    sort($times);
    $trimmed = array_slice($times, 0, max(1, (int)(count($times) * 0.9)));
    $avgTime = array_sum($trimmed) / count($trimmed);
    
    return [
        'time' => $avgTime,
        'min' => min($times),
        'max' => max($times),
        'memory' => $memoryPeak,
    ];
}

// ============================================================================
// Benchmarks
// ============================================================================

printHeader('🔥 HTTP Client Benchmark: HttpPromise vs Guzzle');

echo "Configuration:\n";
echo "  • Iterations: {$config['iterations']}\n";
echo "  • Warmup: {$config['warmup']}\n";
echo "  • Concurrent Requests: {$config['concurrent_requests']}\n";
echo "  • PHP Version: " . PHP_VERSION . "\n";
echo "  • Date: " . date('Y-m-d H:i:s') . "\n";

$results = [];

// ---------------------------------------------------------------------------
// Benchmark 1: Simple GET Request
// ---------------------------------------------------------------------------
printSubHeader('Benchmark 1: Simple GET Request');

$httpPromise = HttpPromise::create(new SimpleResponse());
$guzzle = new GuzzleClient(['timeout' => 30]);

$httpResult = runBenchmark(function () use ($httpPromise, $config) {
    $response = $httpPromise->get($config['endpoints']['simple'])->wait();
    return $response->getStatusCode();
}, $config['iterations'], $config['warmup']);

$guzzleResult = runBenchmark(function () use ($guzzle, $config) {
    $response = $guzzle->get($config['endpoints']['simple']);
    return $response->getStatusCode();
}, $config['iterations'], $config['warmup']);

printResult('HttpPromise', $httpResult['time'], $httpResult['memory']);
printResult('Guzzle', $guzzleResult['time'], $guzzleResult['memory'], $httpResult['time']);

$results['Simple GET'] = [
    'http_promise' => $httpResult['time'],
    'guzzle' => $guzzleResult['time'],
];

// ---------------------------------------------------------------------------
// Benchmark 2: POST JSON Request
// ---------------------------------------------------------------------------
printSubHeader('Benchmark 2: POST JSON Request');

$postData = ['name' => 'John Doe', 'email' => 'john@example.com', 'id' => 123];

$httpResult = runBenchmark(function () use ($httpPromise, $config, $postData) {
    $response = $httpPromise->asJson()->post($config['endpoints']['post'], $postData)->wait();
    return $response->getStatusCode();
}, $config['iterations'], $config['warmup']);

$guzzleResult = runBenchmark(function () use ($guzzle, $config, $postData) {
    $response = $guzzle->post($config['endpoints']['post'], ['json' => $postData]);
    return $response->getStatusCode();
}, $config['iterations'], $config['warmup']);

printResult('HttpPromise', $httpResult['time'], $httpResult['memory']);
printResult('Guzzle', $guzzleResult['time'], $guzzleResult['memory'], $httpResult['time']);

$results['POST JSON'] = [
    'http_promise' => $httpResult['time'],
    'guzzle' => $guzzleResult['time'],
];

// ---------------------------------------------------------------------------
// Benchmark 3: Concurrent Requests (the key advantage!)
// ---------------------------------------------------------------------------
printSubHeader("Benchmark 3: {$config['concurrent_requests']} Concurrent GET Requests");

$urls = array_fill(0, $config['concurrent_requests'], $config['endpoints']['uuid']);

// HttpPromise concurrent - reuse instance for connection pooling
$httpConcurrent5 = HttpPromise::create(new SimpleResponse());
$httpResult = runBenchmark(function () use ($urls, $httpConcurrent5) {
    $requests = [];
    foreach ($urls as $i => $url) {
        $requests[$i] = ['method' => 'GET', 'url' => $url];
    }
    
    $promise = $httpConcurrent5->concurrent($requests);
    
    // Manually tick until all requests complete
    while ($promise->isPending() && $httpConcurrent5->hasPending()) {
        $httpConcurrent5->tick();
    }
    
    return $promise->wait();
}, $config['iterations'], $config['warmup']);

// Guzzle concurrent (using Pool)
$guzzleResult = runBenchmark(function () use ($guzzle, $urls) {
    $requests = function () use ($urls) {
        foreach ($urls as $url) {
            yield new Request('GET', $url);
        }
    };
    
    $responses = [];
    $pool = new Pool($guzzle, $requests(), [
        'concurrency' => count($urls),
        'fulfilled' => function ($response, $index) use (&$responses) {
            $responses[$index] = $response;
        },
    ]);
    
    $pool->promise()->wait();
    return $responses;
}, $config['iterations'], $config['warmup']);

printResult('HttpPromise', $httpResult['time'], $httpResult['memory']);
printResult('Guzzle (Pool)', $guzzleResult['time'], $guzzleResult['memory'], $httpResult['time']);

$results['Concurrent x' . $config['concurrent_requests']] = [
    'http_promise' => $httpResult['time'],
    'guzzle' => $guzzleResult['time'],
];

// ---------------------------------------------------------------------------
// Benchmark 4: Sequential Multiple Requests
// ---------------------------------------------------------------------------
printSubHeader('Benchmark 4: 5 Sequential GET Requests');

$httpResult = runBenchmark(function () use ($httpPromise, $config) {
    for ($i = 0; $i < 5; $i++) {
        $httpPromise->get($config['endpoints']['simple'])->wait();
    }
}, $config['iterations'], $config['warmup']);

$guzzleResult = runBenchmark(function () use ($guzzle, $config) {
    for ($i = 0; $i < 5; $i++) {
        $guzzle->get($config['endpoints']['simple']);
    }
}, $config['iterations'], $config['warmup']);

printResult('HttpPromise', $httpResult['time'], $httpResult['memory']);
printResult('Guzzle', $guzzleResult['time'], $guzzleResult['memory'], $httpResult['time']);

$results['Sequential x5'] = [
    'http_promise' => $httpResult['time'],
    'guzzle' => $guzzleResult['time'],
];

// ---------------------------------------------------------------------------
// Benchmark 5: Request with JSON Response Processing
// ---------------------------------------------------------------------------
printSubHeader('Benchmark 5: GET + JSON Decode');

$httpResult = runBenchmark(function () use ($httpPromise, $config) {
    $response = $httpPromise->get($config['endpoints']['json'])->wait();
    return json_decode($response->getBody()->getContents(), true);
}, $config['iterations'], $config['warmup']);

$guzzleResult = runBenchmark(function () use ($guzzle, $config) {
    $response = $guzzle->get($config['endpoints']['json']);
    return json_decode($response->getBody()->getContents(), true);
}, $config['iterations'], $config['warmup']);

printResult('HttpPromise', $httpResult['time'], $httpResult['memory']);
printResult('Guzzle', $guzzleResult['time'], $guzzleResult['memory'], $httpResult['time']);

$results['GET + JSON Decode'] = [
    'http_promise' => $httpResult['time'],
    'guzzle' => $guzzleResult['time'],
];

// ---------------------------------------------------------------------------
// Benchmark 6: Delayed Response (1 second delay)
// ---------------------------------------------------------------------------
printSubHeader('Benchmark 6: Request with 1s Server Delay');

$httpResult = runBenchmark(function () use ($httpPromise, $config) {
    $response = $httpPromise->get($config['endpoints']['delay'])->wait();
    return $response->getStatusCode();
}, 2, 1); // Fewer iterations due to delay

$guzzleResult = runBenchmark(function () use ($guzzle, $config) {
    $response = $guzzle->get($config['endpoints']['delay']);
    return $response->getStatusCode();
}, 2, 1);

printResult('HttpPromise', $httpResult['time'], $httpResult['memory']);
printResult('Guzzle', $guzzleResult['time'], $guzzleResult['memory'], $httpResult['time']);

$results['Delayed (1s)'] = [
    'http_promise' => $httpResult['time'],
    'guzzle' => $guzzleResult['time'],
];

// ---------------------------------------------------------------------------
// Benchmark 7: Parallel vs Sequential Comparison
// ---------------------------------------------------------------------------
printSubHeader('Benchmark 7: 5 Requests - Parallel vs Sequential');

$numRequests = 5;
$urls5 = array_fill(0, $numRequests, $config['endpoints']['uuid']);

// HttpPromise Parallel - using the optimized concurrent() method
$httpParallel = HttpPromise::create(new SimpleResponse());
$httpParallelResult = runBenchmark(function () use ($urls5, $httpParallel) {
    $requests = [];
    foreach ($urls5 as $i => $url) {
        $requests[$i] = ['method' => 'GET', 'url' => $url];
    }
    
    $promise = $httpParallel->concurrent($requests);
    
    while ($promise->isPending() && $httpParallel->hasPending()) {
        $httpParallel->tick();
    }
    
    return $promise->wait();
}, $config['iterations'], $config['warmup']);

// HttpPromise Sequential
$httpSeq = HttpPromise::create(new SimpleResponse());
$httpSeqResult = runBenchmark(function () use ($urls5, $httpSeq) {
    $results = [];
    foreach ($urls5 as $url) {
        $results[] = $httpSeq->get($url)->wait();
    }
    return $results;
}, $config['iterations'], $config['warmup']);

echo "  HttpPromise:\n";
printResult('Parallel', $httpParallelResult['time'], $httpParallelResult['memory']);
printResult('Sequential', $httpSeqResult['time'], $httpSeqResult['memory'], $httpParallelResult['time']);

$speedup = ($httpSeqResult['time'] / $httpParallelResult['time']);
printf("\n    Speedup: %.2fx faster with parallel requests\n", $speedup);

// ---------------------------------------------------------------------------
// Benchmark 8: Large Concurrent Batch (10 requests)
// ---------------------------------------------------------------------------
printSubHeader('Benchmark 8: 10 Concurrent Requests');

$urls20 = array_fill(0, 10, $config['endpoints']['uuid']);

// HttpPromise concurrent - using the optimized concurrent() method
$httpConcurrent10 = HttpPromise::create(new SimpleResponse());
$httpResult = runBenchmark(function () use ($urls20, $httpConcurrent10) {
    $requests = [];
    foreach ($urls20 as $i => $url) {
        $requests[$i] = ['method' => 'GET', 'url' => $url];
    }
    
    $promise = $httpConcurrent10->concurrent($requests);
    
    while ($promise->isPending() && $httpConcurrent10->hasPending()) {
        $httpConcurrent10->tick();
    }
    
    return $promise->wait();
}, $config['iterations'], $config['warmup']);

$guzzleResult = runBenchmark(function () use ($guzzle, $urls20) {
    $requests = function () use ($urls20) {
        foreach ($urls20 as $url) {
            yield new Request('GET', $url);
        }
    };
    
    $responses = [];
    $pool = new Pool($guzzle, $requests(), [
        'concurrency' => 10,
        'fulfilled' => function ($response, $index) use (&$responses) {
            $responses[$index] = $response;
        },
    ]);
    
    $pool->promise()->wait();
    return $responses;
}, $config['iterations'], $config['warmup']);

printResult('HttpPromise', $httpResult['time'], $httpResult['memory']);
printResult('Guzzle (Pool)', $guzzleResult['time'], $guzzleResult['memory'], $httpResult['time']);

$results['Concurrent x10'] = [
    'http_promise' => $httpResult['time'],
    'guzzle' => $guzzleResult['time'],
];

// ---------------------------------------------------------------------------
// Results Summary
// ---------------------------------------------------------------------------
printHeader('📊 Results Summary');

printComparisonTable($results);

// Calculate overall winner
$httpWins = 0;
$guzzleWins = 0;
$totalHttpTime = 0;
$totalGuzzleTime = 0;

foreach ($results as $data) {
    $totalHttpTime += $data['http_promise'];
    $totalGuzzleTime += $data['guzzle'];
    
    if ($data['http_promise'] < $data['guzzle']) {
        $httpWins++;
    } else {
        $guzzleWins++;
    }
}

echo "\n  Overall Statistics:\n";
echo "  ───────────────────────────────────────────────────────────────────\n";
printf("    HttpPromise wins: %d / %d benchmarks\n", $httpWins, count($results));
printf("    Guzzle wins: %d / %d benchmarks\n", $guzzleWins, count($results));
printf("    Total time HttpPromise: %s\n", formatTime($totalHttpTime));
printf("    Total time Guzzle: %s\n", formatTime($totalGuzzleTime));

$overallDiff = (($totalHttpTime - $totalGuzzleTime) / $totalGuzzleTime) * 100;
if ($overallDiff < 0) {
    printf("\n    🏆 HttpPromise is %.1f%% faster overall!\n", abs($overallDiff));
} else {
    printf("\n    🏆 Guzzle is %.1f%% faster overall!\n", $overallDiff);
}

echo "\n  Key Insights:\n";
echo "  ───────────────────────────────────────────────────────────────────\n";
echo "    • HttpPromise: Zero dependencies, native cURL multi-handle\n";
echo "    • Guzzle: Full-featured, middleware support, PSR-18 compliant\n";
echo "    • Concurrent requests show the biggest performance difference\n";
echo "    • For simple requests, both libraries perform similarly\n";

printHeader('Benchmark Complete');
