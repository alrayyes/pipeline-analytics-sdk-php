<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use PipelineAnalytics\Client;
use PipelineAnalytics\Exception\ApiError;
use PipelineAnalytics\Generated\ApiException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Attaches Middleware::history() to $stack *after* the Client has already
 * pushed its own cookie/retry middleware onto it, so history ends up
 * closest to the transport and records the final, fully mutated request --
 * not the pristine one Client started from. HandlerStack resolves lazily
 * per request, so a middleware pushed after construction still takes
 * effect.
 *
 * @param-out array<array-key, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}>|ArrayAccess<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}> $history
 */
function attachHistory(HandlerStack $stack, mixed &$history): void
{
    $history = [];
    $stack->push(Middleware::history($history));
}

/**
 * Guzzle's own type for a history container is a loose
 * array<array-key, shape>|ArrayAccess<int, shape> (it has to support
 * both), so nothing statically guarantees a given offset exists or that
 * the container is even a plain array -- Assert::assertIsArray/
 * assertArrayHasKey narrow that for PHPStan (via phpstan/phpstan-phpunit)
 * the same way an `if` would, without resorting to an inline @var
 * override.
 *
 * @param  array<array-key, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}>|ArrayAccess<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}>  $history
 */
function requestAt(array|ArrayAccess $history, int $index): RequestInterface
{
    Assert::assertIsArray($history);
    Assert::assertArrayHasKey($index, $history);

    return $history[$index]['request'];
}

/**
 * @param  array<array-key, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}>|ArrayAccess<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}>  $history
 */
function historyCount(array|ArrayAccess $history): int
{
    Assert::assertIsArray($history);

    return count($history);
}

afterEach(function (): void {
    putenv(Client::SESSION_COOKIE_ENV_VAR);
});

it('sends an explicit session cookie on every request', function (): void {
    $mock = new MockHandler([new Response(200, [], '{"version":"dev"}')]);
    $stack = HandlerStack::create($mock);
    $client = new Client('https://example.test', 'from-constructor', handlerStack: $stack);
    attachHistory($stack, $history);

    $client->meta->getVersion();

    expect(requestAt($history, 0)->getHeaderLine('Cookie'))->toBe('session=from-constructor');
});

it('falls back to the environment variable when no cookie is passed', function (): void {
    putenv(Client::SESSION_COOKIE_ENV_VAR.'=from-env');

    $mock = new MockHandler([new Response(200, [], '{"version":"dev"}')]);
    $stack = HandlerStack::create($mock);
    $client = new Client('https://example.test', handlerStack: $stack);
    attachHistory($stack, $history);

    $client->meta->getVersion();

    expect(requestAt($history, 0)->getHeaderLine('Cookie'))->toBe('session=from-env');
});

it('lets an explicit cookie override the environment variable', function (): void {
    putenv(Client::SESSION_COOKIE_ENV_VAR.'=from-env');

    $mock = new MockHandler([new Response(200, [], '{"version":"dev"}')]);
    $stack = HandlerStack::create($mock);
    $client = new Client('https://example.test', 'from-constructor', handlerStack: $stack);
    attachHistory($stack, $history);

    $client->meta->getVersion();

    expect(requestAt($history, 0)->getHeaderLine('Cookie'))->toBe('session=from-constructor');
});

it('sends no cookie header when none is available', function (): void {
    $mock = new MockHandler([new Response(200, [], '{"version":"dev"}')]);
    $stack = HandlerStack::create($mock);
    $client = new Client('https://example.test', handlerStack: $stack);
    attachHistory($stack, $history);

    $client->meta->getVersion();

    expect(requestAt($history, 0)->hasHeader('Cookie'))->toBeFalse();
});

it('retries a 5xx response and succeeds on the retry', function (): void {
    $mock = new MockHandler([
        new Response(503, ['Retry-After' => '0']),
        new Response(200, [], '{"version":"dev"}'),
    ]);
    $stack = HandlerStack::create($mock);
    $client = new Client('https://example.test', handlerStack: $stack);
    attachHistory($stack, $history);

    $client->meta->getVersion();

    expect(historyCount($history))->toBe(2);
});

it('never retries a 400', function (): void {
    $mock = new MockHandler([new Response(400, [], '{"code":"bad_request","message":"nope"}')]);
    $stack = HandlerStack::create($mock);
    $client = new Client('https://example.test', handlerStack: $stack);
    attachHistory($stack, $history);

    try {
        $client->meta->getVersion();
        Assert::fail('expected an ApiException');
    } catch (ApiException) {
        // expected -- fall through to the assertion below
    }

    expect(historyCount($history))->toBe(1);
});

it('decodes an error response into an api error', function (): void {
    $mock = new MockHandler([
        new Response(401, ['X-Request-Id' => 'req-42'], '{"code":"unauthorized","message":"session expired"}'),
    ]);
    $stack = HandlerStack::create($mock);
    $client = new Client('https://example.test', 'stale-cookie', handlerStack: $stack, maxRetries: 0);

    try {
        $client->repos->listRepos();
        Assert::fail('expected an ApiException');
    } catch (ApiException $exception) {
        $apiError = ApiError::fromGeneratedException($exception);
    }

    expect($apiError->statusCode)->toBe(401)
        ->and($apiError->apiCode)->toBe('unauthorized')
        ->and($apiError->requestId)->toBe('req-42')
        ->and($apiError->getMessage())->toContain('session expired');
});
