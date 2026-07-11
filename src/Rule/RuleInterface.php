<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Rule;

/**
 * A rule that describes when time slots are available.
 */
interface RuleInterface {

  /**
   * Returns the time slots that apply under this rule.
   *
   * @return \Zigazou\DateRules\TimeSlot[]
   *   The time slots, sorted by start time.
   */
  public function getTimeSlots(): array;

  /**
   * Returns the specific dates excluded from this rule.
   *
   * @return \DateTimeImmutable[]
   *   Specific dates (midnight) explicitly excluded from this rule.
   */
  public function getExceptions(): array;

}
