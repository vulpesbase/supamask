<?php

namespace Supamask\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Supamask\Challenge\SessionChallengeStore;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Entry\DisposableEntryManager;
use Supamask\Entry\DisposableEntryState;
use Supamask\Entry\SessionDisposableEntryRegistry;
use Supamask\Http\Request;
use Supamask\Http\Response;

final class LifecycleIntegrationTest extends TestCase
{
    private Config $config;
    private Kernel $kernel;
    private DisposableEntryManager $entryManager;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];

        $this->config = new Config([
            'challenge' => [
                'enabled' => true,
                'path' => '/_supamask/challenge/',
            ],
            'disposable' => [
                'enabled' => true,
                'slug_length' => 12,
                'ttl' => 900,
                'single_use' => true,
            ],
            'entry' => [
                'enabled' => true,
                'policy' => [
                    'seeded' => 'challenge',
                    'direct' => 'allow',
                    'referred' => 'allow',
                    'unknown' => 'allow',
                ],
            ],
        ]);

        $this->kernel = new Kernel($this->config);
        
        $this->entryManager = new DisposableEntryManager(
            new SessionDisposableEntryRegistry(),
            900,
            12,
            true
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function get(string $path): ?Response
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['HTTP_HOST'] = 'example.test';
        return $this->kernel->handle(new Request());
    }

    private function post(string $path, array $data): ?Response
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_POST = $data;
        return $this->kernel->handle(new Request());
    }

    public function testLifecycleFlow(): void
    {
        // ── A. Create ──
        $entry = $this->entryManager->generate('/dashboard');
        $slug = $entry->slug();
        $this->assertTrue($entry->isActive());

        // ── B. First visit ──
        $response = $this->get('/' . $slug);
        
        // Should redirect to a challenge
        $this->assertNotNull($response);
        $this->assertSame(302, $response->status());
        $location = $response->headers()['Location'] ?? '';
        $this->assertStringStartsWith('/_supamask/challenge/', $location);
        
        $challengeId = substr($location, strlen('/_supamask/challenge/'));

        // Entry MUST still be ACTIVE
        $inspected = $this->entryManager->inspect($slug);
        $this->assertTrue($inspected->isActive());

        // ── C. Failed verification ──
        $verifyResponse = $this->post('/_supamask/challenge/' . $challengeId, ['token' => 'wrong']);
        $this->assertSame(404, $verifyResponse->status());
        
        // Entry MUST still be ACTIVE
        $this->assertTrue($this->entryManager->inspect($slug)->isActive());

        // ── D. Successful verification ──
        $store = new SessionChallengeStore();
        $challenge = $store->find($challengeId);
        $this->assertNotNull($challenge);
        $this->assertSame($slug, $challenge->entrySlug());

        $successResponse = $this->post('/_supamask/challenge/' . $challengeId, ['token' => $challenge->verificationToken()]);
        
        // Should redirect to destination
        $this->assertSame(302, $successResponse->status());
        $this->assertSame('/dashboard', $successResponse->headers()['Location']);

        // Entry MUST become CONSUMED
        $registry = new SessionDisposableEntryRegistry();
        $consumedEntry = $registry->find($slug);
        $this->assertSame(DisposableEntryState::CONSUMED, $consumedEntry->state());

        // ── E. Replay ──
        $replayResponse = $this->get('/' . $slug);
        // CONSUMED entry must be explicitly rejected (410 Gone), not silently passed to app
        $this->assertNotNull($replayResponse);
        $this->assertSame(410, $replayResponse->status());
    }

    public function testExpiration(): void
    {
        $past = (new DateTimeImmutable())->modify('-1 hour');
        $entry = $this->entryManager->generate('/dashboard', $past);
        
        $response = $this->get('/' . $entry->slug());
        // Expired entry must be explicitly rejected (410 Gone)
        $this->assertNotNull($response);
        $this->assertSame(410, $response->status());

        // Registry should now mark it as expired
        $registry = new SessionDisposableEntryRegistry();
        $this->assertSame(DisposableEntryState::EXPIRED, $registry->find($entry->slug())->state());
    }

    public function testUnknownSlug(): void
    {
        // Valid-looking but nonexistent
        $response = $this->get('/a1b2c3d4e5f6');
        
        // UNKNOWN -> falls through
        $this->assertNull($response);
    }
}
