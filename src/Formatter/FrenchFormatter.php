<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Formatter;

use Zigazou\DateRules\Rule\DateRangeRule;
use Zigazou\DateRules\Rule\RuleInterface;
use Zigazou\DateRules\Rule\WeekdayRule;
use Zigazou\DateRules\RuleSet;
use Zigazou\DateRules\TimeSlot;

/**
 * Formats a RuleSet as French human-readable text.
 *
 * Example outputs:
 *   Lundi, mercredi, jeudi, vendredi, samedi et dimanche de 13h30 à 18h15,
 *       du 13 avril 2026 au 4 janvier 2027 (sauf le 15 août 2026)
 *
 *   Samedi et dimanche de 10h à 12h30, du 18 avril 2026 au 4 janvier 2027
 *
 *   Du 1er au 30 juillet 2026 de 23h à 23h59
 *
 *   Vendredi et samedi de 21h30 à 22h30, du 4 au 26 septembre 2026
 */
final class FrenchFormatter implements FormatterInterface
{
    private const WEEKDAY_NAMES = [
        1 => 'lundi',
        2 => 'mardi',
        3 => 'mercredi',
        4 => 'jeudi',
        5 => 'vendredi',
        6 => 'samedi',
        7 => 'dimanche',
    ];

    private const MONTH_NAMES = [
        1  => 'janvier',
        2  => 'février',
        3  => 'mars',
        4  => 'avril',
        5  => 'mai',
        6  => 'juin',
        7  => 'juillet',
        8  => 'août',
        9  => 'septembre',
        10 => 'octobre',
        11 => 'novembre',
        12 => 'décembre',
    ];

    public function format(RuleSet $ruleSet): string
    {
        return implode("\n", array_map(
            fn(RuleInterface $rule) => $this->formatRule($rule),
            $ruleSet->rules,
        ));
    }

    // =========================================================================
    // Rule-level formatting
    // =========================================================================

    private function formatRule(RuleInterface $rule): string
    {
        return match (true) {
            $rule instanceof WeekdayRule   => $this->formatWeekdayRule($rule),
            $rule instanceof DateRangeRule => $this->formatDateRangeRule($rule),
            default                        => '',
        };
    }

    private function formatWeekdayRule(WeekdayRule $rule): string
    {
        $days   = $this->formatWeekdays($rule->weekdays);
        $slots  = $this->formatTimeSlots($rule->timeSlots);
        $range  = $this->formatDateRangeLabel($rule->startDate, $rule->endDate);
        $except = $this->formatExceptions($rule->exceptions);

        // "Lundi, mercredi et vendredi de 13h30 à 18h15, du 1er avril au 30 juin 2026"
        $result = ucfirst($days) . ' de ' . $slots;

        if ($range !== '') {
            $result .= ', ' . $range;
        }

        if ($except !== '') {
            $result .= ' (' . $except . ')';
        }

        return $result;
    }

    private function formatDateRangeRule(DateRangeRule $rule): string
    {
        $range  = $this->formatDateRangeLabel($rule->startDate, $rule->endDate);
        $slots  = $this->formatTimeSlots($rule->timeSlots);
        $except = $this->formatExceptions($rule->exceptions);

        // "Du 1er au 30 juillet 2026 de 23h à 23h59"
        $result = ucfirst($range) . ' de ' . $slots;

        if ($except !== '') {
            $result .= ' (' . $except . ')';
        }

        return $result;
    }

    // =========================================================================
    // Component formatters
    // =========================================================================

    /**
     * @param int[] $weekdays ISO 8601 weekday numbers
     */
    private function formatWeekdays(array $weekdays): string
    {
        $names = array_map(
            static fn(int $d) => self::WEEKDAY_NAMES[$d],
            $weekdays,
        );

        return $this->joinFrench($names);
    }

    /**
     * @param TimeSlot[] $slots
     */
    private function formatTimeSlots(array $slots): string
    {
        $parts = array_map(
            fn(TimeSlot $s) =>
                $this->formatTime($s->startHour, $s->startMinute)
                . ' à '
                . $this->formatTime($s->endHour, $s->endMinute),
            $slots,
        );

        if (count($parts) === 1) {
            return $parts[0];
        }

        // "de 10h à 12h30 et de 13h30 à 18h15"
        $last = array_pop($parts);

        return 'de ' . implode(', de ', $parts) . ' et de ' . $last;
    }

    private function formatTime(int $hour, int $minute): string
    {
        return $minute === 0
            ? $hour . 'h'
            : $hour . 'h' . sprintf('%02d', $minute);
    }

    /**
     * Produces a "du X au Y" date range label, compressing common sub-parts:
     *  – same month+year  → "du 1er au 30 juillet 2026"
     *  – same year        → "du 31 juillet au 14 août 2026"
     *  – different years  → "du 13 avril 2026 au 4 janvier 2027"
     *  – single day       → "le 15 août 2026"
     */
    private function formatDateRangeLabel(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): string {
        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            return 'le ' . $this->formatFullDate($start);
        }

        $startYear  = $start->format('Y');
        $endYear    = $end->format('Y');
        $startMonth = (int) $start->format('n');
        $endMonth   = (int) $end->format('n');

        if ($startYear === $endYear && $startMonth === $endMonth) {
            // "du 1er au 30 juillet 2026"
            return 'du ' . $this->dayOrdinal($start)
                . ' au ' . $this->dayOrdinal($end)
                . ' ' . self::MONTH_NAMES[$startMonth]
                . ' ' . $startYear;
        }

        if ($startYear === $endYear) {
            // "du 31 juillet au 14 août 2026"
            return 'du ' . $this->formatShortDate($start)
                . ' au ' . $this->formatFullDate($end);
        }

        // "du 13 avril 2026 au 4 janvier 2027"
        return 'du ' . $this->formatFullDate($start)
            . ' au ' . $this->formatFullDate($end);
    }

    /**
     * @param \DateTimeImmutable[] $exceptions
     */
    private function formatExceptions(array $exceptions): string
    {
        if (empty($exceptions)) {
            return '';
        }

        $labels = array_map(
            fn(\DateTimeImmutable $d) => 'le ' . $this->formatFullDate($d),
            $exceptions,
        );

        return 'sauf ' . $this->joinFrench($labels);
    }

    // =========================================================================
    // Date helpers
    // =========================================================================

    /** "4 janvier 2027" */
    private function formatFullDate(\DateTimeImmutable $date): string
    {
        return $this->dayOrdinal($date)
            . ' ' . self::MONTH_NAMES[(int) $date->format('n')]
            . ' ' . $date->format('Y');
    }

    /** "31 juillet"  (no year) */
    private function formatShortDate(\DateTimeImmutable $date): string
    {
        return $this->dayOrdinal($date)
            . ' ' . self::MONTH_NAMES[(int) $date->format('n')];
    }

    /** "1er" for the 1st, "2", "3", … otherwise */
    private function dayOrdinal(\DateTimeImmutable $date): string
    {
        $day = (int) $date->format('j');

        return $day === 1 ? '1er' : (string) $day;
    }

    // =========================================================================
    // French list joining
    // =========================================================================

    /**
     * Joins a list of strings with ", " and "et" before the last item.
     * ["a"]           → "a"
     * ["a", "b"]      → "a et b"
     * ["a", "b", "c"] → "a, b et c"
     *
     * @param string[] $items
     */
    private function joinFrench(array $items): string
    {
        if (count($items) === 0) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' et ' . $last;
    }
}
