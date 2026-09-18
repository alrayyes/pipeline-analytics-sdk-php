<?php

declare(strict_types=1);

use PipelineAnalytics\Exception\ApiError;
use PipelineAnalytics\Generated\ApiException;
use PipelineAnalytics\Generated\Model\Error as GeneratedError;

it('prefers the deserialized error model when one was attached', function (): void {
    $exception = new ApiException('[401] ...', 401, ['X-Request-Id' => ['req-1']], '{"code":"unauthorized","message":"session expired"}');
    $exception->setResponseObject(new GeneratedError(['code' => 'unauthorized', 'message' => 'session expired']));

    $apiError = ApiError::fromGeneratedException($exception);

    expect($apiError->statusCode)->toBe(401)
        ->and($apiError->apiCode)->toBe('unauthorized')
        ->and($apiError->requestId)->toBe('req-1');
});

it('decodes the raw body when no model was attached', function (): void {
    // The status code the spec doesn't document explicitly for this
    // operation (a 429, say) -- the generated Api classes never
    // deserialize those, so ApiError has to decode the JSON itself.
    $exception = new ApiException('[429] ...', 429, [], '{"code":"rate_limited","message":"slow down"}');

    $apiError = ApiError::fromGeneratedException($exception);

    expect($apiError->statusCode)->toBe(429)
        ->and($apiError->apiCode)->toBe('rate_limited')
        ->and($apiError->requestId)->toBeNull();
});

it("falls back gracefully when the body isn't the expected shape", function (): void {
    $exception = new ApiException('[500] boom', 500, [], 'not json at all');

    $apiError = ApiError::fromGeneratedException($exception);

    expect($apiError->statusCode)->toBe(500)
        ->and($apiError->apiCode)->toBe('unknown');
});

it('finds the request id header case-insensitively', function (): void {
    $exception = new ApiException('[500] boom', 500, ['x-request-id' => ['req-lower']], '{"code":"x","message":"y"}');

    $apiError = ApiError::fromGeneratedException($exception);

    expect($apiError->requestId)->toBe('req-lower');
});

it('includes the request id in the message when present', function (): void {
    $apiError = new ApiError(500, 'boom', 'something broke', 'req-99');

    expect($apiError->getMessage())->toContain('req-99');
});

it('omits the request id from the message when absent', function (): void {
    $apiError = new ApiError(500, 'boom', 'something broke');

    expect($apiError->getMessage())->not->toContain('request');
});
