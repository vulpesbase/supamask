<?php

namespace Supamask\Core;

use Supamask\Http\Request;

class Context
{
    public function __construct(
        private Request $request,
        private Config $config
    ) {
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function config(): Config
    {
        return $this->config;
    }
}