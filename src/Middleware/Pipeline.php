<?php

namespace Supamask\Middleware;

use Supamask\Contracts\MiddlewareInterface;
use Supamask\Http\Request;
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

    public function process(Request $request): Decision
    {
        foreach ($this->middleware as $middleware) {
            $decision = $middleware->handle($request);

            if ($decision !== Decision::ALLOW) {
                return $decision;
            }
        }

        return Decision::ALLOW;
    }
}