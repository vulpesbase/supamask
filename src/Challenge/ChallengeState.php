<?php

namespace Supamask\Challenge;

enum ChallengeState: string
{
    case PENDING = 'pending';
    case EXPIRED = 'expired';
    case CONSUMED = 'consumed';
}
