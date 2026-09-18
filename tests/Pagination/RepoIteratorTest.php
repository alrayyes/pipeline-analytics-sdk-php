<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use PipelineAnalytics\Client;
use PipelineAnalytics\Exception\ApiError;

/**
 * @param  array<mixed>  $data
 */
function jsonBody(array $data): string
{
    return json_encode($data, JSON_THROW_ON_ERROR);
}

it('walks every page transparently', function (): void {
    $mock = new MockHandler([
        new Response(200, [], jsonBody([
            'repos' => [
                ['id' => '1', 'forge' => 'github', 'identifier' => 'a/a', 'tokenMasked' => '****1', 'ingestionStatus' => 'active'],
            ],
            'hasMore' => true,
        ])),
        new Response(200, [], jsonBody([
            'repos' => [
                ['id' => '2', 'forge' => 'github', 'identifier' => 'b/b', 'tokenMasked' => '****2', 'ingestionStatus' => 'active'],
            ],
            'hasMore' => false,
        ])),
    ]);

    $client = new Client('https://example.test', handlerStack: HandlerStack::create($mock));

    $ids = [];
    foreach ($client->listRepos() as $repo) {
        $ids[] = $repo->getId();
    }

    expect($ids)->toBe(['1', '2']);
});

it('stops on an empty page even if has-more is true', function (): void {
    $mock = new MockHandler([
        new Response(200, [], jsonBody(['repos' => [], 'hasMore' => true])),
    ]);

    $client = new Client('https://example.test', handlerStack: HandlerStack::create($mock));

    $ids = iterator_to_array($client->listRepos());

    expect($ids)->toBe([]);
});

it('surfaces an api error partway through the walk', function (): void {
    $mock = new MockHandler([
        new Response(200, [], jsonBody([
            'repos' => [
                ['id' => '1', 'forge' => 'github', 'identifier' => 'a/a', 'tokenMasked' => '****1', 'ingestionStatus' => 'active'],
            ],
            'hasMore' => true,
        ])),
        new Response(401, [], jsonBody(['code' => 'unauthorized', 'message' => 'session expired'])),
    ]);

    $client = new Client('https://example.test', 'cookie', handlerStack: HandlerStack::create($mock), maxRetries: 0);

    $seen = [];
    try {
        foreach ($client->listRepos() as $repo) {
            $seen[] = $repo->getId();
        }
        Assert::fail('expected an ApiError partway through the walk');
    } catch (ApiError $error) {
        expect($error->statusCode)->toBe(401);
    }

    expect($seen)->toBe(['1']);
});
