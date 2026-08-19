<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Http\Request;
use Supamask\Security\ProofOfWork\ProofOfWorkVerifier;

final class ProofOfWorkChallengeFlowTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/pricing';
        $_POST = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    public function testEnabledProofOfWorkIsEmbeddedAndRequired(): void
    {
        $kernel = new class(new Config([
            'ip_blocking' => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
            'challenge' => ['proof_of_work' => ['enabled' => true, 'difficulty' => 8, 'ttl' => 60]],
        ])) extends Kernel {
            public function force(Request $request): ?\Supamask\Http\Response
            {
                return $this->createChallengeResponse($request);
            }
        };

        $redirect = $kernel->force(new Request());
        $_SERVER['REQUEST_URI'] = $redirect->headers()['Location'];
        $response = $kernel->handle(new Request());

        $this->assertSame(200, $response->status());
        $this->assertMatchesRegularExpression('/name="pow_counter"/', $response->body());
        $this->assertStringContainsString('var nonce=', $response->body());
        $this->assertStringContainsString('difficulty=8', $response->body());
        $this->assertStringContainsString('crypto.subtle.digest("SHA-256"', $response->body());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['token' => $this->extract($response->body(), '/name="token" value="([a-f0-9]{64})"/')];
        $retry = $kernel->handle(new Request());

        $this->assertSame(200, $retry->status());
        $this->assertStringContainsString('Try once more', $retry->body());
    }


    public function testSuccessfulProofOfWorkCompletesVerificationAndAllowsOrigin(): void
    {
        $kernel = new class(new Config([
            'ip_blocking' => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
            'challenge' => [
                'middleware' => ['enabled' => true],
                'protection' => ['enabled' => true],
                'proof_of_work' => ['enabled' => true, 'difficulty' => 8, 'ttl' => 60],
            ],
            'routing' => ['root' => ['behavior' => 'challenge']],
        ])) extends Kernel {
            public function force(Request $request): ?\Supamask\Http\Response
            {
                return $this->createChallengeResponse($request);
            }
        };

        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $redirect = $kernel->force(new Request());
        $_SERVER['REQUEST_URI'] = $redirect->headers()['Location'];
        $challenge = $kernel->handle(new Request());

        $this->assertStringContainsString('Preparing secure session', $challenge->body());
        $this->assertStringContainsString('id="sm-preparing-screen"', $challenge->body());
        $this->assertStringContainsString('(function(value)', $challenge->body());
        $this->assertStringNotContainsString('setTimeout(submit,2000)', $challenge->body());

        $token = $this->extract($challenge->body(), '/name="token" value="([a-f0-9]{64})"/');
        $nonce = json_decode($this->extract($challenge->body(), '/var nonce=([^,]+),difficulty=/'), true);
        $difficulty = (int) $this->extract($challenge->body(), '/var nonce=[^,]+,difficulty=([0-9]+)/');
        $counter = $this->solve((string) $nonce, $token, $difficulty);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['token' => $token, 'pow_counter' => $counter];
        $verified = $kernel->handle(new Request());

        $this->assertSame(200, $verified->status());
        $this->assertStringContainsString('success', strtolower($verified->body()));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_POST = [];
        $origin = $kernel->handle(new Request());

        $this->assertNull($origin, 'A successfully verified session must reach the application origin.');
    }

    public function testDisabledProofOfWorkPreservesNormalChallengePost(): void
    {
        $kernel = new class(new Config([
            'ip_blocking' => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
            'challenge' => ['proof_of_work' => ['enabled' => false]],
        ])) extends Kernel {
            public function force(Request $request): ?\Supamask\Http\Response
            {
                return $this->createChallengeResponse($request);
            }
        };

        $redirect = $kernel->force(new Request());
        $_SERVER['REQUEST_URI'] = $redirect->headers()['Location'];
        $response = $kernel->handle(new Request());
        $token = $this->extract($response->body(), '/name="token" value="([a-f0-9]{64})"/');

        $this->assertStringNotContainsString('name="pow_counter"', $response->body());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['token' => $token];
        $verified = $kernel->handle(new Request());

        $this->assertSame(200, $verified->status());
        $this->assertStringContainsString('success', strtolower($verified->body()));
    }


    private function solve(string $nonce, string $token, int $difficulty): string
    {
        for ($counter = 0; $counter <= 10000000; $counter++) {
            $digest = hash('sha256', $nonce . ':' . $token . ':' . $counter, true);
            $fullBytes = intdiv($difficulty, 8);
            $valid = true;

            for ($i = 0; $i < $fullBytes; $i++) {
                if (ord($digest[$i]) !== 0) {
                    $valid = false;
                    break;
                }
            }

            if ($valid && ($difficulty % 8) !== 0) {
                $valid = (ord($digest[$fullBytes]) >> (8 - ($difficulty % 8))) === 0;
            }

            if ($valid) {
                return (string) $counter;
            }
        }

        $this->fail('Unable to find a proof-of-work solution within the test limit.');
    }

    private function extract(string $body, string $pattern): string
    {
        preg_match($pattern, $body, $matches);
        return (string) ($matches[1] ?? '');
    }
}
