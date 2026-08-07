<?php

namespace Supamask\Security;

use Supamask\Http\Request;

class Fingerprint
{
    public function __construct(
        private Request $request
    ) {
    }

    public function ip(): string
    {
        return $this->request->ip();
    }

    public function userAgent(): string
    {
        return $this->request->userAgent();
    }

    public function method(): string
    {
        return $this->request->method();
    }

    public function uri(): string
    {
        return $this->request->uri();
    }

    public function host(): string
    {
        return $this->request->host();
    }
}