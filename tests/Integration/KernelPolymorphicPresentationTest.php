<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\InMemoryChallengeStore;
use Supamask\Challenge\Presentation\ContentCatalogue;
use Supamask\Challenge\SessionVerification;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Http\Request;

final class KernelPolymorphicPresentationTest extends TestCase
{
    private InMemoryChallengeStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/polymorphic',
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ];
        $_POST = [];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();

        $this->store = new InMemoryChallengeStore();
    }

    public function testKernelUsesPolymorphicPresentationForHttpChallenges(): void
    {
        $kernel = new Kernel(
            new Config([
                'challenge' => [
                    'middleware' => ['enabled' => true],
                    'protection' => [
                        'enabled' => true,
                        'paths' => ['/polymorphic'],
                    ],
                ],
                'ip_blocking' => ['enabled' => false],
                'bot_blocking' => ['enabled' => false],
            ]),
            new ChallengeManager($this->store, 300, 300),
            new SessionVerification(),
        );

        $redirect = $kernel->handle(new Request());

        $this->assertNotNull($redirect);
        $this->assertSame(302, $redirect->status());
        $location = $redirect->headers()['Location'];

        $_SERVER['REQUEST_URI'] = $location;
        $firstResponse = $kernel->handle(new Request());
        $this->assertNotNull($firstResponse);
        $this->assertSame(200, $firstResponse->status());
        $firstHtml = $firstResponse->body();

        $this->assertStringContainsString('<h1', $firstHtml);
        $this->assertStringContainsString('<p ', $firstHtml);
        $this->assertStringContainsString('<form method="post"', $firstHtml);
        $this->assertSame(1, substr_count($firstHtml, 'type="submit"') + substr_count($firstHtml, 'type=submit'));
        $this->assertStringContainsString('name="token"', $firstHtml);
        $this->assertStringContainsString('<span>Continue</span></button>', $firstHtml);
        $this->assertMatchesRegularExpression('/class="[a-z][a-z0-9]{15}"/', $firstHtml);
        $this->assertStringNotContainsString('supamask-', $firstHtml);
        $this->assertTrue(
            str_contains($firstHtml, 'max-width:440px') ||
            str_contains($firstHtml, 'max-width:390px') ||
            str_contains($firstHtml, 'max-width:420px')
        );
        $this->assertTrue($this->containsTrustFooter($firstHtml));
        $firstReferenceCode = $this->referenceCode($firstHtml);

        $secondResponse = $kernel->handle(new Request());
        $this->assertNotNull($secondResponse);
        $this->assertSame(200, $secondResponse->status());
        $secondReferenceCode = $this->referenceCode($secondResponse->body());

        $this->assertNotSame($firstReferenceCode, $secondReferenceCode);
    }

    public function testLegacyInternalRouteCannotVerifyAPublicChallenge(): void
    {
        $verification = new SessionVerification();
        $kernel = new Kernel(
            new Config([
                'challenge' => [
                    'middleware' => ['enabled' => true],
                    'protection' => ['enabled' => true, 'paths' => ['/polymorphic']],
                ],
                'ip_blocking' => ['enabled' => false],
                'bot_blocking' => ['enabled' => false],
            ]),
            new ChallengeManager($this->store, 300, 300),
            $verification,
        );

        $redirect = $kernel->handle(new Request());
        $challenge = $this->store->find(basename($redirect->headers()['Location']));
        $this->assertNotNull($challenge);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challenge->id();
        $_POST = ['token' => $challenge->verificationToken()];

        $this->assertNull($kernel->handle(new Request()));
        $this->assertFalse($verification->isVerified());
        $this->assertTrue($this->store->find($challenge->id())->isPending());
    }

    private function containsTrustFooter(string $html): bool
    {
        foreach (ContentCatalogue::allTrustFooters() as $trustFooter) {
            if (str_contains($html, $trustFooter)) {
                return true;
            }
        }

        return false;
    }

    private function referenceCode(string $html): string
    {
        preg_match('/<span id="[a-f0-9]+">([A-Z0-9]{8})<\/span>/', $html, $matches);

        $this->assertArrayHasKey(1, $matches);

        return $matches[1];
    }
}
