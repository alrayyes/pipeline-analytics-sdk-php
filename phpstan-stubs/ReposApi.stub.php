<?php

namespace PipelineAnalytics\Generated\Api;

/**
 * Corrects a PHPStan-visible type mismatch in generated code without
 * touching generated/ itself (rules/sdk-generation.md's "Generated vs
 * hand-written"). openapi-generator's php templates document
 * ReposApi::listRepos()'s $forge parameter as
 * `\PipelineAnalytics\Generated\Model\Forge|null`, but Forge is never
 * actually instantiated -- it's a plain container of string constants
 * (Forge::GITHUB === 'github'), and the real runtime expectation is a
 * plain string. This stub only overrides that one method's signature;
 * PHPStan still uses the real class for everything else.
 */
class ReposApi
{
    /**
     * @param  string|null  $forge
     * @param  int|null  $limit
     * @param  int|null  $offset
     */
    public function listRepos($forge = null, $limit = null, $offset = 0, string $contentType = 'application/json'): mixed {}
}
