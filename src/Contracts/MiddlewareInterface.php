<?php

namespace Supamask\Contracts;

use Supamask\Core\Context;
use Supamask\Core\Decision;

interface MiddlewareInterface
{
    public function handle(Context $context): Decision;
}