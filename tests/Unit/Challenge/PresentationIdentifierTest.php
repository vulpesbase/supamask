<?php

namespace Supamask\Tests\Unit\Challenge;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Supamask\Challenge\Presentation\ChallengeViewData;
use Supamask\Challenge\Presentation\PresentationIdentifierGenerator;
use Supamask\Challenge\Presentation\PresentationIdentifierSet;
use Supamask\Challenge\Presentation\Variants\CheckmarkPresentation;
use Supamask\Challenge\Presentation\Variants\PillPresentation;
use Supamask\Challenge\Presentation\Variants\ShieldPresentation;

final class PresentationIdentifierTest extends TestCase
{
    public function testGeneratedIdentifiersHaveValidFormat(): void
    {
        $identifiers = (new PresentationIdentifierGenerator())->generate()->toArray();

        foreach ($identifiers as $identifier) {
            $this->assertNotSame('', $identifier);
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]{15}$/', $identifier);
        }
    }

    public function testIdentifierSetContainsAllSemanticIdentifiersAndIsUnique(): void
    {
        $identifiers = (new PresentationIdentifierGenerator())->generate()->toArray();
        $expected = [
            'container', 'card', 'icon', 'iconWrapper', 'heading', 'body', 'content',
            'form', 'button', 'spinner', 'footer', 'divider', 'trust', 'reference',
            'honeypot', 'eyebrow',
        ];

        $this->assertSame($expected, array_keys($identifiers));
        $this->assertCount(count($identifiers), array_unique($identifiers));
    }

    public function testEachGenerationReturnsANewIdentifierSet(): void
    {
        $generator = new PresentationIdentifierGenerator();

        $this->assertNotSame($generator->generate()->toArray(), $generator->generate()->toArray());
    }

    public function testRenderingTheSameVariantTwiceUsesDifferentIdentifierSets(): void
    {
        $generator = new PresentationIdentifierGenerator();
        $firstIdentifiers = $generator->generate();
        $secondIdentifiers = $generator->generate();
        $presentation = new ShieldPresentation();

        $firstHtml = $presentation->render($this->viewData('shield', $firstIdentifiers));
        $secondHtml = $presentation->render($this->viewData('shield', $secondIdentifiers));

        $this->assertNotSame($firstIdentifiers->toArray(), $secondIdentifiers->toArray());
        $this->assertStringContainsString('class="' . $firstIdentifiers->container() . '"', $firstHtml);
        $this->assertStringContainsString('class="' . $secondIdentifiers->container() . '"', $secondHtml);
        $this->assertStringNotContainsString('class="' . $firstIdentifiers->container() . '"', $secondHtml);
    }

    #[DataProvider('presentationVariants')]
    public function testTemplatesUseGeneratedIdentifiersAndKeepTheFunctionalMarkup(
        object $presentation,
        string $variant,
        array $usedIdentifierNames
    ): void {
        $identifiers = (new PresentationIdentifierGenerator())->generate();
        $data = new ChallengeViewData(
            title: 'Identifier test',
            heading: 'Continue verification',
            body: 'Please continue.',
            buttonLabel: 'Continue',
            trustFooter: 'Protected by Supamask',
            referenceCode: 'L97GYKQI',
            variant: $variant,
            challengeId: 'challenge123',
            verificationToken: 'verification-token',
            action: '/_supamask/challenge/challenge123',
            identifiers: $identifiers,
        );

        $html = $presentation->render($data);

        foreach ($usedIdentifierNames as $name) {
            $identifier = $identifiers->toArray()[$name];
            $this->assertStringContainsString($identifier, $html);
            $this->assertStringContainsString('class="' . $identifier . '"', $html);
        }

        $this->assertStringNotContainsString('supamask-', $html);
        $this->assertSame(1, substr_count($html, 'type="submit"'));
        $this->assertStringContainsString('>Continue</button>', $html);
        $this->assertStringContainsString('L97GYKQI', $html);
        $this->assertStringContainsString('name="token"', $html);
    }

    /** @return array<string, array{0: object, 1: string, 2: array<int, string>}> */
    public static function presentationVariants(): array
    {
        return [
            'shield' => [new ShieldPresentation(), 'shield', ['container', 'icon', 'heading', 'body', 'form', 'button', 'trust', 'reference']],
            'pill' => [new PillPresentation(), 'pill', ['container', 'heading', 'content', 'body', 'form', 'button', 'footer', 'trust', 'reference']],
            'checkmark' => [new CheckmarkPresentation(), 'checkmark', ['container', 'iconWrapper', 'icon', 'heading', 'body', 'form', 'button', 'divider', 'trust', 'reference']],
        ];
    }

    private function viewData(string $variant, PresentationIdentifierSet $identifiers): ChallengeViewData
    {
        return new ChallengeViewData(
            title: 'Identifier test',
            heading: 'Continue verification',
            body: 'Please continue.',
            buttonLabel: 'Continue',
            trustFooter: 'Protected by Supamask',
            referenceCode: 'L97GYKQI',
            variant: $variant,
            challengeId: 'challenge123',
            verificationToken: 'verification-token',
            action: '/_supamask/challenge/challenge123',
            identifiers: $identifiers,
        );
    }
}
