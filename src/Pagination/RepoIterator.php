<?php

declare(strict_types=1);

namespace PipelineAnalytics\Pagination;

use Generator;
use IteratorAggregate;
use PipelineAnalytics\Exception\ApiError;
use PipelineAnalytics\Generated\Api\ReposApi;
use PipelineAnalytics\Generated\ApiException;
use PipelineAnalytics\Generated\Model\Repo;
use PipelineAnalytics\Generated\Model\RepoList;

/**
 * Walks every page of GET /api/repos, the only real pagination in the
 * spec (rules/sdk-generation.md's "Client shape": a real iterator, not
 * "here's a page, loop yourself"). Range over it with a plain foreach --
 * IteratorAggregate makes this directly iterable, so a stopped-early
 * walk surfaces as a thrown ApiError from within the loop, same as any
 * other API call, with no separate Err()-style check needed afterward.
 *
 * @implements IteratorAggregate<int, Repo>
 */
final readonly class RepoIterator implements IteratorAggregate
{
    private const DEFAULT_PAGE_SIZE = 50;

    public function __construct(
        private ReposApi $reposApi,
        private ?string $forge = null,
        private int $pageSize = self::DEFAULT_PAGE_SIZE,
    ) {}

    /**
     * @return Generator<int, Repo>
     */
    public function getIterator(): Generator
    {
        $offset = 0;

        while (true) {
            try {
                /** @var RepoList $page */
                $page = $this->reposApi->listRepos($this->forge, $this->pageSize, $offset);
            } catch (ApiException $exception) {
                throw ApiError::fromGeneratedException($exception);
            }

            $repos = $page->getRepos();
            foreach ($repos as $repo) {
                yield $repo;
            }

            // An empty page stops the walk even if hasMore is somehow
            // still true -- there's nothing left to advance the offset
            // past, and looping forever on a server bug is worse than
            // returning a short result.
            if (! $page->getHasMore() || count($repos) === 0) {
                return;
            }

            $offset += count($repos);
        }
    }
}
