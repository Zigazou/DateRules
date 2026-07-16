<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Rule;

/**
 * A rule that applies to every calendar day within a continuous date range.
 *
 * Example: "from July 1 to July 30 from 23:00 to 23:59".
 */
final class DateRangeRule implements RuleInterface {

  /**
   * Constructs a new DateRangeRule.
   *
   * @param \DateTimeImmutable $startDate
   *   First day (midnight) of the range.
   * @param \DateTimeImmutable $endDate
   *   Last day (midnight) of the range.
   * @param \Zigazou\DateRules\TimeSlot[] $timeSlots
   *   Sorted list of time slots that apply every day.
   * @param \DateTimeImmutable[] $exceptions
   *   Specific dates (midnight) excluded from this rule.
   */
  public function __construct(
    public readonly \DateTimeImmutable $startDate,
    public readonly \DateTimeImmutable $endDate,
    public readonly array $timeSlots,
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
   * {@inheritdoc}
   */
  public function showRule(int $indentLevel = 0): void {
    $indent  = str_repeat('  ', $indentLevel);
    $indent2 = str_repeat('  ', $indentLevel + 1);
    $indent3 = str_repeat('  ', $indentLevel + 2);
    $start   = $this->startDate->format('Y-m-d');
    $end     = $this->endDate->format('Y-m-d');

    print("{$indent}DateRangeRule {$start} -> {$end}:\n");
    print("{$indent2}Time slots:\n");
    foreach ($this->timeSlots as $timeSlot) {
      print("{$indent3}- {$timeSlot->startHour}:{$timeSlot->startMinute} -> {$timeSlot->endHour}:{$timeSlot->endMinute}\n");
    }

    if (!empty($this->exceptions)) {
      print("{$indent2}Exceptions:\n");
      foreach ($this->exceptions as $exception) {
        print("{$indent3}- " . $exception->format('Y-m-d') . "\n");
      }
    }
  }

}
