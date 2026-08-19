<?php

namespace Supamask\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Supamask\Challenge\Challenge;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\ChallengeStoreInterface;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Entry\DisposableEntryManager;
use Supamask\Entry\InMemoryDisposableEntryRegistry;
use Supamask\Http\Request;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderInterface;
use Supamask\Security\IpIntelligence\IpIntelligenceResult;

final class HardDenyDisposablePrecedenceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [];
        $_POST = [];
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_POST = [];
        $_SESSION = [];
    }

    #[DataProvider('hardDenyCases')]
    public function testHardDenyPrecedesAnActiveDisposableEntry(
        array $override,
        ?IpIntelligenceProviderInterface $provider = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $referrer = null,
    ): void {
        $store = new class implements ChallengeStoreInterface {
            public int $saves = 0;
            /** @var array<string, Challenge> */
            private array $challenges = [];

            public function save(Challenge $challenge): void
            {
                $this->saves++;
                $this->challenges[$challenge->id()] = $challenge;
            }

            public function find(string $id): ?Challenge
            {
                return $this->challenges[$id] ?? null;
            }
        };
        $challengeManager = new ChallengeManager($store, 300, 300, null, null);
        $entries = new DisposableEntryManager(new InMemoryDisposableEntryRegistry());
        $entry = $entries->generate('/origin?foo=bar');

        $config = array_replace_recursive($this->baseConfig(), $override);
        $kernel = new Kernel(new Config($config), $challengeManager, null, $entries, $provider);

        $_SERVER = [
            'REMOTE_ADDR' => $ip ?? '198.51.100.10',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/' . $entry->slug(),
            'HTTP_HOST' => 'example.test',
        ];
        if ($userAgent !== null) {
            $_SERVER['HTTP_USER_AGENT'] = $userAgent;
        }
        if ($referrer !== null) {
            $_SERVER['HTTP_REFERER'] = $referrer;
        }

        $response = $kernel->handle(new Request());

        self::assertNotNull($response);
        self::assertSame(403, $response->status());
        self::assertArrayNotHasKey('Location', $response->headers());
        self::assertSame(0, $store->saves, 'DENY must occur before challenge creation.');
        self::assertTrue($entries->inspect($entry->slug())->isActive(), 'DENY must not consume the entry.');
    }

    public function testAllowedActiveDisposableEntryStillCreatesOneChallengeWithExactDestination(): void
    {
        $store = new class implements ChallengeStoreInterface {
            public int $saves = 0;
            /** @var array<string, Challenge> */
            private array $challenges = [];
            public function save(Challenge $challenge): void { $this->saves++; $this->challenges[$challenge->id()] = $challenge; }
            public function find(string $id): ?Challenge { return $this->challenges[$id] ?? null; }
        };
        $challengeManager = new ChallengeManager($store, 300, 300, null, null);
        $entries = new DisposableEntryManager(new InMemoryDisposableEntryRegistry());
        $entry = $entries->generate('/origin?foo=bar');
        $kernel = new Kernel(new Config($this->baseConfig()), $challengeManager, null, $entries);

        $_SERVER = [
            'REMOTE_ADDR' => '198.51.100.20',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/' . $entry->slug(),
            'HTTP_HOST' => 'example.test',
        ];

        $response = $kernel->handle(new Request());

        self::assertSame(302, $response?->status());
        self::assertSame(1, $store->saves);
        $id = basename($response->headers()['Location']);
        self::assertSame('/origin?foo=bar', $store->find($id)?->originalUri());
        self::assertTrue($entries->inspect($entry->slug())->isActive());
    }

    public function testAllowedExpiredAndConsumedEntriesKeepLifecycleRejections(): void
    {
        $entries = new DisposableEntryManager(new InMemoryDisposableEntryRegistry());
        $expired = $entries->generate('/origin', (new DateTimeImmutable())->modify('-1 hour'));
        $consumed = $entries->generate('/origin');
        $entries->consume($consumed->slug());
        $kernel = new Kernel(new Config($this->baseConfig()), null, null, $entries);

        foreach ([$expired->slug(), $consumed->slug()] as $slug) {
            $_SERVER = [
                'REMOTE_ADDR' => '198.51.100.20',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/' . $slug,
                'HTTP_HOST' => 'example.test',
            ];
            self::assertSame(410, $kernel->handle(new Request())?->status());
        }
    }

    public static function hardDenyCases(): array
    {
        $vpn = new class implements IpIntelligenceProviderInterface {
            public function lookup(string $ip): IpIntelligenceResult { return new IpIntelligenceResult($ip, 'AS64500', 'VPN Network', true); }
        };
        $asn = new class implements IpIntelligenceProviderInterface {
            public function lookup(string $ip): IpIntelligenceResult { return new IpIntelligenceResult($ip, 'AS14061', 'DigitalOcean'); }
        };

        return [
            'exact IP' => [['ip_blocking' => ['rules' => ['198.51.100.10']]], null],
            'CIDR' => [['ip_blocking' => ['rules' => ['198.51.100.0/24']]], null],
            'bot' => [['bot_blocking' => ['signatures' => ['EvilBot']]], null, null, 'EvilBot/1.0'],
            'VPN' => [['block_vpn' => true], $vpn],
            'ASN' => [['detect_isp' => true, 'isp_exclusions' => ['AS14061']], $asn],
            'ISP' => [['detect_isp' => true, 'isp_exclusions' => ['DigitalOcean']], $asn],
            'referrer' => [['block_referrers' => true, 'referrer_blocklist' => ['badsite.com']], null, null, null, 'https://sub.badsite.com/path'],
        ];
    }

    private function baseConfig(): array
    {
        return [
            'ip_blocking' => ['enabled' => true, 'antired' => false, 'rules' => []],
            'bot_blocking' => ['enabled' => true, 'antired' => false, 'signatures' => []],
            'disposable' => ['enabled' => true],
            'entry' => ['enabled' => true],
            'challenge' => ['proof_of_work' => ['enabled' => false]],
        ];
    }
}
