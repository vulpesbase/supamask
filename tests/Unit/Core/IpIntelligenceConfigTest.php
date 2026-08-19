<?php

namespace Supamask\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Supamask\Core\Config;

final class IpIntelligenceConfigTest extends TestCase
{
    public function testIntelligenceFeaturesAreDisabledByDefault(): void
    {
        $config = new Config([]);

        $this->assertFalse($config->get('block_vpn'));
        $this->assertFalse($config->get('detect_isp'));
        $this->assertSame([], $config->get('isp_exclusions'));
        $this->assertSame(2, $config->get('ip_intelligence.timeout'));
        $this->assertSame(3600, $config->get('ip_intelligence.cache_ttl'));
        $this->assertTrue($config->get('ip_intelligence.skip_private'));
    }

    public function testIntelligenceConfigurationCanBeCustomized(): void
    {
        $config = new Config([
            'block_vpn' => true,
            'detect_isp' => true,
            'isp_exclusions' => ['AS14061', 'DigitalOcean'],
            'ip_intelligence' => [
                'token' => 'test-token',
                'timeout' => 1,
                'cache_ttl' => 600,
            ],
        ]);

        $this->assertTrue($config->get('block_vpn'));
        $this->assertTrue($config->get('detect_isp'));
        $this->assertSame(['AS14061', 'DigitalOcean'], $config->get('isp_exclusions'));
        $this->assertSame('test-token', $config->get('ip_intelligence.token'));
        $this->assertSame(1, $config->get('ip_intelligence.timeout'));
        $this->assertSame(600, $config->get('ip_intelligence.cache_ttl'));
    }
}
