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

        $expected = ['status' => 403, 'body' => 'Denied'];
        $this->assertSame($expected, $config->get('responses.deny'));
    }
}
