<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PipelineAnalytics\Retry\RetryMiddleware;

function retryTestRequest(): Request
{
    return new Request('GET', 'https://example.test/api/version');
}

it('retries a 429', function (): void {
    $decider = (new RetryMiddleware(maxRetries: 3))->decider();

    expect($decider(0, retryTestRequest(), new Response(429)))->toBeTrue();
});

it('retries a 5xx', function (): void {
    $decider = (new RetryMiddleware(maxRetries: 3))->decider();

    expect($decider(0, retryTestRequest(), new Response(503)))->toBeTrue();
});

it('never retries an otherwise 4xx', function (): void {
    $decider = (new RetryMiddleware(maxRetries: 3))->decider();

    expect($decider(0, retryTestRequest(), new Response(404)))->toBeFalse();
    expect($decider(0, retryTestRequest(), new Response(400)))->toBeFalse();
});

it('retries a connection failure', function (): void {
    $decider = (new RetryMiddleware(maxRetries: 3))->decider();
    $request = retryTestRequest();
    $exception = new ConnectException('connection reset', $request);

    expect($decider(0, $request, null, $exception))->toBeTrue();
});

it('never retries any other request exception', function (): void {
    $decider = (new RetryMiddleware(maxRetries: 3))->decider();
    $request = retryTestRequest();
    $exception = RequestException::create($request, new Response(400));

    expect($decider(0, $request, new Response(400), $exception))->toBeFalse();
});

it('stops once max retries is reached', function (): void {
    $decider = (new RetryMiddleware(maxRetries: 2))->decider();
    $request = retryTestRequest();

    expect($decider(0, $request, new Response(500)))->toBeTrue();
    expect($decider(1, $request, new Response(500)))->toBeTrue();
    expect($decider(2, $request, new Response(500)))->toBeFalse();
});

it('honors retry-after in seconds', function (): void {
    $delay = (new RetryMiddleware)->delay();

    expect($delay(0, new Response(429, ['Retry-After' => '5'])))->toBe(5000);
});

it('honors retry-after as an http date', function (): void {
    $delay = (new RetryMiddleware)->delay();
    // The literal IMF-fixdate format, not the deprecated (as of PHP
    // 8.5) DateTimeInterface::RFC7231 constant -- see the comment in
    // RetryMiddleware::retryAfterMillis().
    $future = (new DateTimeImmutable('+10 seconds', new DateTimeZone('GMT')))->format('D, d M Y H:i:s \G\M\T');

    $millis = $delay(0, new Response(503, ['Retry-After' => $future]));

    // Allow a little slack for the time it took this test to run.
    expect($millis)->toBeGreaterThan(7000)
        ->and($millis)->toBeLessThanOrEqual(10000);
});

it('falls back to exponential backoff with jitter without retry-after', function (): void {
    $delay = (new RetryMiddleware(baseDelaySeconds: 1.0))->delay();

    $first = $delay(0, null);
    $second = $delay(1, null);

    // base=1000ms: attempt 0 draws jitter from [0,1000] then halves,
    // attempt 1 from [0,2000] then halves -- second is drawn from a
    // wider range than first, so it should typically run larger, but
    // both are bounded well below what a hung test would imply.
    expect($first)->toBeGreaterThanOrEqual(0)
        ->and($first)->toBeLessThanOrEqual(1000)
        ->and($second)->toBeGreaterThanOrEqual(0)
        ->and($second)->toBeLessThanOrEqual(2000);
});
