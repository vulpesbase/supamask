<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\InMemoryChallengeStore;
use Supamask\Challenge\SessionVerification;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Http\Request;

/**
 * Integration tests for the user-facing challenge URL cleanup (Workstream 2).
 *
 * Verifies that:
 *   1. The configurable presentation_path is the exclusive URL at which the
 *      browser-facing challenge page is served and POSTed to.
 *   2. The legacy internal path (/_supamask/challenge/) does not intercept
 *      requests when a presentation_path is configured.
 *   3. The default route (/challenge/) is used when nothing is configured.
 *   4. A custom presentation_path is honoured end-to-end.
 */
final class ChallengeUserFacingRouteTest extends TestCase
{
    private InMemoryChallengeStore $store;
    private SessionVerification $verification;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/protected',
            'REMOTE_ADDR'    => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ];
        $_POST = [];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();

        $this->store        = new InMemoryChallengeStore();
        $this->verification = new SessionVerification();
    }

    public function testDefaultPresentationPathIsChallenge(): void
    {
        $kernel = $this->kernel([]);

        $redirect = $kernel->handle(new Request());
        $this->assertNotNull($redirect);
        $this->assertSame(302, $redirect->status());

        $location = $redirect->headers()['Location'];
        $this->assertMatchesRegularExpression('#^/[a-f0-9]{12}$#', $location);
    }

    public function testDefaultRouteServesHtmlOnGet(): void
    {
        $kernel = $this->kernel([]);

        $redirect = $kernel->handle(new Request());
        $location = $redirect->headers()['Location'];

        $_SERVER['REQUEST_URI'] = $location;
        $response = $kernel->handle(new Request());

        $this->assertSame(200, $response->status());
        $body = $response->body();
        $this->assertMatchesRegularExpression('/<form[^>]*method="post"/i', $body);
        $this->assertStringContainsString('name="token"', $body);
        
        $id = basename($location);
        $this->assertStringContainsString('action="/' . $id . '"', $body);
    }

    public function testCustomPresentationPathIsUsedForRedirect(): void
    {
        $kernel = $this->kernel(['challenge' => ['presentation_path' => '/verify/']]);

        $redirect = $kernel->handle(new Request());
        $this->assertSame(302, $redirect->status());
        $this->assertMatchesRegularExpression(
            '#^/verify/[a-f0-9]{12}$#',
            $redirect->headers()['Location']
        );
    }

    public function testCustomPresentationPathServesAndVerifiesEndToEnd(): void
    {
        $kernel = $this->kernel(['challenge' => ['presentation_path' => '/verify/']]);

        $redirect = $kernel->handle(new Request());
        $location = $redirect->headers()['Location'];

        $_SERVER['REQUEST_URI'] = $location;
        $page = $kernel->handle(new Request());

        $this->assertSame(200, $page->status());
        $body = $page->body();
        $this->assertStringContainsString('action="/verify/', $body);

        preg_match('/name="token" value="([^"]+)"/', $body, $m);
        $this->assertNotEmpty($m, 'Token not found in rendered HTML');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['token'] = $m[1];
        $verified = $kernel->handle(new Request());

        $this->assertSame(200, $verified->status());
        $this->assertStringContainsString('state==="success"', $verified->body());
        $this->assertTrue($this->verification->isVerified());
    }

    public function testLegacyInternalPathDoesNotMatchWhenPublicRouteIsConfigured(): void
    {
        $kernel = $this->kernel([]);

        $redirect    = $kernel->handle(new Request());
        $challengeId = basename($redirect->headers()['Location']);
        $challenge   = $this->store->find($challengeId);
        $this->assertNotNull($challenge);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/_supamask/challenge/' . $challengeId;
        $_POST = ['token' => $challenge->verificationToken()];

        $response = $kernel->handle(new Request());

        $this->assertNull($response, 'Legacy internal path must not be intercepted by the challenge handler');
        $this->assertFalse($this->verification->isVerified(), 'Session must NOT be verified via the legacy path');
        $this->assertTrue($this->store->find($challengeId)->isPending(), 'Challenge must remain PENDING');
    }

    public function testLegacyInternalPathDoesNotServeHtml(): void
    {
        $kernel = $this->kernel([]);

        $redirect    = $kernel->handle(new Request());
        $challengeId = basename($redirect->headers()['Location']);

        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challengeId;
        $response = $kernel->handle(new Request());

        $this->assertNull($response, 'Legacy internal path must not serve challenge HTML');
    }

    public function testPresentationPathTakesPrecedenceOverLegacyPath(): void
    {
        $kernel = $this->kernel([
            'challenge' => [
                'path'              => '/_supamask/challenge/',
                'presentation_path' => '/root-custom/',
            ],
        ]);

        $redirect = $kernel->handle(new Request());
        $this->assertMatchesRegularExpression(
            '#^/root-custom/[a-f0-9]{12}$#',
            $redirect->headers()['Location'],
            'presentation_path must win over the legacy path key'
        );
    }

    public function testApplicationRouteSafety(): void
    {
        $kernel = $this->kernel([]);

        // Arbitrary application routes
        $routes = ['/pricing', '/about', '/login', '/index.php', '/some-real-application-route'];

        foreach ($routes as $route) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = $route;
            
            // By returning null, Kernel allows these to pass through to the application
            $this->assertNull(
                $kernel->handle(new Request()), 
                "Legitimate route {$route} must not be swallowed"
            );
        }
    }

    private function kernel(array $extraConfig): Kernel
    {
        $baseConfig = [
            'challenge' => [
                'middleware'  => ['enabled' => true],
                'proof_of_work' => ['enabled' => false],
                'protection'  => ['enabled' => true, 'paths' => ['/protected']],
            ],
            'ip_blocking'  => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
        ];

        $config = array_replace_recursive($baseConfig, $extraConfig);

        return new Kernel(
            new Config($config),
            new ChallengeManager($this->store, 300, 300),
            $this->verification,
        );
    }
}
