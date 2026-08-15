<?php

namespace Supamask\Entry;

use InvalidArgumentException;
use Supamask\Core\Decision;

/**
 * Maps an EntryClassification to a routing Decision.
 *
 * Keeps classification (what type of traffic is this?) separate from
 * policy evaluation (what should we do with it?), following the
 * single-responsibility principle.
 *
 * Configuration example:
 *
 * ```php
 * 'entry' => [
 *     'policy' => [
 *         'direct'   => 'allow',      // direct navigation passes through
 *         'referred' => 'challenge',  // referred traffic gets challenged
 *         'seeded'   => 'challenge',  // seeded traffic gets challenged
 *         'unknown'  => 'allow',      // unknown referrer passes through
 *     ],
 * ]
 * ```
 *
 * Accepted policy values: 'allow', 'challenge', 'deny'.
 * An unrecognised value throws InvalidArgumentException at construction time.
 */
final class EntryClassificationPolicy
{
    private const VALID_DECISIONS = ['allow', 'challenge', 'deny'];

    /** @var array<string, Decision> */
    private array $map;

    /**
     * @param array<string, string> $config  Map of classification name → decision string.
     *
     * @throws InvalidArgumentException On invalid classification key or decision value.
     */
    public function __construct(array $config = [])
    {
        $defaults = [
            'direct'   => 'allow',
            'referred' => 'allow',
            'seeded'   => 'challenge',
            'unknown'  => 'allow',
        ];

        $merged = array_merge($defaults, $config);

        $this->map = [];

        foreach ($merged as $classificationName => $decisionString) {
            $this->map[$classificationName] = $this->parseDecision(
                $classificationName,
                $decisionString,
            );
        }
    }

    /**
     * Returns the Decision for the given classification.
     *
     * @throws InvalidArgumentException If the classification has no registered policy.
     */
    public function decide(EntryClassification $classification): Decision
    {
        $key = strtolower($classification->name);

        if (!isset($this->map[$key])) {
            throw new InvalidArgumentException(
                sprintf('No entry policy registered for classification "%s".', $classification->name)
            );
        }

        return $this->map[$key];
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function parseDecision(string $classificationName, mixed $value): Decision
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Entry policy value for "%s" must be a string; %s given.',
                    $classificationName,
                    gettype($value),
                )
            );
        }

        $lower = strtolower($value);

        if (!in_array($lower, self::VALID_DECISIONS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid entry policy decision "%s" for classification "%s". '
                    . 'Accepted values: %s.',
                    $value,
                    $classificationName,
                    implode(', ', self::VALID_DECISIONS),
                )
            );
        }

        return match ($lower) {
            'allow'     => Decision::ALLOW,
            'challenge' => Decision::CHALLENGE,
            'deny'      => Decision::DENY,
        };
    }
}
