<?php

declare(strict_types=1);

namespace PipelineAnalytics\Tests\Pagination;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PipelineAnalytics\Client;
use PipelineAnalytics\Exception\ApiError;

final class RepoIteratorTest extends TestCase
{
    /**
     * @param  array<mixed>  $data
     */
    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function walks_every_page_transparently(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->json([
                'repos' => [
                    ['id' => '1', 'forge' => 'github', 'identifier' => 'a/a', 'tokenMasked' => '****1', 'ingestionStatus' => 'active'],
                ],
                'hasMore' => true,
            ])),
            new Response(200, [], $this->json([
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

        self::assertSame(['1', '2'], $ids);
    }

    #[Test]
    public function default_page_size_is_exactly_fifty(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->json(['repos' => [], 'hasMore' => false])),
        ]);
        $stack = HandlerStack::create($mock);
        $history = [];
        $stack->push(Middleware::history($history));

        $client = new Client('https://example.test', handlerStack: $stack);

        iterator_to_array($client->listRepos());

        self::assertIsArray($history);
        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        self::assertSame('50', $query['limit']);
    }

    #[Test]
    public function offset_accumulates_across_more_than_two_pages(): void
    {
        // Three single-item pages: a page's offset getting reset to just
        // that page's own count, instead of accumulating, only shows up
        // once there's a third request to compare against -- the second
        // request's offset is 1 either way.
        $mock = new MockHandler([
            new Response(200, [], $this->json(['repos' => [
                ['id' => '1', 'forge' => 'github', 'identifier' => 'a/a', 'tokenMasked' => '****1', 'ingestionStatus' => 'active'],
            ], 'hasMore' => true])),
            new Response(200, [], $this->json(['repos' => [
                ['id' => '2', 'forge' => 'github', 'identifier' => 'b/b', 'tokenMasked' => '****2', 'ingestionStatus' => 'active'],
            ], 'hasMore' => true])),
            new Response(200, [], $this->json(['repos' => [
                ['id' => '3', 'forge' => 'github', 'identifier' => 'c/c', 'tokenMasked' => '****3', 'ingestionStatus' => 'active'],
            ], 'hasMore' => false])),
        ]);
        $stack = HandlerStack::create($mock);
        $history = [];
        $stack->push(Middleware::history($history));

        $client = new Client('https://example.test', handlerStack: $stack);

        iterator_to_array($client->listRepos());

        self::assertIsArray($history);
        self::assertCount(3, $history);
        parse_str($history[2]['request']->getUri()->getQuery(), $thirdRequestQuery);
        self::assertSame('2', $thirdRequestQuery['offset']);
    }

    #[Test]
    public function stops_on_an_empty_page_even_if_has_more_is_true(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->json(['repos' => [], 'hasMore' => true])),
        ]);

        $client = new Client('https://example.test', handlerStack: HandlerStack::create($mock));

        $ids = iterator_to_array($client->listRepos());

        self::assertSame([], $ids);
    }

    #[Test]
    public function surfaces_an_api_error_partway_through_the_walk(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->json([
                'repos' => [
                    ['id' => '1', 'forge' => 'github', 'identifier' => 'a/a', 'tokenMasked' => '****1', 'ingestionStatus' => 'active'],
                ],
                'hasMore' => true,
            ])),
            new Response(401, [], $this->json(['code' => 'unauthorized', 'message' => 'session expired'])),
        ]);

        $client = new Client('https://example.test', 'cookie', handlerStack: HandlerStack::create($mock), maxRetries: 0);

        $seen = [];
        try {
            foreach ($client->listRepos() as $repo) {
                $seen[] = $repo->getId();
            }
            self::fail('expected an ApiError partway through the walk');
        } catch (ApiError $error) {
            self::assertSame(401, $error->statusCode);
        }

        self::assertSame(['1'], $seen);
    }
}
