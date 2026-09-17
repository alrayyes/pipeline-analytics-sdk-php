<?php

declare(strict_types=1);

namespace PipelineAnalytics\Tests\Pagination;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
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
