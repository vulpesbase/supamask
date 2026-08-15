<?php

namespace Supamask\Tests\Unit\Challenge;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\DefaultChallengePresentation;

final class ChallengePresentationTest extends TestCase
{
    public function testDefaultPresentationRendersContext(): void
    {
        $html = (new DefaultChallengePresentation())->render([
            'title' => 'Custom title',
            'heading' => 'Custom heading',
            'message' => 'Custom message',
            'button' => 'Verify',
            'action' => '/_supamask/challenge/82f6cd2d2843',
            'token' => 'secret-token',
        ]);

        $this->assertStringContainsString('<title>Custom title</title>', $html);
        $this->assertStringContainsString('<h1>Custom heading</h1>', $html);
        $this->assertStringContainsString('Custom message', $html);
        $this->assertStringContainsString('>Verify</button>', $html);
        $this->assertStringContainsString('secret-token', $html);
    }

    public function testDefaultPresentationEscapesContext(): void
    {
        $html = (new DefaultChallengePresentation())->render([
            'title' => '<Title>',
            'heading' => '<Heading>',
            'message' => '<Message>',
            'button' => '<Button>',
            'action' => '/challenge?id="x"',
            'token' => '<token>',
        ]);

        $this->assertStringContainsString('&lt;Title&gt;', $html);
        $this->assertStringContainsString('&lt;Heading&gt;', $html);
        $this->assertStringContainsString('&lt;Message&gt;', $html);
        $this->assertStringContainsString('&lt;Button&gt;', $html);
        $this->assertStringContainsString('&lt;token&gt;', $html);
    }
}
