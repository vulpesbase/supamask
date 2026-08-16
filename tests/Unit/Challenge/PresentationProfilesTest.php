<?php

namespace Supamask\Tests\Unit\Challenge;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Supamask\Challenge\Presentation\ChallengePresenter;
use Supamask\Challenge\Presentation\PresentationProfileCatalogue;

final class PresentationProfilesTest extends TestCase
{
    public function testSevenReferenceProfilesExist(): void
    {
        $this->assertCount(7, PresentationProfileCatalogue::all());
        $this->assertSame([
            'branded-confirm',
            'compact-icon-confirm',
            'compact-secure',
            'compact-quick',
            'compact-almost',
            'branded-one-more',
            'branded-protected',
        ], PresentationProfileCatalogue::names());
    }

    /** @dataProvider profileProvider */
    #[DataProvider('profileProvider')]
    public function testEveryReferenceProfileRenders(string $profile, string $expectedHeading, string $expectedTrust): void
    {
        $presenter = new ChallengePresenter();
        $presenter->setEnabledVariants([$profile]);

        $html = $presenter->present('abc123def456', str_repeat('a', 64), '/abc123def456');

        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString(htmlspecialchars($expectedHeading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
        $this->assertStringContainsString(htmlspecialchars($expectedTrust, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
        $this->assertSame(1, substr_count($html, 'type="submit"'));
        $this->assertStringContainsString('name="token"', $html);
        $this->assertStringContainsString(str_repeat('a', 64), $html);
    }

    public static function profileProvider(): array
    {
        return [
            ['branded-confirm', 'Confirm you are human', 'Privacy first'],
            ['compact-icon-confirm', 'Confirm you are human', 'Secure gate'],
            ['compact-secure', 'Secure continue', 'TLS secured'],
            ['compact-quick', 'Quick security check', 'Encrypted session'],
            ['compact-almost', 'Almost there', 'Privacy first'],
            ['branded-one-more', 'One more step', 'Privacy first'],
            ['branded-protected', 'One more step', 'TLS secured'],
        ];
    }

    public function testPresentationReferenceChangesPerRender(): void
    {
        $presenter = new ChallengePresenter();
        $presenter->setEnabledVariants(['branded-protected']);

        $first = $presenter->present('abc123def456', str_repeat('a', 64), '/abc123def456');
        $second = $presenter->present('abc123def456', str_repeat('a', 64), '/abc123def456');

        preg_match('/(?:ref <span[^>]*>|· <span[^>]*>)([A-Z0-9]{8})<\/span>/', $first, $m1);
        preg_match('/(?:ref <span[^>]*>|· <span[^>]*>)([A-Z0-9]{8})<\/span>/', $second, $m2);

        $this->assertArrayHasKey(1, $m1);
        $this->assertArrayHasKey(1, $m2);
        $this->assertNotSame($m1[1], $m2[1]);
    }
}
