<?php

declare(strict_types=1);

namespace Zigazou\DateRules;

/**
 * Represents a time range within a single day (start time → end time).
 * This is a pure time value object, with no date component.
 */
final class TimeSlot
{
    public function __construct(
        public readonly int $startHour,
        public readonly int $startMinute,
        public readonly int $endHour,
        public readonly int $endMinute,
    ) {}

    public static function fromDateTimeImmutables(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): self {
        return new self(
            (int) $start->format('G'),
            (int) $start->format('i'),
            (int) $end->format('G'),
            (int) $end->format('i'),
        );
    }

    /**
     * Unique string key identifying this time slot (e.g. "13:30-18:15").
     */
    public function key(): string
    {
        return sprintf(
            '%02d:%02d-%02d:%02d',
            $this->startHour,
            $this->startMinute,
            $this->endHour,
            $this->endMinute,
        );
    }

    /**
     * Start time expressed as total minutes since midnight. Useful for sorting.
     */
    public function startInMinutes(): int
    {
        return $this->startHour * 60 + $this->startMinute;
    }
}
