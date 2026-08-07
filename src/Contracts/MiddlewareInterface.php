<?php

namespace Supamask\Contracts;

use Supamask\Http\Request;
use Supamask\Core\Decision;

interface MiddlewareInterface
{
    public function handle(Request $request): Decision;
}