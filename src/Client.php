<?php

declare(strict_types=1);

namespace PipelineAnalytics;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PipelineAnalytics\Generated\Api\AuthApi;
use PipelineAnalytics\Generated\Api\InsightsApi;
use PipelineAnalytics\Generated\Api\MetaApi;
use PipelineAnalytics\Generated\Api\PipelinesApi;
use PipelineAnalytics\Generated\Api\ReposApi;
use PipelineAnalytics\Generated\Api\WebhooksApi;
use PipelineAnalytics\Generated\Configuration;
use PipelineAnalytics\Pagination\RepoIterator;
use PipelineAnalytics\Retry\RetryMiddleware;
use Psr\Http\Message\RequestInterface;

/**
 * A pipeline-analytics API client (github.com/alrayyes/pipeline-analytics).
 *
 * Every operation except {@see MetaApi::getVersion()} and the two webhook
 * receivers ({@see WebhooksApi}) requires an authenticated session.
 * pipeline-analytics authenticates browsers with WebAuthn, not an API
 * token -- there is no headless credential-grant flow in the spec. Obtain
 * a session cookie by logging into the dashboard in a browser and copying
 * the "session" cookie's value; pass it to the constructor or set
 * {@see self::SESSION_COOKIE_ENV_VAR}. See the README for the full
 * explanation and an example.
 *
 * Each generated Api class is exposed as a public, readonly property
 * ({@see self::$repos}, {@see self::$pipelines}, ...) for direct,
 * one-response-at-a-time calls. {@see self::listRepos()} wraps
 * {@see ReposApi::listRepos()} in a real iterator instead, since it's
 * the only endpoint in the spec with real pagination.
 */
final readonly class Client
{
    /**
     * Environment variable the constructor falls back to when no session
     * cookie is passed explicitly.
     */
    public const SESSION_COOKIE_ENV_VAR = 'PIPELINE_ANALYTICS_SESSION';

    public MetaApi $meta;

    public ReposApi $repos;

    public PipelinesApi $pipelines;

    public InsightsApi $insights;

    public AuthApi $auth;

    public WebhooksApi $webhooks;

    /**
     * @param  string  $baseUrl  the pipeline-analytics instance's origin, e.g.
     *                           "https://pipeline-analytics.example.com"
     * @param  string|null  $sessionCookie  overrides
     *                                      {@see self::SESSION_COOKIE_ENV_VAR} when given
     * @param  ClientInterface|null  $httpClient  replaces the underlying Guzzle
     *                                            client entirely (a test double, usually); when given, the retry
     *                                            and cookie-injection middleware below are *not* applied, and
     *                                            $handlerStack/$maxRetries/$retryBaseDelaySeconds are ignored --
     *                                            the caller owns that behaviour instead
     * @param  HandlerStack|null  $handlerStack  the base handler stack the
     *                                           retry and cookie-injection middleware get pushed onto -- pass a
     *                                           stack built around a {@see MockHandler} in a
     *                                           test to exercise both against a fake transport. Defaults to a
     *                                           fresh {@see HandlerStack::create()} (the real cURL/stream
     *                                           handler). Ignored when $httpClient is given.
     * @param  int  $maxRetries  additional attempts after the first, for a
     *                           429/5xx or network failure
     * @param  float  $retryBaseDelaySeconds  the starting backoff before
     *                                        jitter and any server-sent Retry-After
     */
    public function __construct(
        string $baseUrl,
        ?string $sessionCookie = null,
        ?ClientInterface $httpClient = null,
        ?HandlerStack $handlerStack = null,
        int $maxRetries = 3,
        float $retryBaseDelaySeconds = 0.25,
    ) {
        $cookie = $sessionCookie ?? (getenv(self::SESSION_COOKIE_ENV_VAR) ?: null);

        $guzzle = $httpClient ?? $this->buildGuzzleClient($handlerStack ?? HandlerStack::create(), $cookie, $maxRetries, $retryBaseDelaySeconds);

        $config = new Configuration;
        $config->setHost(rtrim($baseUrl, '/'));

        $this->meta = new MetaApi($guzzle, $config);
        $this->repos = new ReposApi($guzzle, $config);
        $this->pipelines = new PipelinesApi($guzzle, $config);
        $this->insights = new InsightsApi($guzzle, $config);
        $this->auth = new AuthApi($guzzle, $config);
        $this->webhooks = new WebhooksApi($guzzle, $config);
    }

    /**
     * Walks every tracked repo, paging through GET /api/repos
     * transparently.
     *
     * @param  string|null  $forge  restrict the walk to one forge; null
     *                              returns every forge
     * @param  int  $pageSize  items requested per underlying page
     */
    public function listRepos(?string $forge = null, int $pageSize = 50): RepoIterator
    {
        return new RepoIterator($this->repos, $forge, $pageSize);
    }

    private function buildGuzzleClient(HandlerStack $stack, ?string $cookie, int $maxRetries, float $baseDelaySeconds): GuzzleClient
    {
        $stack->push(Middleware::mapRequest(
            static function (RequestInterface $request) use ($cookie): RequestInterface {
                if ($cookie === null || $cookie === '') {
                    return $request;
                }

                return $request->withHeader('Cookie', 'session='.$cookie);
            },
        ), 'session_cookie');

        $retry = new RetryMiddleware($maxRetries, $baseDelaySeconds);
        $stack->push(Middleware::retry($retry->decider(), $retry->delay()), 'retry');

        return new GuzzleClient(['handler' => $stack]);
    }
}
