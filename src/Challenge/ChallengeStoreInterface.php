<?php

namespace Supamask\Challenge;

interface ChallengeStoreInterface
{
    public function save(Challenge $challenge): void;

    public function find(string $id): ?Challenge;
}
