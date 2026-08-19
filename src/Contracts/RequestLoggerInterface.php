<?php

namespace Supamask\Contracts;

use Supamask\Core\Context;
use Supamask\Core\Decision;

interface RequestLoggerInterface
{
    public function log(Context $context, Decision $decision): void;
}
