<?php

declare(strict_types=1);

namespace PipelineAnalytics\Tests\Contract;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PipelineAnalytics\Client;

/**
 * Runs the client against a Prism mock server generated from
 * pipeline-analytics's own pinned spec (see ci.yml's `contract` job) --
 * never a hand-rolled stub (rules/sdk-generation.md's "testing against
 * the spec, not a hand-written stub"). This proves the client's requests
 * conform to the spec's shape; it says nothing about whether the real
 * server still matches that spec.
 *
 * Run locally with:
 *   docker run -d -p 4010:4010 -v "$(pwd)/openapi:/spec:ro" \
 *     stoplight/prism:5 mock -h 0.0.0.0 -m false /spec/openapi.yaml
 *
 * @internal
 */
final class ClientTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $baseUrl = getenv('PIPELINE_ANALYTICS_BASE_URL');
        if ($baseUrl === false) {
            self::markTestSkipped('PIPELINE_ANALYTICS_BASE_URL must point at a running Prism mock');
        }

        $this->client = new Client($baseUrl, 'prism-does-not-check-this');
    }

    #[Test]
    public function fetches_the_servers_version(): void
    {
        // Prism's mock fills a plain `type: string` schema with the
        // literal "string" when the spec gives it no example -- a
        // real, deterministic value to assert on rather than just a
        // type check PHPStan already knows is always true.
        self::assertSame('string', $this->client->meta->getVersion()->getVersion());
    }

    #[Test]
    public function walks_the_repos_pagination(): void
    {
        $ids = [];
        foreach ($this->client->listRepos() as $repo) {
            $ids[] = $repo->getId();

            // Prism's mock keeps hasMore=true from its own spec example
            // indefinitely -- this walks a handful of real pages to
            // prove the iterator's request/response cycle actually
            // works against the spec, not to drain a mock that never
            // ends.
            if (\count($ids) >= 3) {
                break;
            }
        }

        self::assertNotEmpty($ids);
    }

    #[Test]
    public function calls_an_authenticated_endpoint(): void
    {
        $usage = $this->client->insights->getGitHubRateLimitInsights();

        self::assertIsArray($usage);
    }
}
