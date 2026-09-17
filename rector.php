<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

// Rector does the mechanical upgrades -- a PHP version bump, a deprecated
// call swapped out. Scoped to src/tests, never generated/ -- that tree is
// openapi-generator's, and a Rector-applied change there is one the next
// regeneration silently deletes (rules/sdk-generation.md's "Generated vs
// hand-written").
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
    ]);
