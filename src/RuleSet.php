<?php

declare(strict_types=1);

namespace Zigazou\DateRules;

use Zigazou\DateRules\Rule\RuleInterface;

/**
 * An ordered collection of rules that together fully describe a set of date intervals.
 * Rules are sorted chronologically by their start date.
 */
final class RuleSet
{
    /**
     * @param RuleInterface[] $rules
     */
    public function __construct(
        public readonly array $rules,
    ) {}
}
