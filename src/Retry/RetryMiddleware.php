<?php

declare(strict_types=1);

namespace PipelineAnalytics\Retry;

use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds the decider/delay pair for GuzzleHttp\Middleware::retry --
 * exponential backoff with jitter on a 429 or 5xx, honoring a
 * server-sent Retry-After (seconds or an HTTP-date, RFC 9110 section
 * 10.2.3) ahead of the computed backoff, and never retrying any other
 * 4xx (rules/sdk-generation.md's "Client shape").
 */
final readonly class RetryMiddleware
{
    public function __construct(
        private int $maxRetries = 3,
        private float $baseDelaySeconds = 0.25,
    ) {}

    /**
     * Guzzle calls this with the raw rejection reason as its fourth
     * argument (untyped -- GuzzleHttp\RetryMiddleware itself only
     * documents it as `mixed`), which in practice is always a Throwable
     * for an HTTP middleware chain -- a ConnectException for a
     * network-level failure before any response arrived, or nothing at
     * all when a response (even an error one) came back, since the retry
     * middleware sits closer to the transport than Guzzle's http_errors
     * middleware and sees the raw response first.
     *
     * @return callable(int, RequestInterface, ResponseInterface|null=, mixed=): bool
     */
    public function decider(): callable
    {
        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            mixed $exception = null,
        ): bool {
            if ($retries >= $this->maxRetries) {
                return false;
            }

            if ($exception instanceof ConnectException) {
                // A network-level failure (connection reset, timeout) is
                // retry-worthy the same as a 5xx.
                return true;
            }

            if (! $response instanceof ResponseInterface) {
                return false;
            }

            $status = $response->getStatusCode();

            return $status === 429 || $status >= 500;
        };
    }

    /**
     * @return callable(int, ResponseInterface|null=): int Delay in
     *                                                     milliseconds -- the unit GuzzleHttp\Middleware::retry expects.
     *                                                     Guzzle itself calls this with a third $request argument too;
     *                                                     it's irrelevant to the delay calculation and safely dropped.
     */
    public function delay(): callable
    {
        return function (int $retries, ?ResponseInterface $response = null): int {
            if ($response instanceof ResponseInterface) {
                $retryAfter = $this->retryAfterMillis($response);
                if ($retryAfter !== null) {
                    return $retryAfter;
                }
            }

            $backoffMillis = (int) ($this->baseDelaySeconds * 1000 * (2 ** $retries));
            $jitter = random_int(0, $backoffMillis);

            return intdiv($backoffMillis, 2) + intdiv($jitter, 2);
        };
    }

    private function retryAfterMillis(ResponseInterface $response): ?int
    {
        $header = $response->getHeaderLine('Retry-After');
        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return ((int) $header) * 1000;
        }

        // RFC 9110 section 10.2.3's IMF-fixdate, spelled out literally
        // rather than via the DateTimeInterface::RFC7231 constant -- that
        // constant is deprecated as of PHP 8.5 (it silently assumes GMT
        // and ignores the actual timezone), and an HTTP-date is always
        // GMT by definition anyway.
        $when = DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $header, new DateTimeZone('GMT'));
        if ($when === false) {
            return null;
        }

        $seconds = $when->getTimestamp() - time();

        return $seconds > 0 ? $seconds * 1000 : 0;
    }
}
