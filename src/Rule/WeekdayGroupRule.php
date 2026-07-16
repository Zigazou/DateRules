<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Rule;

/**
 * A group of weekday sub-rules sharing the same date range.
 *
 * Used when weekday rules with different time slots and different weekday sets
 * share the same overall date range and should be presented together as a
 * labelled block.
 */
final class WeekdayGroupRule implements RuleInterface {

  /**
   * Constructs a new WeekdayGroupRule.
   *
   * @param \Zigazou\DateRules\Rule\WeekdayRule[] $subRules
   *   The sub-rules that make up this group, ordered for display.
   * @param \DateTimeImmutable $startDate
   *   The overall start date of the group (the widest span).
   * @param \DateTimeImmutable $endDate
   *   The overall end date of the group (the widest span).
   */
  public function __construct(
    public readonly array $subRules,
    public readonly \DateTimeImmutable $startDate,
    public readonly \DateTimeImmutable $endDate,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getTimeSlots(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getExceptions(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function showRule(int $indentLevel = 0): void {
    $indent = str_repeat('  ', $indentLevel);
    $start  = $this->startDate->format('Y-m-d');
    $end    = $this->endDate->format('Y-m-d');

    print("{$indent}WeekdayGroupRule {$start} -> {$end}:\n");
    foreach ($this->subRules as $subRule) {
      $subRule->showRule($indentLevel + 1);
    }

  }

}
