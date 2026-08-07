<?php

namespace Supamask\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Context;
use Supamask\Core\Config;
use Supamask\Core\Decision;
use Supamask\Http\Request;
use Supamask\Middleware\Pipeline;

class PipelineTest extends TestCase
{
    private function makeContext(): Context
    {
        return new Context(new Request(), new Config());
    }

    private function makeMiddleware(Decision $decision): MiddlewareInterface
    {
        return new class($decision) implements MiddlewareInterface {
            public function __construct(private Decision $decision) {}

            public function handle(Context $context): Decision
            {
                return $this->decision;
            }
        };
    }

    // ── Empty pipeline ──

    public function testEmptyPipelineReturnsAllow(): void
    {
        $pipeline = new Pipeline();

        $this->assertSame(
            Decision::ALLOW,
            $pipeline->process($this->makeContext())
        );
    }

    // ── Single middleware ──

    public function testSingleMiddlewareReturningAllow(): void
    {
        $pipeline = new Pipeline();
        $pipeline->pipe($this->makeMiddleware(Decision::ALLOW));

        $this->assertSame(
            Decision::ALLOW,
            $pipeline->process($this->makeContext())
        );
    }

    public function testSingleMiddlewareReturningDeny(): void
    {
        $pipeline = new Pipeline();
        $pipeline->pipe($this->makeMiddleware(Decision::DENY));

        $this->assertSame(
            Decision::DENY,
            $pipeline->process($this->makeContext())
        );
    }

    public function testSingleMiddlewareReturningChallenge(): void
    {
        $pipeline = new Pipeline();
        $pipeline->pipe($this->makeMiddleware(Decision::CHALLENGE));

        $this->assertSame(
            Decision::CHALLENGE,
            $pipeline->process($this->makeContext())
        );
    }

    // ── Short-circuit on DENY ──

    public function testDenyShortCircuitsRemainingMiddleware(): void
    {
        $called = false;

        $tracker = new class($called) implements MiddlewareInterface {
            private bool $called;

            public function __construct(bool &$called)
            {
                $this->called = &$called;
            }

            public function handle(Context $context): Decision
            {
                $this->called = true;
                return Decision::ALLOW;
            }
        };

        $pipeline = new Pipeline();
        $pipeline->pipe($this->makeMiddleware(Decision::DENY));
        $pipeline->pipe($tracker);

        $result = $pipeline->process($this->makeContext());

        $this->assertSame(Decision::DENY, $result);
        $this->assertFalse($called);
    }

    // ── Ordering ──

    public function testMiddlewareExecutesInOrder(): void
    {
        $order = [];

        $first = new class($order) implements MiddlewareInterface {
            private array $order;

            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            public function handle(Context $context): Decision
            {
                $this->order[] = 'first';
                return Decision::ALLOW;
            }
        };

        $second = new class($order) implements MiddlewareInterface {
            private array $order;

            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            public function handle(Context $context): Decision
            {
                $this->order[] = 'second';
                return Decision::ALLOW;
            }
        };

        $pipeline = new Pipeline();
        $pipeline->pipe($first);
        $pipeline->pipe($second);

        $pipeline->process($this->makeContext());

        $this->assertSame(['first', 'second'], $order);
    }

    // ── Fluent API ──

    public function testPipeReturnsSelfForChaining(): void
    {
        $pipeline = new Pipeline();

        $result = $pipeline->pipe($this->makeMiddleware(Decision::ALLOW));

        $this->assertSame($pipeline, $result);
    }

    // ── Multiple ALLOW then DENY ──

    public function testMultipleAllowThenDeny(): void
    {
        $pipeline = new Pipeline();
        $pipeline->pipe($this->makeMiddleware(Decision::ALLOW));
        $pipeline->pipe($this->makeMiddleware(Decision::ALLOW));
        $pipeline->pipe($this->makeMiddleware(Decision::DENY));

        $this->assertSame(
            Decision::DENY,
            $pipeline->process($this->makeContext())
        );
    }
}
