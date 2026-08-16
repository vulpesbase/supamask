<?php

namespace Supamask\Tests\Unit\Challenge;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\ChallengeHandler;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\DefaultChallengePresentation;
use Supamask\Challenge\InMemoryChallengeStore;
use Supamask\Challenge\SessionVerification;
use Supamask\Core\Config;
use Supamask\Http\Request;

final class ChallengeHandlerTest extends TestCase
{
    private function handler(?array $presentation = null): ChallengeHandler
    {
        $config = new Config([
            'challenge' => [
                'enabled' => true,
                'path' => '/_supamask/challenge/',
                'presentation' => $presentation ?? [],
            ],
        ]);

        return new ChallengeHandler(
            new ChallengeManager(new InMemoryChallengeStore(), 300, 300),
            new SessionVerification(),
            $config,
            new DefaultChallengePresentation(),
        );
    }

    public function testChallengePathIsRecognized(): void
    {
        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/82f6cd2d2843';

        $this->assertTrue(
            $this->handler()->matches(new Request())
        );
    }

    public function testMalformedChallengePathIsHandledForSafeRestart(): void
    {
        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/000000000000';

        $this->assertTrue(
            $this->handler()->matches(new Request())
        );
    }

    public function testCreateReturnsChallengeLocation(): void
    {
        $_SERVER['REQUEST_URI'] = '/pricing';

        $response = $this->handler()->create(new Request());

        $this->assertSame(302, $response->status());
        $this->assertMatchesRegularExpression(
            '#^/_supamask/challenge/[a-f0-9]{12}$#',
            $response->headers()['Location']
        );
    }

    public function testPresentationIsEscaped(): void
    {
        $_SERVER['REQUEST_URI'] = '/pricing';

        $handler = $this->handler([
            'title' => '<Title>',
            'heading' => '<Heading>',
            'message' => '<Message>',
            'button' => '<Continue>',
        ]);

        $redirect = $handler->create(new Request());

        $_SERVER['REQUEST_URI'] = $redirect->headers()['Location'];

        $response = $handler->handle(new Request());

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('&lt;Title&gt;', $response->body());
        $this->assertStringContainsString('&lt;Heading&gt;', $response->body());
        $this->assertStringContainsString('&lt;Message&gt;', $response->body());
        $this->assertStringContainsString('&lt;Continue&gt;', $response->body());
    }
}
