<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\InMemoryChallengeStore;
use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Config;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Core\Kernel;
use Supamask\Http\Request;

class ChallengeFlowTest extends TestCase
{
    private InMemoryChallengeStore $store;
    private Kernel $kernel;

    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/pricing';
        $_POST = [];

        $this->store = new InMemoryChallengeStore();
        $manager = new ChallengeManager($this->store, 300);
        $config = new Config([
            'ip_blocking' => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
        ]);

        $this->kernel = new class($config, $manager) extends Kernel {
            public function forceChallenge(Request $request): ?\Supamask\Http\Response
            {
                return $this->createChallengeResponse($request);
            }
        };
    }

    public function testChallengeDecisionCreatesRedirectToChallengeRoute(): void
    {
        $response = $this->kernel->forceChallenge(new Request());

        $this->assertNotNull($response);
        $this->assertSame(302, $response->status());
        $this->assertMatchesRegularExpression(
            '#^/_supamask/challenge/[a-f0-9]{12}$#',
            $response->headers()['Location']
        );
        $this->assertCount(1, $this->getStoredChallenges());
    }

    public function testChallengeRouteRendersPendingChallenge(): void
    {
        $challenge = (new ChallengeManager($this->store))->create('/pricing');
        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challenge->id();
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $response = $this->kernel->handle(new Request());

        $this->assertNotNull($response);
        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Security verification', $response->body());
        $this->assertStringContainsString('<form', $response->body());
        $this->assertStringContainsString($challenge->verificationToken(), $response->body());
    }

    public function testPostConsumesChallengeAndRedirectsToOriginalUri(): void
    {
        $challenge = (new ChallengeManager($this->store))->create('/pricing?plan=pro');
        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challenge->id();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['token' => $challenge->verificationToken()];

        $response = $this->kernel->handle(new Request());

        $this->assertNotNull($response);
        $this->assertSame(302, $response->status());
        $this->assertSame('/pricing?plan=pro', $response->headers()['Location']);
        $this->assertSame('consumed', $this->store->find($challenge->id())->state()->value);
    }

    public function testConsumedChallengeCannotBePostedAgain(): void
    {
        $challenge = (new ChallengeManager($this->store))->create('/pricing');
        $challengeManager = new ChallengeManager($this->store);
        $challengeManager->consume($challenge->id(), $challenge->verificationToken());

        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challenge->id();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['token' => $challenge->verificationToken()];

        $response = $this->kernel->handle(new Request());

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status());
    }

    /** @return array<string, mixed> */
    private function getStoredChallenges(): array
    {
        $reflection = new \ReflectionClass($this->store);
        $property = $reflection->getProperty('challenges');
        $property->setAccessible(true);

        return $property->getValue($this->store);
    }
}
