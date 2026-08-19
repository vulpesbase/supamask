<?php

namespace Supamask\Tests\Unit\Challenge;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Supamask\Challenge\Presentation\ChallengePresenter;
use Supamask\Challenge\Presentation\PresentationProfileCatalogue;

final class PresentationProfilesTest extends TestCase
{
    public function testAllSupportedProfilesExist(): void
    {
        $this->assertCount(14, PresentationProfileCatalogue::all());
        $this->assertSame([
            'branded-confirm',
            'compact-icon-confirm',
            'compact-secure',
            'compact-quick',
            'compact-almost',
            'branded-one-more',
            'branded-protected',
            'extended-8',
            'extended-9',
            'extended-10',
            'extended-11',
            'extended-12',
            'extended-13',
            'extended-14',
        ], PresentationProfileCatalogue::names());
    }

    /** @dataProvider profileProvider */
    #[DataProvider('profileProvider')]
    public function testEveryReferenceProfileRenders(string $profile): void
    {
        $presenter = new ChallengePresenter();
        $presenter->setEnabledVariants([$profile]);

        $html = $presenter->present('abc123def456', str_repeat('a', 64), '/abc123def456');

        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertSame(1, substr_count($html, 'type="submit"'));
        $this->assertStringContainsString('name="token"', $html);
        $this->assertStringContainsString(str_repeat('a', 64), $html);

        $headings = array_map(fn($h) => htmlspecialchars($h, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), \Supamask\Challenge\Presentation\ContentCatalogue::allHeadings());
        $trusts = array_map(fn($t) => htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), \Supamask\Challenge\Presentation\ContentCatalogue::allTrustFooters());

        $hasHeading = false;
        foreach ($headings as $heading) {
            if (str_contains($html, $heading)) {
                $hasHeading = true;
                break;
            }
        }
        $this->assertTrue($hasHeading, 'HTML must contain one of the random headings');

        $hasTrust = false;
        foreach ($trusts as $trust) {
            if (str_contains($html, $trust)) {
                $hasTrust = true;
                break;
            }
        }
        $this->assertTrue($hasTrust, 'HTML must contain one of the random trust footers');
    }

    public static function profileProvider(): array
    {
        return array_map(static fn (string $name): array => [$name], PresentationProfileCatalogue::names());
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
