<?php

namespace Supamask\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Supamask\Core\Config;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Http\Request;
use Supamask\Security\RequestLogger\FileRequestLogger;

final class RequestLoggerTest extends TestCase
{
    private array $serverBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    public function testLoggerWritesDecisionWithoutQueryStringByDefault(): void
    {
        $dir = sys_get_temp_dir() . '/supamask-log-' . bin2hex(random_bytes(4));
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/index.php?token=secret&user=Rogue';
        $_SERVER['HTTP_USER_AGENT'] = 'TestBrowser';

        $context = new Context(new Request(), new Config());
        $context->setDecisionReason('challenge_required');
        (new FileRequestLogger($dir))->log($context, Decision::CHALLENGE);

        $contents = file_get_contents($dir . '/bouncer.log');
        $event = json_decode(trim($contents), true);

        $this->assertSame('CHALLENGE', $event['decision']);
        $this->assertSame('/index.php', $event['uri']);
        $this->assertSame('203.0.113.10', $event['ip']);
        $this->assertSame('challenge_required', $event['reason']);
        $this->assertStringNotContainsString('secret', $contents);

        $this->removeDirectory($dir);
    }

    public function testLoggerCanIncludeQueryStringWhenExplicitlyEnabled(): void
    {
        $dir = sys_get_temp_dir() . '/supamask-log-' . bin2hex(random_bytes(4));
        $_SERVER['REMOTE_ADDR'] = '203.0.113.11';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/index.php?user=Rogue&campaignId=ABC123';

        $context = new Context(new Request(), new Config());
        (new FileRequestLogger($dir, true))->log($context, Decision::ALLOW);

        $event = json_decode(trim(file_get_contents($dir . '/bouncer.log')), true);
        $this->assertSame('/index.php?user=Rogue&campaignId=ABC123', $event['uri']);
        $this->assertSame('ALLOW', $event['decision']);

        $this->removeDirectory($dir);
    }

    public function testLoggerFailureDoesNotThrow(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.12';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $context = new Context(new Request(), new Config());
        $logger = new FileRequestLogger('');

        $logger->log($context, Decision::DENY);
        $this->addToAssertionCount(1);
    }

    private function removeDirectory(string $dir): void
    {
        $file = $dir . '/bouncer.log';
        if (is_file($file)) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
