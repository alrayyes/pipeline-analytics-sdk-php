<?php

declare(strict_types=1);

namespace PipelineAnalytics\Tests;

use ArrayAccess;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PipelineAnalytics\Client;
use PipelineAnalytics\Exception\ApiError;
use PipelineAnalytics\Generated\ApiException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ClientTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv(Client::SESSION_COOKIE_ENV_VAR);
    }

    #[Test]
    public function explicit_session_cookie_is_sent_on_every_request(): void
    {
        $mock = new MockHandler([new Response(200, [], '{"version":"dev"}')]);
        $stack = HandlerStack::create($mock);
        $client = new Client('https://example.test', 'from-constructor', handlerStack: $stack);
        $this->attachHistory($stack, $history);

        $client->meta->getVersion();

        self::assertSame(
            'session=from-constructor',
            $this->requestAt($history, 0)->getHeaderLine('Cookie'),
        );
    }

    #[Test]
    public function falls_back_to_the_environment_variable_when_no_cookie_is_passed(): void
    {
        putenv(Client::SESSION_COOKIE_ENV_VAR.'=from-env');

        $mock = new MockHandler([new Response(200, [], '{"version":"dev"}')]);
        $stack = HandlerStack::create($mock);
        $client = new Client('https://example.test', handlerStack: $stack);
        $this->attachHistory($stack, $history);

        $client->meta->getVersion();

        self::assertSame(
            'session=from-env',
            $this->requestAt($history, 0)->getHeaderLine('Cookie'),
        );
    }

    #[Test]
    public function an_explicit_cookie_overrides_the_environment_variable(): void
    {
        putenv(Client::SESSION_COOKIE_ENV_VAR.'=from-env');

        $mock = new MockHandler([new Response(200, [], '{"version":"dev"}')]);
        $stack = HandlerStack::create($mock);
        $client = new Client('https://example.test', 'from-constructor', handlerStack: $stack);
        $this->attachHistory($stack, $history);

        $client->meta->getVersion();

        self::assertSame(
            'session=from-constructor',
            $this->requestAt($history, 0)->getHeaderLine('Cookie'),
        );
    }

    #[Test]
    public function no_cookie_header_is_sent_when_none_is_available(): void
    {
        $mock = new MockHandler([new Response(200, [], '{"version":"dev"}')]);
        $stack = HandlerStack::create($mock);
        $client = new Client('https://example.test', handlerStack: $stack);
        $this->attachHistory($stack, $history);

        $client->meta->getVersion();

        self::assertFalse($this->requestAt($history, 0)->hasHeader('Cookie'));
    }

    #[Test]
    public function retries_a5xx_response_and_succeeds_on_the_retry(): void
    {
        $mock = new MockHandler([
            new Response(503, ['Retry-After' => '0']),
            new Response(200, [], '{"version":"dev"}'),
        ]);
        $stack = HandlerStack::create($mock);
        $client = new Client('https://example.test', handlerStack: $stack);
        $this->attachHistory($stack, $history);

        $client->meta->getVersion();

        self::assertSame(2, $this->historyCount($history));
    }

    #[Test]
    public function a_trailing_slash_on_the_base_url_does_not_produce_a_double_slash(): void
    {
        $mock = new MockHandler([new Response(200, [], '{"version":"dev"}')]);
        $stack = HandlerStack::create($mock);
        $client = new Client('https://example.test/', handlerStack: $stack);
        $this->attachHistory($stack, $history);

        $client->meta->getVersion();

        $uri = $this->requestAt($history, 0)->getUri();
        // Pins that setHost() actually ran at all -- the generated
        // Configuration's own default host ('http://localhost') would
        // otherwise silently take over instead.
        self::assertSame('example.test', $uri->getHost());
        self::assertStringNotContainsString('//api', (string) $uri);
    }

    #[Test]
    public function an_explicit_http_client_is_used_directly_instead_of_building_one(): void
    {
        // No cookie or retry middleware pushed onto this handler stack at
        // all -- if Client built its own Guzzle client instead of using
        // this one (ignoring it entirely, say, on a $httpClient ?? ...
        // that got its operands swapped), the request would either 500
        // via a retry loop with nothing to retry into, or simply not be
        // the object this test can see requests through.
        $mock = new MockHandler([new Response(200, [], '{"version":"dev"}')]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $explicitClient = new GuzzleClient(['handler' => $stack]);

        $client = new Client('https://example.test', 'a-cookie', httpClient: $explicitClient);
        $client->meta->getVersion();

        self::assertIsArray($history);
        self::assertCount(1, $history);
        // Client's own cookie middleware never ran on this stack, since
        // buildGuzzleClient() -- the only place that middleware gets
        // pushed -- must not run when an explicit client is given.
        self::assertFalse($this->requestAt($history, 0)->hasHeader('Cookie'));
    }

    #[Test]
    public function default_max_retries_is_exactly_three(): void
    {
        // Four failing responses queued, none of them a success: with the
        // real default of 3 retries (4 attempts total), the 4th response
        // is consumed and the walk ends there. A default one lower would
        // give up after 3 attempts (historyCount would be 3, not 4); a
        // default one higher would ask MockHandler for a 5th response it
        // doesn't have, throwing OutOfBoundsException instead of the
        // ApiException this test catches -- either direction fails loudly.
        $mock = new MockHandler([
            new Response(503),
            new Response(503),
            new Response(503),
            new Response(503),
        ]);
        $stack = HandlerStack::create($mock);
        $client = new Client('https://example.test', handlerStack: $stack);
        $this->attachHistory($stack, $history);

        try {
            $client->meta->getVersion();
            self::fail('expected an ApiException');
        } catch (ApiException) {
            // expected -- fall through to the assertion below
        }

        self::assertSame(4, $this->historyCount($history));
    }

    #[Test]
    public function never_retries_a400(): void
    {
        $mock = new MockHandler([new Response(400, [], '{"code":"bad_request","message":"nope"}')]);
        $stack = HandlerStack::create($mock);
        $client = new Client('https://example.test', handlerStack: $stack);
        $this->attachHistory($stack, $history);

        try {
            $client->meta->getVersion();
            self::fail('expected an ApiException');
        } catch (ApiException) {
            // expected -- fall through to the assertion below
        }

        self::assertSame(1, $this->historyCount($history));
    }

    #[Test]
    public function decodes_an_error_response_into_an_api_error(): void
    {
        $mock = new MockHandler([
            new Response(401, ['X-Request-Id' => 'req-42'], '{"code":"unauthorized","message":"session expired"}'),
        ]);
        $stack = HandlerStack::create($mock);
        $client = new Client('https://example.test', 'stale-cookie', handlerStack: $stack, maxRetries: 0);

        try {
            $client->repos->listRepos();
            self::fail('expected an ApiException');
        } catch (ApiException $exception) {
            $apiError = ApiError::fromGeneratedException($exception);
        }

        self::assertSame(401, $apiError->statusCode);
        self::assertSame('unauthorized', $apiError->apiCode);
        self::assertSame('req-42', $apiError->requestId);
        self::assertStringContainsString('session expired', $apiError->getMessage());
    }

    /**
     * Attaches Middleware::history() to $stack *after* the Client has
     * already pushed its own cookie/retry middleware onto it, so history
     * ends up closest to the transport and records the final, fully
     * mutated request -- not the pristine one Client started from.
     * HandlerStack resolves lazily per request, so a middleware pushed
     * after construction still takes effect.
     *
     * @param-out array<array-key, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}>|ArrayAccess<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}> $history
     */
    private function attachHistory(HandlerStack $stack, mixed &$history): void
    {
        $history = [];
        $stack->push(Middleware::history($history));
    }

    /**
     * Guzzle's own type for a history container is a loose
     * array<array-key, shape>|ArrayAccess<int, shape> (it has to support
     * both), so nothing statically guarantees a given offset exists or
     * that the container is even a plain array -- assertIsArray/
     * assertArrayHasKey narrow that for PHPStan (via phpstan/phpstan-phpunit)
     * the same way an `if` would, without resorting to an inline @var
     * override.
     *
     * @param  array<array-key, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}>|ArrayAccess<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}>  $history
     */
    private function requestAt(array|ArrayAccess $history, int $index): RequestInterface
    {
        self::assertIsArray($history);
        self::assertArrayHasKey($index, $history);

        return $history[$index]['request'];
    }

    /**
     * @param  array<array-key, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}>|ArrayAccess<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}>  $history
     */
    private function historyCount(array|ArrayAccess $history): int
    {
        self::assertIsArray($history);

        return count($history);
    }
}
