<?php

namespace Supamask\Core;

enum Decision
{
    case ALLOW;
    case CHALLENGE;
    case DENY;
}