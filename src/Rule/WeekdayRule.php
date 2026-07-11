<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Rule;

/**
 * A rule that repeats on specific days of the week within a date range.
 *
 * Example: "every Monday, Wednesday, Friday from 13:30 to 18:15,
 *           from April 13 to January 4 (except August 15)".
 */
final class WeekdayRule implements RuleInterface {

  /**
   * Constructs a new WeekdayRule.
   *
   * @param int[] $weekdays
   *   ISO 8601 day numbers: 1 = Monday ... 7 = Sunday.
   * @param \Zigazou\DateRules\TimeSlot[] $timeSlots
   *   Sorted list of time slots that apply on those days.
   * @param \DateTimeImmutable $startDate
   *   First day (midnight) the rule is in effect.
   * @param \DateTimeImmutable $endDate
   *   Last day (midnight) the rule is in effect.
   * @param \DateTimeImmutable[] $exceptions
   *   Specific dates (midnight) excluded from this rule.
   */
  public function __construct(
    public readonly array $weekdays,
    public readonly array $timeSlots,
    public readonly \DateTimeImmutable $startDate,
    public readonly \DateTimeImmutable $endDate,
    public readonly array $exceptions = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getTimeSlots(): array {
    return $this->timeSlots;
  }

  /**
   * {@inheritdoc}
   */
  public function getExceptions(): array {
    return $this->exceptions;
  }

  /**
   * Returns a key that uniquely identifies the weekday set and date range.
   *
   * Used by the Analyzer to merge rules that share the same structure.
   *
   * @return string
   *   A unique structure key combining weekdays, start date, and end date.
   */
  public function structureKey(): string {
    return implode(',', $this->weekdays)
      . '|' . $this->startDate->format('Y-m-d')
      . '|' . $this->endDate->format('Y-m-d');
  }

}
