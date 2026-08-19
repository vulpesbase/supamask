<?php

namespace Supamask\Tests\Unit\Challenge;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\Presentation\ChallengeViewData;
use Supamask\Challenge\Presentation\HoneypotGenerator;
use Supamask\Challenge\Presentation\HoneypotRenderer;
use Supamask\Challenge\Presentation\PresentationIdentifierGenerator;
use Supamask\Challenge\Presentation\Variants\BrandedPresentation;
use Supamask\Challenge\Presentation\Variants\CheckmarkPresentation;
use Supamask\Challenge\Presentation\Variants\CompactPresentation;
use Supamask\Challenge\Presentation\Variants\ExtendedPresentation;
use Supamask\Challenge\Presentation\Variants\PillPresentation;
use Supamask\Challenge\Presentation\Variants\ShieldPresentation;

final class HoneypotPresentationTest extends TestCase
{
    public function testGeneratorProducesFreshAlphanumericValuesAndValidAttributeName(): void
    {
        $generator = new HoneypotGenerator();
        $first = $generator->generate();
        $second = $generator->generate();

        $this->assertMatchesRegularExpression('/^[a-z0-9]{20}$/', $first->value());
        $this->assertMatchesRegularExpression('/^[a-z0-9]{20}$/', $first->attributeValue());
        $this->assertMatchesRegularExpression('/^[a-z0-9]{20}$/', $first->childValue());
        $this->assertMatchesRegularExpression('/^data-[a-z]$/', $first->attributeName());
        $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]{15}$/', $first->id());
        $this->assertNotSame($first->value(), $second->value());
        $this->assertNotSame($first->id(), $second->id());
    }

    public function testRendererProducesHiddenNonInteractiveMarkup(): void
    {
        $identifiers = (new PresentationIdentifierGenerator())->generate();
        $data = (new HoneypotGenerator())->generate();
        $html = HoneypotRenderer::render($identifiers, $data);

        $this->assertStringContainsString('class="' . $identifiers->honeypot() . '"', $html);
        $this->assertStringContainsString('id="' . $data->id() . '"', $html);
        $this->assertStringContainsString($data->attributeName() . '="' . $data->attributeValue() . '"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('opacity:0', $html);
        $this->assertStringContainsString('pointer-events:none', $html);
        $this->assertStringContainsString($data->value(), $html);
        $this->assertStringContainsString($data->childValue(), $html);
    }

    /** @return array<string, array{0: object, 1: string}> */
    public static function presentations(): array
    {
        return [
            'shield' => [new ShieldPresentation(), 'shield'],
            'pill' => [new PillPresentation(), 'pill'],
            'checkmark' => [new CheckmarkPresentation(), 'checkmark'],
            'compact' => [new CompactPresentation(), 'compact'],
            'branded' => [new BrandedPresentation(), 'branded'],
            'extended-8' => [new ExtendedPresentation(8), 'extended-8'],
            'extended-14' => [new ExtendedPresentation(14), 'extended-14'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('presentations')]
    public function testEveryPresentationVariantContainsTheRenderScopedHoneypot(object $presentation, string $variant): void
    {
        $identifiers = (new PresentationIdentifierGenerator())->generate();
        $data = new ChallengeViewData(
            title: 'Test',
            heading: 'Test heading',
            body: 'Test body',
            buttonLabel: 'Continue',
            trustFooter: 'Secure gate',
            referenceCode: 'L97GYKQI',
            variant: $variant,
            challengeId: 'challenge123',
            verificationToken: 'token123',
            action: '/challenge/challenge123',
            identifiers: $identifiers,
        );

        $html = $presentation->render($data);

        $this->assertStringContainsString('class="' . $identifiers->honeypot() . '"', $html);
        $this->assertStringContainsString('id="' . $data->honeypot()->id() . '"', $html);
        $this->assertStringContainsString($data->honeypot()->value(), $html);
        $this->assertStringContainsString($data->honeypot()->attributeName() . '="' . $data->honeypot()->attributeValue() . '"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('pointer-events:none', $html);
        $this->assertStringNotContainsString('supamask-honeypot', $html);
    }

    public function testChallengeViewDataCreatesHoneypotWhenNotExplicitlySupplied(): void
    {
        $first = $this->viewData();
        $second = $this->viewData();

        $this->assertNotSame($first->honeypot()->value(), $second->honeypot()->value());
        $this->assertNotSame($first->honeypot()->id(), $second->honeypot()->id());
    }

    private function viewData(): ChallengeViewData
    {
        return new ChallengeViewData(
            title: 'Test',
            heading: 'Test heading',
            body: 'Test body',
            buttonLabel: 'Continue',
            trustFooter: 'Secure gate',
            referenceCode: 'L97GYKQI',
            variant: 'shield',
            challengeId: 'challenge123',
            verificationToken: 'token123',
            action: '/challenge/challenge123',
            identifiers: (new PresentationIdentifierGenerator())->generate(),
        );
    }
}
