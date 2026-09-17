<?php

declare(strict_types=1);

namespace PipelineAnalytics\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PipelineAnalytics\Exception\ApiError;
use PipelineAnalytics\Generated\ApiException;
use PipelineAnalytics\Generated\Model\Error as GeneratedError;

final class ApiErrorTest extends TestCase
{
    #[Test]
    public function prefers_the_deserialized_error_model_when_one_was_attached(): void
    {
        $exception = new ApiException('[401] ...', 401, ['X-Request-Id' => ['req-1']], '{"code":"unauthorized","message":"session expired"}');
        $exception->setResponseObject(new GeneratedError(['code' => 'unauthorized', 'message' => 'session expired']));

        $apiError = ApiError::fromGeneratedException($exception);

        self::assertSame(401, $apiError->statusCode);
        self::assertSame('unauthorized', $apiError->apiCode);
        self::assertSame('req-1', $apiError->requestId);
    }

    #[Test]
    public function decodes_the_raw_body_when_no_model_was_attached(): void
    {
        // The status code the spec doesn't document explicitly for this
        // operation (a 429, say) -- the generated Api classes never
        // deserialize those, so ApiError has to decode the JSON itself.
        $exception = new ApiException('[429] ...', 429, [], '{"code":"rate_limited","message":"slow down"}');

        $apiError = ApiError::fromGeneratedException($exception);

        self::assertSame(429, $apiError->statusCode);
        self::assertSame('rate_limited', $apiError->apiCode);
        self::assertNull($apiError->requestId);
    }

    #[Test]
    public function falls_back_gracefully_when_the_body_isnt_the_expected_shape(): void
    {
        $exception = new ApiException('[500] boom', 500, [], 'not json at all');

        $apiError = ApiError::fromGeneratedException($exception);

        self::assertSame(500, $apiError->statusCode);
        self::assertSame('unknown', $apiError->apiCode);
    }

    #[Test]
    public function finds_the_request_id_header_case_insensitively(): void
    {
        $exception = new ApiException('[500] boom', 500, ['x-request-id' => ['req-lower']], '{"code":"x","message":"y"}');

        $apiError = ApiError::fromGeneratedException($exception);

        self::assertSame('req-lower', $apiError->requestId);
    }

    #[Test]
    public function message_includes_the_request_id_when_present(): void
    {
        $apiError = new ApiError(500, 'boom', 'something broke', 'req-99');

        self::assertStringContainsString('req-99', $apiError->getMessage());
    }

    #[Test]
    public function message_omits_the_request_id_when_absent(): void
    {
        $apiError = new ApiError(500, 'boom', 'something broke');

        self::assertStringNotContainsString('request', $apiError->getMessage());
    }
}
