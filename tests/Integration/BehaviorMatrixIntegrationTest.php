<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Entry\DisposableEntryManager;
use Supamask\Entry\SessionDisposableEntryRegistry;
use Supamask\Http\Request;
use Supamask\Http\Response;

final class BehaviorMatrixIntegrationTest extends TestCase
{
    private Kernel $kernel;
    private DisposableEntryManager $manager;
    private string $activeSlug;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];

        $config = new Config([
            'challenge' => [
                'enabled' => true,
                'path' => '/_supamask/challenge/',
                'middleware' => [
                    'enabled' => true,
                ],
                'protection' => [
                    'enabled' => true,
                    'paths' => ['/pricing'],
                ],
            ],
            'disposable' => [
                'enabled' => true,
                'slug_length' => 12,
            ],
            'entry' => [
                'enabled' => true,
                'referrers' => ['https://trusted.example/'],
                'policy' => [
                    'direct' => 'allow',
                    'referred' => 'challenge',
                    'seeded' => 'challenge',
                    'unknown' => 'deny',
                ],
            ],
            'routing' => [
                'root' => [
                    'behavior' => 'allow',
                ],
            ]
        ]);

        $this->kernel = new Kernel($config);
        $this->manager = new DisposableEntryManager(new SessionDisposableEntryRegistry(), 900, 12, true);
        $this->activeSlug = $this->manager->generate('/dashboard')->slug();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function get(string $path, ?string $referrer = null): ?Response
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['HTTP_HOST'] = 'example.test';
        if ($referrer !== null) {
            $_SERVER['HTTP_REFERER'] = $referrer;
        } else {
            unset($_SERVER['HTTP_REFERER']);
        }
        return $this->kernel->handle(new Request());
    }

    /** @return array<string, array{string, bool, string|null, string, string|null}> */
    public static function behaviorMatrix(): array
    {
        // Matrix: Path | Entry Exists? | Referrer | Expected Classification equivalent | Result (NULL=Allow, 302=Challenge, 403=Deny)
        return [
            'Root, No entry, Direct' => [
                '/', false, null, 'DIRECT', 'ALLOW'
            ],
            'Protected path, No entry, Direct' => [
                '/pricing', false, null, 'DIRECT', 'CHALLENGE' // DIRECT=Allow -> normal pipeline -> RoutePolicy requires challenge
            ],
            'Protected path, No entry, Trusted referrer' => [
                '/pricing', false, 'https://trusted.example/page', 'REFERRED', 'CHALLENGE' // REFERRED=Challenge (entry policy intercepts)
            ],
            'Active slug, Yes entry, Direct' => [
                '/SLUG', true, null, 'SEEDED', 'CHALLENGE' // SEEDED intercepts -> DisposableEntryHandler handles challenge
            ],
            'Non-active slug, No entry, Direct' => [
                '/a1b2c3d4e5f6', false, null, 'DIRECT', 'ALLOW' // Valid-looking slug but not active -> DIRECT=Allow -> not in RoutePolicy -> app handles
            ],
            'Bad slug, No entry, Direct' => [
                '/bad-slug', false, null, 'DIRECT', 'ALLOW'
            ],
            'Root, No entry, Trusted referrer' => [
                '/', false, 'https://trusted.example/page', 'REFERRED', 'CHALLENGE'
            ],
            'Root, No entry, Unknown referrer' => [
                '/', false, 'https://evil.example/', 'UNKNOWN', 'DENY' // UNKNOWN=Deny via entry policy
            ],
            'Protected path, No entry, Unknown referrer' => [
                '/pricing', false, 'https://evil.example/', 'UNKNOWN', 'DENY' // UNKNOWN=Deny via entry policy
            ],
        ];
    }

    #[DataProvider('behaviorMatrix')]
    public function testBehaviorMatrix(string $path, bool $entryExists, ?string $referrer, string $expectedClass, string $expectedResult): void
    {
        if ($path === '/SLUG' && $entryExists) {
            $path = '/' . $this->activeSlug;
        }

        $response = $this->get($path, $referrer);

        if ($expectedResult === 'ALLOW') {
            $this->assertNull($response);
        } elseif ($expectedResult === 'CHALLENGE') {
            $this->assertNotNull($response);
            $this->assertSame(302, $response->status());
            $this->assertStringStartsWith('/_supamask/challenge/', $response->headers()['Location']);
        } elseif ($expectedResult === 'DENY') {
            $this->assertNotNull($response);
            $this->assertSame(403, $response->status());
        }
    }
}
