<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Rule;

/**
 * A rule that applies to a single day within a date range.
 *
 * Example: "on July 1 from 13:30 to 18:15".
 */
final class SingleDayRule implements RuleInterface {
  /**
   * Simulate startDate properties for compatibility with RuleInterface.
   *
   * @var \DateTimeImmutable
   *   The single day (midnight) the rule is in effect.
   */
  public \DateTimeImmutable $startDate;

  /**
   * Simulate endDate properties for compatibility with RuleInterface.
   *
   * @var \DateTimeImmutable
   *   The single day (midnight) the rule is in effect.
   */
  public \DateTimeImmutable $endDate;

  /**
   * Constructs a new SingleDayRule.
   *
   * @param \DateTimeImmutable $date
   *   First day (midnight) the rule is in effect.
   * @param \Zigazou\DateRules\TimeSlot[] $timeSlots
   *   Sorted list of time slots that apply on those days.
   */
  public function __construct(
    public readonly \DateTimeImmutable $date,
    public readonly array $timeSlots,
  ) {
    $this->startDate = $date;
    $this->endDate   = $date;
  }

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
    return [];
  }

  /**
   * Returns a key that uniquely identifies the single day.
   *
   * Used by the Analyzer to merge rules that share the same structure.
   *
   * @return string
   *   A unique structure key for the single day.
   */
  public function structureKey(): string {
    return $this->date->format('Y-m-d');
  }

  /**
   * {@inheritdoc}
   */
  public function showRule(int $indentLevel = 0): void {
    $indent  = str_repeat('  ', $indentLevel);
    $indent2 = str_repeat('  ', $indentLevel + 1);
    $indent3 = str_repeat('  ', $indentLevel + 2);
    $date = $this->date->format('Y-m-d');

    print("{$indent}SingleDayRule {$date}:\n");
    print("{$indent2}Time slots:\n");
    foreach ($this->timeSlots as $timeSlot) {
      print("{$indent3}- {$timeSlot->startHour}:{$timeSlot->startMinute} -> {$timeSlot->endHour}:{$timeSlot->endMinute}\n");
    }
  }

}
