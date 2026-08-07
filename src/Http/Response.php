<?php

namespace Supamask\Http;

class Response
{
    public function __construct(
        private int $status = 200,
        private string $body = '',
        private array $headers = []
    ) {}

    public function send(): never
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo $this->body;

        exit;
    }
}
