<?php

namespace Supamask\Challenge;

final class InMemoryChallengeStore implements ChallengeStoreInterface
{
    /** @var array<string, Challenge> */
    private array $challenges = [];

    public function save(Challenge $challenge): void
    {
        $this->challenges[$challenge->id()] = $challenge;
    }

    public function find(string $id): ?Challenge
    {
        return $this->challenges[$id] ?? null;
    }

    public function clear(Challenge $challenge): void
    {
        unset($this->challenges[$challenge->id()]);
    }
}
