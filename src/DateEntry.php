<?php

declare(strict_types=1);

namespace Zigazou\DateRules;

/**
 * Represents a single input date+time interval (one line of the input list).
 *
 * Intervals always fall within a single calendar day; they never span midnight.
 */
final class DateEntry {

  public function __construct(
    public readonly \DateTimeImmutable $start,
    public readonly \DateTimeImmutable $end,
  ) {}

  /**
   * Returns the TimeSlot (time-only portion) of this entry.
   *
   * @return \Zigazou\DateRules\TimeSlot
   *   The time slot for this entry.
   */
  public function getTimeSlot(): TimeSlot {
    return TimeSlot::fromDateTimeImmutables($this->start, $this->end);
  }

  /**
   * Returns midnight of the calendar day this entry falls on.
   *
   * @return \DateTimeImmutable
   *   The midnight datetime of the calendar day.
   */
  public function getDate(): \DateTimeImmutable {
    return $this->start->setTime(0, 0, 0);
  }

  /**
   * Returns the ISO 8601 day of the week.
   *
   * 1 = Monday ... 7 = Sunday.
   *
   * @return int
   *   The ISO 8601 day-of-week number.
   */
  public function getDayOfWeek(): int {
    return (int) $this->start->format('N');
  }

}
