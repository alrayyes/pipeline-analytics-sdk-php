<?php

declare(strict_types=1);

namespace PipelineAnalytics\Exception;

use PipelineAnalytics\Generated\ApiException;
use Throwable;

/**
 * Thrown for any pipeline-analytics response carrying an error body --
 * every 4xx/5xx in the spec shares the same {code, message} shape
 * (rules/sdk-generation.md's "Client shape": map errors to typed
 * exceptions carrying the HTTP status, any request ID, and the parsed
 * error body, never a bare string or the raw response).
 */
final class ApiError extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $apiCode,
        string $apiMessage,
        public readonly ?string $requestId = null,
        ?Throwable $previous = null,
    ) {
        $message = \sprintf('pipeline-analytics: %d %s: %s', $statusCode, $apiCode, $apiMessage);
        if ($requestId !== null) {
            $message .= " (request {$requestId})";
        }

        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Builds an ApiError from the ApiException the generated Api classes
     * throw on any non-2xx response. Prefers the deserialized Error model
     * openapi-generator attaches for status codes the spec documents
     * explicitly; falls back to decoding the raw JSON body itself for a
     * status code the spec doesn't list (a 429 or 500, say), since the
     * generated code never deserializes those.
     */
    public static function fromGeneratedException(ApiException $exception): self
    {
        $statusCode = $exception->getCode();
        [$code, $message] = self::decodeBody($exception);

        return new self(
            statusCode: $statusCode,
            apiCode: $code,
            apiMessage: $message,
            requestId: self::findRequestId($exception->getResponseHeaders() ?? []),
            previous: $exception,
        );
    }

    /**
     * @return array{0: string, 1: string} [code, message]
     */
    private static function decodeBody(ApiException $exception): array
    {
        $responseObject = $exception->getResponseObject();
        if (\is_object($responseObject) && method_exists($responseObject, 'getCode') && method_exists($responseObject, 'getMessage')) {
            return [(string) $responseObject->getCode(), (string) $responseObject->getMessage()];
        }

        $body = $exception->getResponseBody();
        $raw = \is_string($body) ? $body : (string) json_encode($body);
        $decoded = json_decode($raw, true);
        if (\is_array($decoded) && isset($decoded['code'], $decoded['message'])) {
            return [(string) $decoded['code'], (string) $decoded['message']];
        }

        return ['unknown', $exception->getMessage()];
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     */
    private static function findRequestId(array $headers): ?string
    {
        foreach ($headers as $name => $values) {
            if (strcasecmp($name, 'X-Request-Id') === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
