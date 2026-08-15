<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\InMemoryChallengeStore;
use Supamask\Challenge\SessionVerification;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Http\Request;

final class ChallengeSecurityFlowTest extends TestCase
{
    private InMemoryChallengeStore $store;
    private SessionVerification $verification;

    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/pricing';
        $_POST = [];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();

        $this->store = new InMemoryChallengeStore();
        $this->verification = new SessionVerification();
    }

    private function kernel(): Kernel
    {
        $config = new Config([
            'challenge' => [
                'middleware' => ['enabled' => true],
                'verification_ttl' => 300,
                'presentation' => [
                    'title' => 'Verify access',
                    'heading' => 'Confirm access',
                    'message' => 'Complete this verification to continue.',
                    'button' => 'Verify',
                ],
                'protection' => [
                    'enabled' => true,
                    'paths' => ['/pricing'],
                ],
            ],
            'ip_blocking' => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
        ]);

        $manager = new ChallengeManager($this->store, 300, 300);

        return new class($config, $manager, $this->verification) extends Kernel {
        };
    }

    public function testChallengeUsesTokenAndMarksSessionVerified(): void
    {
        $kernel = $this->kernel();

        $response = $kernel->handle(new Request());
        $this->assertSame(302, $response->status());
        $location = $response->headers()['Location'];

        $_SERVER['REQUEST_URI'] = $location;
        $challengeResponse = $kernel->handle(new Request());
        $this->assertSame(200, $challengeResponse->status());

        $challenge = $this->store->find(basename($location));
        $this->assertNotNull($challenge);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['token' => $challenge->verificationToken()];
        $verificationResponse = $kernel->handle(new Request());

        $this->assertSame(302, $verificationResponse->status());
        $this->assertTrue($this->verification->isVerified());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/pricing';
        $_POST = [];

        $this->assertNull($kernel->handle(new Request()));
    }

    public function testWrongTokenDoesNotVerifyChallenge(): void
    {
        $kernel = $this->kernel();
        $response = $kernel->handle(new Request());
        $id = basename($response->headers()['Location']);
        $challenge = $this->store->find($id);

        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $id;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['token' => str_repeat('0', 64)];

        $response = $kernel->handle(new Request());

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status());
        $this->assertFalse($this->verification->isVerified());
        $this->assertSame('pending', $challenge->state()->value);
    }

    public function testChallengePresentationIsConfigurableAndEscaped(): void
    {
        $config = new Config([
            'challenge' => [
                'presentation' => [
                    'title' => '<Title>',
                    'heading' => '<Heading>',
                    'message' => '<Message>',
                    'button' => '<Continue>',
                ],
            ],
            'ip_blocking' => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
        ]);
        $manager = new ChallengeManager($this->store);
        $kernel = new class($config, $manager) extends Kernel {
            public function forceChallenge(Request $request): \Supamask\Http\Response
            {
                return $this->createChallengeResponse($request);
            }
        };

        $redirect = $kernel->forceChallenge(new Request());
        $_SERVER['REQUEST_URI'] = $redirect->headers()['Location'];

        $response = $kernel->handle(new Request());

        $this->assertStringContainsString('&lt;Title&gt;', $response->body());
        $this->assertStringContainsString('&lt;Heading&gt;', $response->body());
        $this->assertStringContainsString('&lt;Message&gt;', $response->body());
        $this->assertStringContainsString('&lt;Continue&gt;', $response->body());
    }
}
