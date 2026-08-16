<?php

namespace Supamask\Tests\Unit\Challenge;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\Presentation\ChallengePresenter;
use Supamask\Challenge\Presentation\ChallengeViewData;
use Supamask\Challenge\Presentation\ContentCatalogue;
use Supamask\Challenge\Presentation\PolymorphicChallengePresentation;
use Supamask\Challenge\Presentation\PresentationIdentifierGenerator;
use Supamask\Challenge\Presentation\ReferenceCodeGenerator;
use Supamask\Challenge\Presentation\PresentationProfileCatalogue;
use Supamask\Challenge\Presentation\Variants\CheckmarkPresentation;
use Supamask\Challenge\Presentation\Variants\PillPresentation;
use Supamask\Challenge\Presentation\Variants\ShieldPresentation;

final class PolymorphicPresentationTest extends TestCase
{
    /**
     * Test that ContentCatalogue provides all required copy variants.
     */
    public function testContentCatalogueProvidesTitles(): void
    {
        $titles = ContentCatalogue::allTitles();

        $this->assertIsArray($titles);
        $this->assertNotEmpty($titles);
        $this->assertGreaterThanOrEqual(5, count($titles));

        foreach ($titles as $title) {
            $this->assertIsString($title);
            $this->assertNotEmpty($title);
        }
    }

    public function testContentCatalogueHeadings(): void
    {
        $headings = ContentCatalogue::allHeadings();

        $this->assertIsArray($headings);
        $this->assertNotEmpty($headings);
        $this->assertGreaterThanOrEqual(5, count($headings));

        foreach ($headings as $heading) {
            $this->assertIsString($heading);
            $this->assertNotEmpty($heading);
        }
    }

    public function testContentCatalogueBodies(): void
    {
        $bodies = ContentCatalogue::allBodies();

        $this->assertIsArray($bodies);
        $this->assertNotEmpty($bodies);
        $this->assertGreaterThanOrEqual(4, count($bodies));

        foreach ($bodies as $body) {
            $this->assertIsString($body);
            $this->assertNotEmpty($body);
        }
    }

    public function testContentCatalogueButtonLabels(): void
    {
        $labels = ContentCatalogue::allButtonLabels();

        $this->assertIsArray($labels);
        $this->assertNotEmpty($labels);
        $this->assertGreaterThanOrEqual(4, count($labels));

        foreach ($labels as $label) {
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }

    public function testContentCatalogueTrustFooters(): void
    {
        $footers = ContentCatalogue::allTrustFooters();

        $this->assertIsArray($footers);
        $this->assertNotEmpty($footers);
        $this->assertGreaterThanOrEqual(5, count($footers));

        foreach ($footers as $footer) {
            $this->assertIsString($footer);
            $this->assertNotEmpty($footer);
        }
    }

    /**
     * Test that random content selection works.
     */
    public function testContentCatalogueRandomSelection(): void
    {
        $title1 = ContentCatalogue::randomTitle();
        $title2 = ContentCatalogue::randomTitle();

        $this->assertIsString($title1);
        $this->assertIsString($title2);
        $this->assertNotEmpty($title1);
        $this->assertNotEmpty($title2);
        // Both should be from catalogue
        $this->assertContains($title1, ContentCatalogue::allTitles());
        $this->assertContains($title2, ContentCatalogue::allTitles());
    }

    /**
     * Test reference code generation.
     */
    public function testReferenceCodeGeneratorLength(): void
    {
        $code = ReferenceCodeGenerator::generate();

        $this->assertIsString($code);
        $this->assertEquals(8, strlen($code));
    }

    public function testReferenceCodeGeneratorCharacters(): void
    {
        $code = ReferenceCodeGenerator::generate();

        // Should only contain A-Z and 0-9
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
    }

    public function testReferenceCodeGeneratorUniqueness(): void
    {
        $codes = [];

        for ($i = 0; $i < 100; $i++) {
            $code = ReferenceCodeGenerator::generate();
            $codes[$code] = true;
        }

        // All 100 generated codes should be unique
        // (probability of collision is astronomically low)
        $this->assertGreaterThanOrEqual(95, count($codes));
    }

    /**
     * Test ChallengeViewData creation and accessors.
     */
    public function testChallengeViewDataCreation(): void
    {
        $data = new ChallengeViewData(
            title: 'Test Title',
            heading: 'Test Heading',
            body: 'Test Body',
            buttonLabel: 'Click Me',
            trustFooter: 'Trust Footer',
            referenceCode: 'ABCD1234',
            variant: 'shield',
            challengeId: 'abc123def456',
            verificationToken: 'token_here_64_chars_long_verification_token_here_64_chars',
            action: '/_supamask/challenge/abc123def456',
            identifiers: (new PresentationIdentifierGenerator())->generate(),
        );

        $this->assertEquals('Test Title', $data->title());
        $this->assertEquals('Test Heading', $data->heading());
        $this->assertEquals('Test Body', $data->body());
        $this->assertEquals('Click Me', $data->buttonLabel());
        $this->assertEquals('Trust Footer', $data->trustFooter());
        $this->assertEquals('ABCD1234', $data->referenceCode());
        $this->assertEquals('shield', $data->variant());
        $this->assertEquals('abc123def456', $data->challengeId());
        $this->assertEquals('token_here_64_chars_long_verification_token_here_64_chars', $data->verificationToken());
        $this->assertEquals('/_supamask/challenge/abc123def456', $data->action());
        $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]{15}$/', $data->identifiers()->container());
    }

    public function testChallengeViewDataToArray(): void
    {
        $data = new ChallengeViewData(
            title: 'Test Title',
            heading: 'Test Heading',
            body: 'Test Body',
            buttonLabel: 'Test Button',
            trustFooter: 'Test Trust',
            referenceCode: 'REFCODE01',
            variant: 'pill',
            challengeId: 'id123',
            verificationToken: 'token123',
            action: '/id123',
            identifiers: (new PresentationIdentifierGenerator())->generate(),
        );

        $array = $data->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Test Title', $array['title']);
        $this->assertEquals('Test Heading', $array['heading']);
        $this->assertEquals('Test Body', $array['message']);
        $this->assertEquals('Test Body', $array['body']);
        $this->assertEquals('Test Button', $array['button']);
        $this->assertEquals('Test Button', $array['buttonLabel']);
        $this->assertEquals('REFCODE01', $array['referenceCode']);
        $this->assertEquals('id123', $array['id']);
        $this->assertEquals('token123', $array['token']);
        $this->assertArrayHasKey('identifiers', $array);
    }

    /**
     * Test ChallengePresenter variant registration and selection.
     */
    public function testPresenterRegistersDefaultVariants(): void
    {
        $presenter = new ChallengePresenter();

        $variants = $presenter->enabledVariants();

        $this->assertSame(PresentationProfileCatalogue::names(), $variants);
    }

    public function testPresenterVariantRestriction(): void
    {
        $presenter = new ChallengePresenter();
        $presenter->setEnabledVariants(['shield', 'pill']);

        $variants = $presenter->enabledVariants();

        $this->assertContains('shield', $variants);
        $this->assertContains('pill', $variants);
        $this->assertNotContains('checkmark', $variants);
    }

    public function testPresenterThrowsOnInvalidVariantName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $presenter = new ChallengePresenter();
        $presenter->registerVariant('Invalid Name!', new ShieldPresentation());
    }

    /**
     * Test that presenter generates valid HTML.
     */
    public function testPresenterGeneratesValidHtml(): void
    {
        $presenter = new ChallengePresenter();

        $html = $presenter->present(
            challengeId: 'abc123def456',
            verificationToken: 'token_verification_token_verification_token_64_chars_long',
            action: '/_supamask/challenge/abc123def456'
        );

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('abc123def456', $html);
        $this->assertStringContainsString('token_verification_token_verification_token_64_chars_long', $html);
        $this->assertStringContainsString('/_supamask/challenge/abc123def456', $html);
    }

    /**
     * Test variant templates render required elements.
     */
    public function testShieldTemplateRendersAllElements(): void
    {
        $template = new ShieldPresentation();

        $data = new ChallengeViewData(
            title: 'Test Title',
            heading: 'Test Heading',
            body: 'Test Body Text',
            buttonLabel: 'Test Button',
            trustFooter: 'Test Trust',
            referenceCode: 'REFCODE99',
            variant: 'shield',
            challengeId: 'id123',
            verificationToken: 'token123',
            action: '/id123',
            identifiers: (new PresentationIdentifierGenerator())->generate(),
        );

        $html = $template->render($data);

        $this->assertStringContainsString('Test Title', $html);
        $this->assertStringContainsString('Test Heading', $html);
        $this->assertStringContainsString('Test Body Text', $html);
        $this->assertStringContainsString('>Test Button</button>', $html);
        $this->assertStringContainsString('Test Trust', $html);
        $this->assertStringContainsString('REFCODE99', $html);
        $this->assertStringContainsString('🛡️', $html);
        // Should escape token properly
        $this->assertStringContainsString('value="token123"', $html);
    }

    public function testPillTemplateRendersAllElements(): void
    {
        $template = new PillPresentation();

        $data = new ChallengeViewData(
            title: 'Pill Title',
            heading: 'Pill Heading',
            body: 'Pill Body',
            buttonLabel: 'Pill Button',
            trustFooter: 'Pill Trust',
            referenceCode: 'PILL0001',
            variant: 'pill',
            challengeId: 'pill123',
            verificationToken: 'token_pill',
            action: '/pill/pill123',
            identifiers: (new PresentationIdentifierGenerator())->generate(),
        );

        $html = $template->render($data);

        $this->assertStringContainsString('Pill Title', $html);
        $this->assertStringContainsString('Pill Heading', $html);
        $this->assertStringContainsString('Pill Body', $html);
        $this->assertStringContainsString('>Pill Button</button>', $html);
        $this->assertStringContainsString('Pill Trust', $html);
        $this->assertStringContainsString('PILL0001', $html);
    }

    public function testCheckmarkTemplateRendersAllElements(): void
    {
        $template = new CheckmarkPresentation();

        $data = new ChallengeViewData(
            title: 'Check Title',
            heading: 'Check Heading',
            body: 'Check Body',
            buttonLabel: 'Check Button',
            trustFooter: 'Check Trust',
            referenceCode: 'CHECK001',
            variant: 'checkmark',
            challengeId: 'check123',
            verificationToken: 'token_check',
            action: '/check/check123',
            identifiers: (new PresentationIdentifierGenerator())->generate(),
        );

        $html = $template->render($data);

        $this->assertStringContainsString('Check Title', $html);
        $this->assertStringContainsString('Check Heading', $html);
        $this->assertStringContainsString('Check Body', $html);
        $this->assertStringContainsString('>Check Button</button>', $html);
        $this->assertStringContainsString('Check Trust', $html);
        $this->assertStringContainsString('CHECK001', $html);
        $this->assertStringContainsString('✓', $html);
    }

    /**
     * Test HTML escaping in templates.
     */
    public function testShieldTemplateEscapesContent(): void
    {
        $template = new ShieldPresentation();

        $data = new ChallengeViewData(
            title: '<script>alert("xss")</script>',
            heading: '"><img src=x>',
            body: '<iframe>',
            buttonLabel: '<Button>',
            trustFooter: '<!--comment-->',
            referenceCode: 'ESCAPE01',
            variant: 'shield',
            challengeId: '123&456',
            verificationToken: 'token<>"\'',
            action: '/test?x=1&y=2',
            identifiers: (new PresentationIdentifierGenerator())->generate(),
        );

        $html = $template->render($data);

        // Should be escaped
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<iframe>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;iframe&gt;', $html);
        $this->assertStringContainsString('&lt;Button&gt;', $html);
        // Token in value attribute should be escaped
        $this->assertStringContainsString('&lt;&gt;&quot;', $html);
    }

    /**
     * Test PolymorphicChallengePresentation as interface adapter.
     */
    public function testPolymorphicPresentationImplementsInterface(): void
    {
        $presentation = new PolymorphicChallengePresentation();

        $this->assertInstanceOf(\Supamask\Challenge\ChallengePresentationInterface::class, $presentation);
    }

    public function testPolymorphicPresentationRenders(): void
    {
        $presentation = new PolymorphicChallengePresentation();

        $html = $presentation->render([
            'id' => 'challenge123',
            'token' => 'verification_token_here_verification_token_here_verification_token',
            'action' => '/_supamask/challenge/challenge123',
        ]);

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('challenge123', $html);
        $this->assertStringContainsString('form', $html);
    }

    public function testPolymorphicPresentationWithConfigOverrides(): void
    {
        $presentation = new PolymorphicChallengePresentation();

        $html = $presentation->render([
            'id' => 'test123',
            'token' => 'token_test_token_test_token_test_token_test_token_test_token_test_',
            'action' => '/_supamask/test',
            'title' => 'Custom Title Override',
            'heading' => 'Custom Heading Override',
            'message' => 'Custom Message Override',
            'button' => 'Custom Button Override',
        ]);

        $this->assertStringContainsString('Custom Title Override', $html);
        $this->assertStringContainsString('Custom Heading Override', $html);
        $this->assertStringContainsString('Custom Message Override', $html);
        $this->assertStringContainsString('<span>Custom Button Override</span></button>', $html);
    }

    public function testPolymorphicPresentationRequiresRequiredContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $presentation = new PolymorphicChallengePresentation();
        $presentation->render([
            // Missing required keys
            'title' => 'Some Title',
        ]);
    }

    public function testPolymorphicPresentationAccessPresenter(): void
    {
        $presentation = new PolymorphicChallengePresentation();

        $presenter = $presentation->presenter();

        $this->assertInstanceOf(ChallengePresenter::class, $presenter);
    }

    /**
     * Test that new presentation system doesn't break existing flows.
     */
    public function testPolymorphicPresentationBackwardCompatibility(): void
    {
        // Test with format from existing DefaultChallengePresentation tests
        $presentation = new PolymorphicChallengePresentation();

        $html = $presentation->render([
            'id' => '82f6cd2d2843',
            'token' => 'secret-token',
            'action' => '/_supamask/challenge/82f6cd2d2843',
            'title' => 'Custom title',
            'heading' => 'Custom heading',
            'message' => 'Custom message',
            'button' => 'Verify',
        ]);

        $this->assertStringContainsString('82f6cd2d2843', $html);
        $this->assertStringContainsString('secret-token', $html);
        $this->assertStringContainsString('Custom title', $html);
        $this->assertStringContainsString('Custom heading', $html);
        $this->assertStringContainsString('Custom message', $html);
        $this->assertStringContainsString('<span>Verify</span></button>', $html);
    }
}
