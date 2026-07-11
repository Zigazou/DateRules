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
 *   Du 13 avril 2026 au 4 janvier 2027 : lundi, mercredi, jeudi, vendredi,
 *       samedi et dimanche de 13h30 à 18h15 (sauf le 15 août 2026).
 *
 *   Du 18 avril 2026 au 3 janvier 2027 : samedi et dimanche de 10h à 12h30.
 *
 *   Du 1er au 30 juillet 2026 : tous les jours de 23h à 23h59.
 *
 *   Du 4 au 26 septembre 2026 : vendredi et samedi de 21h30 à 22h30.
 *
 *   Vendredi 10 juillet 2026, de 10h à 11h.
 */
final class FrenchFormatter implements FormatterInterface {
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

  /**
   * {@inheritdoc}
   */
  public function format(RuleSet $ruleSet): string {
    return implode("\n", array_map(
      fn(RuleInterface $rule) => $this->formatRule($rule),
      $ruleSet->rules,
    ));
  }

  // =========================================================================
  // Rule-level formatting
  // =========================================================================

  /**
   * Formats a single rule as a human-readable string.
   *
   * @param \Zigazou\DateRules\Rule\RuleInterface $rule
   *   The rule to format.
   *
   * @return string
   *   The formatted rule string.
   */
  private function formatRule(RuleInterface $rule): string {
    return match (TRUE) {
      $rule instanceof WeekdayRule   => $this->formatWeekdayRule($rule),
      $rule instanceof DateRangeRule => $this->formatDateRangeRule($rule),
      default                        => '',
    };
  }

  /**
   * Formats a WeekdayRule as a human-readable French string.
   *
   * @param \Zigazou\DateRules\Rule\WeekdayRule $rule
   *   The weekday rule to format.
   *
   * @return string
   *   The formatted rule string.
   */
  private function formatWeekdayRule(WeekdayRule $rule): string {
    $slots  = $this->formatTimeSlots($rule->timeSlots);
    $except = $this->formatExceptions($rule->exceptions);

    // Single day: "Vendredi 10 juillet 2026, de 10h à 11h.".
    if ($rule->startDate->format('Y-m-d') === $rule->endDate->format('Y-m-d')) {
      $dayName = ucfirst(self::WEEKDAY_NAMES[(int) $rule->startDate->format('N')]);
      $result  = $dayName . ' ' . $this->formatFullDate($rule->startDate) . ', de ' . $slots;

      if ($except !== '') {
        $result .= ' (' . $except . ')';
      }

      return $result . '.';
    }

    // Multi-day: "Du 13 avril 2026 au 4 janvier 2027 : lundi, mercredi, ...
    // de 13h30 à 18h15 (sauf ...).".
    $days  = $this->formatWeekdays($rule->weekdays);
    $range = $this->formatDateRangeLabel($rule->startDate, $rule->endDate);

    $result = ucfirst($range) . ' : ' . $days . ' de ' . $slots;

    if ($except !== '') {
      $result .= ' (' . $except . ')';
    }

    return $result . '.';
  }

  /**
   * Formats a DateRangeRule as a human-readable French string.
   *
   * @param \Zigazou\DateRules\Rule\DateRangeRule $rule
   *   The date range rule to format.
   *
   * @return string
   *   The formatted rule string.
   */
  private function formatDateRangeRule(DateRangeRule $rule): string {
    $range  = $this->formatDateRangeLabel($rule->startDate, $rule->endDate);
    $slots  = $this->formatTimeSlots($rule->timeSlots);
    $except = $this->formatExceptions($rule->exceptions);

    // "Du 1er au 30 juillet 2026 : tous les jours de 23h à 23h59."
    $result = ucfirst($range) . ' : tous les jours de ' . $slots;

    if ($except !== '') {
      $result .= ' (' . $except . ')';
    }

    return $result . '.';
  }

  // =========================================================================
  // Component formatters
  // =========================================================================

  /**
   * Formats a list of weekday numbers as a French-language string.
   *
   * @param int[] $weekdays
   *   ISO 8601 weekday numbers.
   *
   * @return string
   *   The formatted weekdays string.
   */
  private function formatWeekdays(array $weekdays): string {
    $names = array_map(
      static fn(int $d) => self::WEEKDAY_NAMES[$d],
      $weekdays,
    );

    return $this->joinFrench($names);
  }

  /**
   * Formats a list of time slots as a French-language string.
   *
   * @param \Zigazou\DateRules\TimeSlot[] $slots
   *   The time slots to format.
   *
   * @return string
   *   The formatted time slots string.
   */
  private function formatTimeSlots(array $slots): string {
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

  /**
   * Formats an hour and minute as a French-language time string.
   *
   * @param int $hour
   *   The hour.
   * @param int $minute
   *   The minute.
   *
   * @return string
   *   The formatted time string (e.g. "13h30" or "10h").
   */
  private function formatTime(int $hour, int $minute): string {
    return $minute === 0
      ? $hour . 'h'
      : $hour . 'h' . sprintf('%02d', $minute);
  }

  /**
   * Produces a "du X au Y" date range label.
   *
   * It compresses common sub-parts:
   *  - same month+year  -> "du 1er au 30 juillet 2026"
   *  - same year        -> "du 31 juillet au 14 aout 2026"
   *  - different years  -> "du 13 avril 2026 au 4 janvier 2027"
   *  - single day       -> "le 15 aout 2026"
   *
   * @param \DateTimeImmutable $start
   *   The start date.
   * @param \DateTimeImmutable $end
   *   The end date.
   *
   * @return string
   *   The formatted date range label.
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
   * Formats a list of exception dates as a French-language string.
   *
   * @param \DateTimeImmutable[] $exceptions
   *   The exception dates to format.
   *
   * @return string
   *   The formatted exceptions string, or an empty string if none.
   */
  private function formatExceptions(array $exceptions): string {
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

  /**
   * Formats a full date as a French-language string (e.g. "4 janvier 2027").
   *
   * @param \DateTimeImmutable $date
   *   The date to format.
   *
   * @return string
   *   The formatted full date string.
   */
  private function formatFullDate(\DateTimeImmutable $date): string {
    return $this->dayOrdinal($date)
      . ' ' . self::MONTH_NAMES[(int) $date->format('n')]
      . ' ' . $date->format('Y');
  }

  /**
   * Formats a short date as a French-language string (e.g. "31 juillet").
   *
   * @param \DateTimeImmutable $date
   *   The date to format.
   *
   * @return string
   *   The formatted short date string (no year).
   */
  private function formatShortDate(\DateTimeImmutable $date): string {
    return $this->dayOrdinal($date)
      . ' ' . self::MONTH_NAMES[(int) $date->format('n')];
  }

  /**
   * Returns the French ordinal day string (e.g. "1er", "2", "3").
   *
   * @param \DateTimeImmutable $date
   *   The date.
   *
   * @return string
   *   "1er" for the 1st, the day number as a string otherwise.
   */
  private function dayOrdinal(\DateTimeImmutable $date): string {
    $day = (int) $date->format('j');

    return $day === 1 ? '1er' : (string) $day;
  }

  // =========================================================================
  // French list joining
  // =========================================================================

  /**
   * Joins a list of strings with ", " and "et" before the last item.
   *
   * Examples:
   * - ["a"]           -> "a"
   * - ["a", "b"]      -> "a et b"
   * - ["a", "b", "c"] -> "a, b et c"
   *
   * @param string[] $items
   *   The items to join.
   *
   * @return string
   *   The joined string.
   */
  private function joinFrench(array $items): string {
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
