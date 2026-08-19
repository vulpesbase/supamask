<?php

namespace Supamask\Tests\Unit\Challenge;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\Presentation\ChallengePresenter;
use Supamask\Challenge\Presentation\PresentationVariantStore;

final class PresentationVariantPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testVariantRemainsStableAcrossRendersForSameChallenge(): void
    {
        $presenter = new ChallengePresenter();
        $store = new PresentationVariantStore();

        $presenter->present('aaaaaaaaaaaa', str_repeat('a', 64), '/aaaaaaaaaaaa');
        $firstVariant = $store->get('aaaaaaaaaaaa');

        $presenter->present('aaaaaaaaaaaa', str_repeat('b', 64), '/aaaaaaaaaaaa');
        $secondVariant = $store->get('aaaaaaaaaaaa');

        self::assertNotEmpty($firstVariant);
        self::assertSame($firstVariant, $secondVariant);
    }

    public function testEachChallengeGetsItsOwnPersistedVariantAssignment(): void
    {
        $presenter = new ChallengePresenter();
        $store = new PresentationVariantStore();

        $presenter->present('aaaaaaaaaaaa', str_repeat('a', 64), '/aaaaaaaaaaaa');
        $presenter->present('bbbbbbbbbbbb', str_repeat('b', 64), '/bbbbbbbbbbbb');

        self::assertNotNull($store->get('aaaaaaaaaaaa'));
        self::assertNotNull($store->get('bbbbbbbbbbbb'));
    }

    public function testSuccessLifecycleCanReleaseVariantAfterRendering(): void
    {
        $presenter = new ChallengePresenter();
        $store = new PresentationVariantStore();

        $presenter->present('aaaaaaaaaaaa', str_repeat('a', 64), '/aaaaaaaaaaaa');
        self::assertNotNull($store->get('aaaaaaaaaaaa'));

        $presenter->forgetVariant('aaaaaaaaaaaa');

        self::assertNull($store->get('aaaaaaaaaaaa'));
    }
}
