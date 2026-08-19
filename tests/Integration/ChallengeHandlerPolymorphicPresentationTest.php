<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\ChallengeHandler;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\InMemoryChallengeStore;
use Supamask\Challenge\Presentation\PolymorphicChallengePresentation;
use Supamask\Challenge\SessionVerification;
use Supamask\Core\Config;
use Supamask\Http\Request;

/**
 * Integration tests for ChallengeHandler with polymorphic presentation.
 *
 * Verifies that ChallengeHandler works correctly with the new
 * PolymorphicChallengePresentation implementation without requiring
 * any changes to the handler itself.
 */
final class ChallengeHandlerPolymorphicPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset $_SERVER to avoid state pollution between tests
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Reset $_SERVER after each test
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
    }
    private function handler(
        ?array $presentation = null,
        ?PolymorphicChallengePresentation $presenter = null
    ): ChallengeHandler {
        $config = new Config([
            'challenge' => [
                'enabled' => true,
                'path' => '/_supamask/challenge/',
                'presentation' => $presentation ?? [],
            ],
        ]);

        $presentation = $presenter ?? new PolymorphicChallengePresentation();

        return new ChallengeHandler(
            new ChallengeManager(new InMemoryChallengeStore(), 300, 300),
            new SessionVerification(),
            $config,
            $presentation,
        );
    }

    public function testChallengeHandlerWorksWithPolymorphicPresentation(): void
    {
        $_SERVER['REQUEST_URI'] = '/protected';

        $handler = $this->handler();
        $createResponse = $handler->create(new Request());

        $this->assertSame(302, $createResponse->status());

        // Extract challenge ID from redirect location
        preg_match('#/_supamask/challenge/([a-f0-9]{12})$#', $createResponse->headers()['Location'], $matches);
        $this->assertNotEmpty($matches);

        $challengeId = $matches[1];

        // Render the challenge using the polymorphic presentation
        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challengeId;

        $renderResponse = $handler->handle(new Request());

        $this->assertSame(200, $renderResponse->status());
        $body = $renderResponse->body();

        // Verify polymorphic presentation renders valid HTML with all components
        $this->assertStringContainsString('<!doctype html>', strtolower($body));
        $this->assertStringContainsString('</html>', $body);
        $this->assertStringContainsString('<form', $body);
        $this->assertStringContainsString('type="submit"', $body);
        $this->assertStringContainsString('name="token"', $body);
        $this->assertStringContainsString($challengeId, $body);

        // Verify reference code is present (8 char alphanumeric)
        $this->assertMatchesRegularExpression('/[A-Z0-9]{8}/', $body);
    }

    public function testPolymorphicPresentationWithMultipleVariants(): void
    {
        $_SERVER['REQUEST_URI'] = '/page1';

        $handler = $this->handler();

        // Create multiple challenges
        $htmls = [];

        for ($i = 0; $i < 5; $i++) {
            $createResponse = $handler->create(new Request());
            preg_match('#/_supamask/challenge/([a-f0-9]{12})$#', $createResponse->headers()['Location'], $matches);
            $challengeId = $matches[1];

            $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challengeId;
            $renderResponse = $handler->handle(new Request());

            $htmls[] = $renderResponse->body();
        }

        // Verify we got valid HTML for all renders
        foreach ($htmls as $html) {
            $this->assertStringContainsString('<!doctype html>', strtolower($html));
            $this->assertStringContainsString('<form', $html);
        }
    }

    public function testPolymorphicPresentationWithConfigurationOverrides(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin';

        $customPresentation = [
            'title' => 'Admin Verification',
            'heading' => 'Admin Verification Required',
            'message' => 'Please verify your admin credentials.',
            'button' => 'Verify Admin Access',
        ];

        $handler = $this->handler($customPresentation);

        $createResponse = $handler->create(new Request());
        preg_match('#/_supamask/challenge/([a-f0-9]{12})$#', $createResponse->headers()['Location'], $matches);
        $challengeId = $matches[1];

        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challengeId;
        $renderResponse = $handler->handle(new Request());

        $html = $renderResponse->body();

        // Verify configuration overrides are applied
        $this->assertStringContainsString('Admin Verification', $html);
        $this->assertStringContainsString('Admin Verification Required', $html);
        $this->assertStringContainsString('Please verify your admin credentials.', $html);
        $this->assertStringContainsString('<span>Verify Admin Access</span></button>', $html);
    }

    public function testPolymorphicPresentationWithXssProtection(): void
    {
        $_SERVER['REQUEST_URI'] = '/test';

        $xssPresentation = [
            'title' => '<script>alert("xss")</script>',
            'heading' => '"><img src=x>',
            'message' => '<iframe src="evil">',
            'button' => '<Button>',
        ];

        $handler = $this->handler($xssPresentation);

        $createResponse = $handler->create(new Request());
        preg_match('#/_supamask/challenge/([a-f0-9]{12})$#', $createResponse->headers()['Location'], $matches);
        $challengeId = $matches[1];

        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challengeId;
        $renderResponse = $handler->handle(new Request());

        $html = $renderResponse->body();

        // Verify dangerous content is escaped
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;iframe', $html);
        $this->assertStringContainsString('&lt;Button&gt;', $html);
    }

    public function testPolymorphicPresentationVerificationStillWorks(): void
    {
        $_SERVER['REQUEST_URI'] = '/verify-test';

        $handler = $this->handler();

        // Create challenge
        $createResponse = $handler->create(new Request());
        $location = $createResponse->headers()['Location'];

        preg_match('#/_supamask/challenge/([a-f0-9]{12})$#', $location, $matches);
        $challengeId = $matches[1];

        // Render challenge (verifies polymorphic presentation works)
        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challengeId;
        $renderResponse = $handler->handle(new Request());

        $this->assertSame(200, $renderResponse->status());

        // Now extract the token from the rendered HTML
        $html = $renderResponse->body();
        preg_match('/name="token"\s+value="([^"]+)"/', $html, $tokenMatches);

        $this->assertNotEmpty($tokenMatches);
        $token = $tokenMatches[1];

        // POST to verify
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challengeId;
        $_POST['token'] = $token;

        $verifyResponse = $handler->handle(new Request());

        // Verification renders the success state, which client-side redirects
        // to the original local URI after the confirmation transition.
        $this->assertSame(200, $verifyResponse->status());
        $this->assertStringContainsString('state==="success"', $verifyResponse->body());
        $this->assertStringContainsString('window.location.replace("/verify-test")', $verifyResponse->body());
    }

    public function testPresenterCanRestrictVariants(): void
    {
        $customPresenter = new PolymorphicChallengePresentation();
        // Only use shield variant
        $customPresenter->presenter()->setEnabledVariants(['shield']);

        $_SERVER['REQUEST_URI'] = '/shield-only';

        $handler = $this->handler(null, $customPresenter);

        // Create a challenge
        $createResponse = $handler->create(new Request());
        $this->assertSame(302, $createResponse->status());
        
        $location = $createResponse->headers()['Location'];
        preg_match('#/_supamask/challenge/([a-f0-9]{12})$#', $location, $matches);
        $this->assertNotEmpty($matches, 'Challenge location not found in redirect: ' . $location);
        $challengeId = $matches[1];

        $_SERVER['REQUEST_URI'] = $location;
        $renderResponse = $handler->handle(new Request());

        $this->assertSame(200, $renderResponse->status(), 'Challenge render failed. Response body: ' . $renderResponse->body());
        
        $html = $renderResponse->body();

        // Presentation class names are generated per render, rather than static.
        $this->assertDoesNotMatchRegularExpression('/(?:class|id)="supamask-/', $html);
        $this->assertMatchesRegularExpression('/class="[a-z][a-z0-9]{15}"/', $html);
        $this->assertStringContainsString('🛡️', $html);
    }

    public function testAllVariantsCanBeRendered(): void
    {
        $variants = ['shield', 'pill', 'checkmark'];

        foreach ($variants as $variant) {
            $customPresenter = new PolymorphicChallengePresentation();
            $customPresenter->presenter()->setEnabledVariants([$variant]);

            $_SERVER['REQUEST_URI'] = "/$variant-test";

            $handler = $this->handler(null, $customPresenter);

            $createResponse = $handler->create(new Request());
            preg_match('#/_supamask/challenge/([a-f0-9]{12})$#', $createResponse->headers()['Location'], $matches);
            $challengeId = $matches[1];

            $_SERVER['REQUEST_URI'] = '/_supamask/challenge/' . $challengeId;
            $renderResponse = $handler->handle(new Request());

            $html = $renderResponse->body();

            // Verify specific variant marker
            $expectedMarker = match ($variant) {
                'shield' => '🛡️',
                'pill' => 'type="submit"',
                'checkmark' => '✓',
            };

            $this->assertStringContainsString($expectedMarker, $html);
            $this->assertDoesNotMatchRegularExpression('/(?:class|id)="supamask-/', $html);
        }
    }
}
