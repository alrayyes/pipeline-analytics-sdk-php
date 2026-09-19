# pipeline-analytics-sdk-php

[![CI](https://github.com/alrayyes/pipeline-analytics-sdk-php/actions/workflows/ci.yml/badge.svg)](https://github.com/alrayyes/pipeline-analytics-sdk-php/actions/workflows/ci.yml)
[![Codecov](https://codecov.io/gh/alrayyes/pipeline-analytics-sdk-php/graph/badge.svg)](https://codecov.io/gh/alrayyes/pipeline-analytics-sdk-php)
[![docs](https://img.shields.io/badge/docs-phpdoc-blue)](https://alrayyes.github.io/pipeline-analytics-sdk-php/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A PHP client for [pipeline-analytics](https://github.com/alrayyes/pipeline-analytics)'s
REST API, generated from its OpenAPI spec with
[openapi-generator](https://openapi-generator.tech/)'s `php` target and a
Guzzle HTTP transport. It saves you from hand-rolling HTTP requests, retries
and pagination against the API yourself.

## Requirements

- PHP 8.2 or later, with the `json` extension (bundled by default).
- [Composer](https://getcomposer.org/).
- A running pipeline-analytics instance.
- A session cookie for that instance (see "Authentication" below) — every
  endpoint except `getVersion` and the two webhook receivers needs one.

## Installation

```sh
composer require alrayyes/pipeline-analytics-sdk-php
```

This package isn't on Packagist yet — see "Publishing" below. Until it is,
require it as a VCS repository pointed at this Git URL, or `composer require`
a specific commit.

## Authentication

pipeline-analytics authenticates browsers with
[WebAuthn](https://webauthn.guide/), not an API token — there's no headless
credential-grant flow in its spec, so this SDK can't log in for you. Get a
session cookie by logging into the dashboard in a browser, opening dev tools,
and copying the `session` cookie's value. Pass it to the constructor or set
`PIPELINE_ANALYTICS_SESSION` in the environment:

```php
use PipelineAnalytics\Client;

$client = new Client(
    'https://pipeline-analytics.example.com',
    sessionCookie: getenv('PIPELINE_ANALYTICS_SESSION'),
);
```

A session cookie expires the same way it would in a browser; there's nothing
in this SDK to refresh it automatically. A real headless token flow is a
known gap, tracked upstream as
[alrayyes/pipeline-analytics#178](https://github.com/alrayyes/pipeline-analytics/issues/178) —
don't work around it, just expect the browser round trip for now.

## Usage

`getVersion` needs no session and is a good first call to prove the client
reaches the server at all:

```php
use PipelineAnalytics\Client;

$client = new Client('https://pipeline-analytics.example.com');

$version = $client->meta->getVersion();
echo "server version: {$version->getVersion()}\n";
```

Listing tracked repos needs a session, and demonstrates the pagination
iterator and error handling:

```php
use PipelineAnalytics\Client;
use PipelineAnalytics\Exception\ApiError;

$client = new Client(
    'https://pipeline-analytics.example.com',
    sessionCookie: getenv('PIPELINE_ANALYTICS_SESSION'),
);

try {
    foreach ($client->listRepos() as $repo) {
        echo "{$repo->getIdentifier()}: {$repo->getIngestionStatus()}\n";
    }
} catch (ApiError $error) {
    if ($error->statusCode === 401) {
        exit('session cookie expired or invalid');
    }

    throw $error;
}
```

Every other operation is a direct call on the matching generated Api class —
`$client->repos`, `$client->pipelines`, `$client->insights`, `$client->auth`,
`$client->webhooks` — each named after the spec's own tags. These throw the
generated `PipelineAnalytics\Generated\ApiException` on any non-2xx response;
convert it to a typed error uniformly with `ApiError::fromGeneratedException()`:

```php
use PipelineAnalytics\Exception\ApiError;
use PipelineAnalytics\Generated\ApiException;

try {
    $usage = $client->repos->getRepoUsage($repoId);
} catch (ApiException $exception) {
    throw ApiError::fromGeneratedException($exception);
}
```

`ApiError` carries the HTTP status (`statusCode`), the API's own error code
and message (`apiCode`, `getMessage()`), and any `X-Request-Id` response
header (`requestId`).

The client retries a `429` or `5xx` response with exponential backoff and
jitter (honoring a server-sent `Retry-After`), and never retries any other
`4xx`. Tune it via the constructor's `$maxRetries`/`$retryBaseDelaySeconds`,
or swap the underlying Guzzle handler stack entirely with `$handlerStack`.

## Regenerating the client

See [CONTRIBUTING.md](CONTRIBUTING.md) — the generated code is pinned to a
specific pipeline-analytics commit and shouldn't drift from it silently.

## Publishing

This package isn't claimed on [Packagist](https://packagist.org/) yet —
that's a manual step (linking a GitHub webhook to a Packagist account) left
for a maintainer to do once the SDK is ready for general use, not something
done as part of this bootstrap.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for building, testing and the release
process.

## License

[MIT](LICENSE) — a permissive license for the client, independent of
pipeline-analytics' own AGPL-3.0.
