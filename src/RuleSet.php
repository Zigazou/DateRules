<?php

declare(strict_types=1);

namespace Zigazou\DateRules;

/**
 * An ordered collection of rules.
 *
 * This collection of rules together fully describe a set of date intervals.
 * Rules are sorted chronologically by their start date.
 */
final class RuleSet {

  /**
   * Constructs a new RuleSet.
   *
   * @param \Zigazou\DateRules\Rule\RuleInterface[] $rules
   *   The ordered collection of rules, sorted chronologically.
   */
  public function __construct(
    public readonly array $rules,
  ) {}

}
