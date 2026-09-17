<?php

declare(strict_types=1);

namespace PipelineAnalytics\Tests\Retry;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PipelineAnalytics\Retry\RetryMiddleware;

final class RetryMiddlewareTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        $this->request = new Request('GET', 'https://example.test/api/version');
    }

    #[Test]
    public function retries_a429(): void
    {
        $decider = (new RetryMiddleware(maxRetries: 3))->decider();

        self::assertTrue($decider(0, $this->request, new Response(429)));
    }

    #[Test]
    public function retries_a5xx(): void
    {
        $decider = (new RetryMiddleware(maxRetries: 3))->decider();

        self::assertTrue($decider(0, $this->request, new Response(503)));
    }

    #[Test]
    public function never_retries_an_otherwise4xx(): void
    {
        $decider = (new RetryMiddleware(maxRetries: 3))->decider();

        self::assertFalse($decider(0, $this->request, new Response(404)));
        self::assertFalse($decider(0, $this->request, new Response(400)));
    }

    #[Test]
    public function retries_a_connection_failure(): void
    {
        $decider = (new RetryMiddleware(maxRetries: 3))->decider();
        $exception = new ConnectException('connection reset', $this->request);

        self::assertTrue($decider(0, $this->request, null, $exception));
    }

    #[Test]
    public function never_retries_any_other_request_exception(): void
    {
        $decider = (new RetryMiddleware(maxRetries: 3))->decider();
        $exception = RequestException::create($this->request, new Response(400));

        self::assertFalse($decider(0, $this->request, new Response(400), $exception));
    }

    #[Test]
    public function stops_once_max_retries_is_reached(): void
    {
        $decider = (new RetryMiddleware(maxRetries: 2))->decider();

        self::assertTrue($decider(0, $this->request, new Response(500)));
        self::assertTrue($decider(1, $this->request, new Response(500)));
        self::assertFalse($decider(2, $this->request, new Response(500)));
    }

    #[Test]
    public function honors_retry_after_in_seconds(): void
    {
        $delay = (new RetryMiddleware)->delay();

        self::assertSame(5000, $delay(0, new Response(429, ['Retry-After' => '5'])));
    }

    #[Test]
    public function honors_retry_after_as_an_http_date(): void
    {
        $delay = (new RetryMiddleware)->delay();
        // The literal IMF-fixdate format, not the deprecated (as of PHP
        // 8.5) DateTimeInterface::RFC7231 constant -- see the comment in
        // RetryMiddleware::retryAfterMillis().
        $future = (new \DateTimeImmutable('+10 seconds', new \DateTimeZone('GMT')))->format('D, d M Y H:i:s \G\M\T');

        $millis = $delay(0, new Response(503, ['Retry-After' => $future]));

        // Allow a little slack for the time it took this test to run.
        self::assertGreaterThan(7000, $millis);
        self::assertLessThanOrEqual(10000, $millis);
    }

    #[Test]
    public function falls_back_to_exponential_backoff_with_jitter_without_retry_after(): void
    {
        $delay = (new RetryMiddleware(baseDelaySeconds: 1.0))->delay();

        $first = $delay(0, null);
        $second = $delay(1, null);

        // base=1000ms: attempt 0 draws jitter from [0,1000] then halves,
        // attempt 1 from [0,2000] then halves -- second is drawn from a
        // wider range than first, so it should typically run larger, but
        // both are bounded well below what a hung test would imply.
        self::assertGreaterThanOrEqual(0, $first);
        self::assertLessThanOrEqual(1000, $first);
        self::assertGreaterThanOrEqual(0, $second);
        self::assertLessThanOrEqual(2000, $second);
    }
}
