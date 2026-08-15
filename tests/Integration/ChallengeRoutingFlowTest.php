<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\ChallengeHandler;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\DefaultChallengePresentation;
use Supamask\Challenge\InMemoryChallengeStore;
use Supamask\Challenge\SessionVerification;
use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Config;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Http\Request;
use Supamask\Routing\RoutePolicy;

final class ChallengeRoutingFlowTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [];
        $_POST = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_POST = [];
        $_SESSION = [];
        parent::tearDown();
    }

    private function request(
        string $method,
        string $uri,
        string $host = 'example.test'
    ): Request {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = $host;

        return new Request();
    }

    private function config(): Config
    {
        return new Config([
            'challenge' => [
                'enabled' => true,
                'ttl' => 300,
                'path' => '/_supamask/challenge/',
                'middleware' => ['enabled' => true],
                'protection' => [
                    'enabled' => true,
                    'paths' => ['/pricing'],
                    'exclude_paths' => [],
                ],
                'presentation' => [
                    'title' => 'Verification',
                    'heading' => 'Verify',
                    'message' => 'Continue to pricing.',
                    'button' => 'Continue',
                ],
            ],
        ]);
    }

    public function testProtectedRequestBecomesChallenge(): void
    {
        $request = $this->request('GET', '/pricing');
        $verification = new SessionVerification();
        $middleware = new \Supamask\Middleware\ChallengeMiddleware(
            $verification,
            new RoutePolicy([
                'enabled' => true,
                'paths' => ['/pricing'],
            ])
        );

        $decision = $middleware->handle(new Context($request));

        $this->assertSame(Decision::CHALLENGE, $decision);
    }

    public function testExcludedRequestPassesWithoutVerification(): void
    {
        $request = $this->request('GET', '/health');
        $verification = new SessionVerification();
        $middleware = new \Supamask\Middleware\ChallengeMiddleware(
            $verification,
            new RoutePolicy([
                'enabled' => true,
                'paths' => ['/*'],
                'exclude_paths' => ['/health'],
            ])
        );

        $decision = $middleware->handle(new Context($request));

        $this->assertSame(Decision::ALLOW, $decision);
    }

    public function testCompleteChallengeFlowRedirectsBackToOriginalRoute(): void
    {
        $config = $this->config();
        $store = new InMemoryChallengeStore();
        $manager = new ChallengeManager($store, 300, 300);
        $verification = new SessionVerification();

        $handler = new ChallengeHandler(
            $manager,
            $verification,
            $config,
            new DefaultChallengePresentation(),
        );

        $request = $this->request('GET', '/pricing');
        $redirect = $handler->create($request);

        $this->assertSame(302, $redirect->status());

        $challengeLocation = $redirect->headers()['Location'];
        $this->assertMatchesRegularExpression(
            '#^/_supamask/challenge/[a-f0-9]{12}$#',
            $challengeLocation
        );

        $this->request('GET', $challengeLocation);
        $page = $handler->handle(new Request());

        $this->assertSame(200, $page->status());
        $this->assertStringContainsString('name="token"', $page->body());

        preg_match(
            '/name="token" value="([^"]+)"/',
            $page->body(),
            $matches
        );

        $this->assertNotEmpty($matches[1]);

        $this->request('POST', $challengeLocation);
        $_POST['token'] = $matches[1];

        $verified = $handler->handle(new Request());

        $this->assertSame(302, $verified->status());
        $this->assertSame('/pricing', $verified->headers()['Location']);
        $this->assertTrue($verification->isVerified());
    }

    public function testConsumedChallengeCannotBeReplayed(): void
    {
        $config = $this->config();
        $store = new InMemoryChallengeStore();
        $manager = new ChallengeManager($store, 300, 300);
        $verification = new SessionVerification();

        $handler = new ChallengeHandler(
            $manager,
            $verification,
            $config,
            new DefaultChallengePresentation(),
        );

        $this->request('GET', '/pricing');
        $redirect = $handler->create(new Request());
        $location = $redirect->headers()['Location'];

        $this->request('GET', $location);
        $page = $handler->handle(new Request());

        preg_match('/name="token" value="([^"]+)"/', $page->body(), $matches);
        $token = $matches[1];

        $this->request('POST', $location);
        $_POST['token'] = $token;
        $first = $handler->handle(new Request());

        $this->assertSame(302, $first->status());

        $this->request('POST', $location);
        $_POST['token'] = $token;
        $second = $handler->handle(new Request());

        $this->assertSame(404, $second->status());
    }

    public function testVerifiedSessionAllowsProtectedRoute(): void
    {
        $verification = new SessionVerification();
        $verification->markVerified(300);

        $middleware = new \Supamask\Middleware\ChallengeMiddleware(
            $verification,
            new RoutePolicy([
                'enabled' => true,
                'paths' => ['/pricing'],
            ])
        );

        $decision = $middleware->handle(
            new Context($this->request('GET', '/pricing'))
        );

        $this->assertSame(Decision::ALLOW, $decision);
    }
}
