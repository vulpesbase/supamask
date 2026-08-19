<?php

namespace Supamask\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Supamask\Core\Config;

class ConfigTest extends TestCase
{
    public function testGetTopLevelKey(): void
    {
        $config = new Config(['debug' => true]);

        $this->assertTrue($config->get('debug'));
    }

    public function testDefaultSecurityConfigurationShapeIsApplied(): void
    {
        $config = new Config([]);

        $this->assertTrue($config->get('ip_blocking.enabled'));
        $this->assertTrue($config->get('ip_blocking.antired'));
        $this->assertSame([], $config->get('ip_blocking.rules'));

        $this->assertTrue($config->get('bot_blocking.enabled'));
        $this->assertTrue($config->get('bot_blocking.antired'));
        $this->assertSame([], $config->get('bot_blocking.signatures'));

        $this->assertFalse($config->get('logging.enabled'));
        $this->assertSame('storage/logs', $config->get('logging.directory'));
        $this->assertFalse($config->get('logging.include_query_string'));

        $this->assertSame(403, $config->get('responses.deny.status'));
        $this->assertSame('Access denied', $config->get('responses.deny.body'));
        $this->assertSame('block', $config->get('responses.deny.action'));
        $this->assertNull($config->get('responses.deny.redirect'));
        $this->assertSame(302, $config->get('responses.deny.redirect_status'));
        $this->assertSame([], $config->get('responses.deny.headers'));

        $this->assertSame(403, $config->get('responses.challenge.status'));
        $this->assertSame('Challenge', $config->get('responses.challenge.body'));
        $this->assertSame([], $config->get('responses.challenge.headers'));
    }

    public function testGetNestedValue(): void
    {
        $config = new Config([
            'ip_blocking' => [
                'antired' => true,
                'rules' => ['10.0.0.0/8'],
            ],
        ]);

        $this->assertTrue($config->get('ip_blocking.antired'));
        $this->assertSame(['10.0.0.0/8'], $config->get('ip_blocking.rules'));
    }

    public function testGetDeeplyNestedValue(): void
    {
        $config = new Config([
            'responses' => [
                'deny' => [
                    'status' => 403,
                ],
            ],
        ]);

        $this->assertSame(403, $config->get('responses.deny.status'));
    }

    public function testGetMissingKeyReturnsNull(): void
    {
        $config = new Config([]);

        $this->assertNull($config->get('nonexistent'));
    }

    public function testGetMissingKeyReturnsDefault(): void
    {
        $config = new Config([]);

        $this->assertSame('fallback', $config->get('missing', 'fallback'));
    }

    public function testGetMissingNestedKeyReturnsDefault(): void
    {
        $config = new Config([
            'ip_blocking' => [],
        ]);

        $this->assertSame(
            [],
            $config->get('ip_blocking.rules', [])
        );
    }

    public function testGetPartialPathReturnsDefault(): void
    {
        $config = new Config([
            'a' => 'not-an-array',
        ]);

        $this->assertSame('default', $config->get('a.b.c', 'default'));
    }

    public function testGetReturnsArraySubtree(): void
    {
        $config = new Config([
            'responses' => [
                'deny' => [
                    'status' => 403,
                    'body' => 'Denied',
                ],
            ],
        ]);

        $expected = [
            'action' => 'block',
            'redirect' => null,
            'redirect_status' => 302,
            'status' => 403,
            'body' => 'Denied',
            'headers' => [],
        ];
        $this->assertSame($expected, $config->get('responses.deny'));
    }
}
