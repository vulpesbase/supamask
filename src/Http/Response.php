<?php

namespace Supamask\Http;

class Response
{
    public function __construct(
        private int $status = 200,
        private string $body = ''
    ) {}

    public function send(): never
    {
        http_response_code($this->status);

        echo $this->body;

        exit;
    }
}
