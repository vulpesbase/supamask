<?php

namespace Supamask\Middleware;

use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Context;
use Supamask\Core\Decision;

class Pipeline
{
    /**
     * @var MiddlewareInterface[]
     */
    private array $middleware = [];

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    public function process(Context $context): Decision
    {
        foreach ($this->middleware as $middleware) {
            $decision = $middleware->handle($context);

            if ($decision !== Decision::ALLOW) {
                return $decision;
            }
        }

        return Decision::ALLOW;
    }
}