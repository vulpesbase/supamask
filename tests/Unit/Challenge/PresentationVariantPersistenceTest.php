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

        $first = $presenter->present('aaaaaaaaaaaa', str_repeat('a', 64), '/aaaaaaaaaaaa');
        preg_match('/<body[^>]*data-supamask-bg="([^"]+)"/', $first, $m1);

        $second = $presenter->present('aaaaaaaaaaaa', str_repeat('b', 64), '/aaaaaaaaaaaa');
        preg_match('/<body[^>]*data-supamask-bg="([^"]+)"/', $second, $m2);

        self::assertNotEmpty($m1[1] ?? null);
        self::assertSame($m1[1], $m2[1]);
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
