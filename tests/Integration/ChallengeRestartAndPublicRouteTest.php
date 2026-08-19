<?php

namespace Supamask\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Supamask\Challenge\Challenge;
use Supamask\Challenge\ChallengeHandler;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\ChallengeState;
use Supamask\Challenge\InMemoryChallengeStore;
use Supamask\Challenge\Presentation\PolymorphicChallengePresentation;
use Supamask\Challenge\SessionVerification;
use Supamask\Core\Config;
use Supamask\Http\Request;

final class ChallengeRestartAndPublicRouteTest extends TestCase
{
    private InMemoryChallengeStore $store;
    private SessionVerification $verification;
    private ChallengeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/pricing'];
        $_POST = [];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();

        $this->store = new InMemoryChallengeStore();
        $this->verification = new SessionVerification();
        $this->handler = new ChallengeHandler(
            new ChallengeManager($this->store, 300, 300),
            $this->verification,
            new Config(['challenge' => ['presentation_path' => '/']]),
            new PolymorphicChallengePresentation(),
        );
    }

    public function testValidChallengeRendersAtThePublicRoute(): void
    {
        $redirect = $this->handler->create(new Request());
        $this->assertMatchesRegularExpression('#^/[a-f0-9]{12}$#', $redirect->headers()['Location']);

        $_SERVER['REQUEST_URI'] = $redirect->headers()['Location'];
        $response = $this->handler->handle(new Request());

        $this->assertSame(200, $response->status());
        $this->assertMatchesRegularExpression('/<form[^>]*method="post"/i', $response->body());
        $this->assertStringContainsString('name="token"', $response->body());
    }

    public function testExpiredPresentationStartsFreshChallengeWithFreshState(): void
    {
        $expired = $this->expiredChallenge('aabbccddeeff', '/pricing');
        $this->store->save($expired);
        $_SERVER['REQUEST_URI'] = '/' . $expired->id();

        $restart = $this->handler->handle(new Request());

        $this->assertSame(302, $restart->status());
        $this->assertNotSame('/' . $expired->id(), $restart->headers()['Location']);
        $this->assertSame(ChallengeState::EXPIRED, $this->store->find($expired->id())->state());

        $freshId = basename($restart->headers()['Location']);
        $fresh = $this->store->find($freshId);
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->isPending());
        $this->assertNotSame($expired->verificationToken(), $fresh->verificationToken());
        $this->assertSame('/pricing', $fresh->originalUri());

        $_SERVER['REQUEST_URI'] = $restart->headers()['Location'];
        $this->assertSame(200, $this->handler->handle(new Request())->status());
    }

    public function testConsumedOrMalformedChallengesCannotAuthenticate(): void
    {
        $manager = new ChallengeManager($this->store, 300, 300);
        $challenge = $manager->create('/pricing');
        $manager->consume($challenge->id(), $challenge->verificationToken());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/' . $challenge->id();
        $_POST = ['token' => $challenge->verificationToken()];
        $this->assertSame(404, $this->handler->handle(new Request())->status());
        $this->assertFalse($this->verification->isVerified());

        $_SERVER['REQUEST_URI'] = '/not-a-challenge';
        $_POST = ['token' => 'not-a-token'];
        $this->assertSame(404, $this->handler->handle(new Request())->status());
        $this->assertFalse($this->verification->isVerified());
    }

    public function testEntryBoundInvalidChallengeKeepsGoneSemantics(): void
    {
        $expired = $this->expiredChallenge('001122334455', '/pricing', 'entry-slug');
        $this->store->save($expired);
        $_SERVER['REQUEST_URI'] = '/' . $expired->id();

        $response = $this->handler->handle(new Request());

        $this->assertSame(410, $response->status());
        $this->assertSame(ChallengeState::EXPIRED, $this->store->find($expired->id())->state());
    }

    private function expiredChallenge(string $id, string $destination, ?string $entrySlug = null): Challenge
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new Challenge(
            $id,
            $destination,
            $now->modify('-10 minutes'),
            $now->modify('-5 minutes'),
            str_repeat('a', 64),
            ChallengeState::PENDING,
            $entrySlug,
        );
    }
}
