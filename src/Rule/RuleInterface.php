<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Rule;

use Zigazou\DateRules\TimeSlot;

/**
 * A rule that describes when time slots are available.
 */
interface RuleInterface
{
    /**
     * @return TimeSlot[] The time slots that apply under this rule, sorted by start time.
     */
    public function getTimeSlots(): array;

    /**
     * @return \DateTimeImmutable[] Specific dates (midnight) explicitly excluded from this rule.
     */
    public function getExceptions(): array;
}
