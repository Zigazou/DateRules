<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Formatter;

use Zigazou\DateRules\Rule\DateRangeRule;
use Zigazou\DateRules\Rule\RuleInterface;
use Zigazou\DateRules\Rule\WeekdayGroupRule;
use Zigazou\DateRules\Rule\WeekdayRule;
use Zigazou\DateRules\Rule\SingleDayRule;
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

  private const SHORT_MONTH_NAMES = [
    1  => 'jan.',
    2  => 'fév.',
    3  => 'mars',
    4  => 'avr.',
    5  => 'mai',
    6  => 'juin',
    7  => 'jul.',
    8  => 'aoû.',
    9  => 'sep.',
    10 => 'oct.',
    11 => 'nov.',
    12 => 'déc.',
  ];

  /**
   * {@inheritdoc}
   */
  public function format(RuleSet $ruleSet, bool $outputHtml = FALSE): string {
    $text = implode("\n", array_map(
      fn(RuleInterface $rule) => $this->formatRule($rule),
      $ruleSet->rules,
    ));

    if (!$outputHtml) {
      return $text;
    }

    // Convert plain text to a minimal HTML representation. Paragraphs are
    // created for regular lines and lists for lines starting with "- ".
    $lines = explode("\n", $text);
    $out = [];
    $inList = FALSE;

    foreach ($lines as $line) {
      if (str_starts_with($line, '- ')) {
        if (!$inList) {
          $out[] = '<ul>';
          $inList = TRUE;
        }

        $item = substr($line, 2);
        $out[] = '<li>' . htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
        continue;
      }

      if ($inList) {
        $out[] = '</ul>';
        $inList = FALSE;
      }

      // A line using the weekday-range format ("... : du X au Y de ...") is
      // rendered as an unclosed <p> with the second colon softened to a comma.
      if (preg_match('/ : du .+ au .+ : de /', $line)) {
        $html = preg_replace('/ : de (.+\.)$/', ', de $1', $line);
        $out[] = '<p>' . htmlspecialchars((string) $html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      }
      else {
        $out[] = '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
      }
    }

    if ($inList) {
      $out[] = '</ul>';
    }

    return implode("", $out);
  }

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
      $rule instanceof WeekdayGroupRule => $this->formatWeekdayGroupRule($rule),
      $rule instanceof WeekdayRule      => $this->formatWeekdayRule($rule),
      $rule instanceof DateRangeRule    => $this->formatDateRangeRule($rule),
      $rule instanceof SingleDayRule    => $this->formatSingleDayRule($rule),
      default                           => '',
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
    $except = $this->formatExceptions(
      $rule->exceptions,
      $rule->startDate,
      $rule->endDate
    );

    // Single day: "Vendredi 10 juillet 2026, de 10h à 11h.".
    if ($rule->startDate->format('Y-m-d') === $rule->endDate->format('Y-m-d')) {
      $dayName = ucfirst(
        self::WEEKDAY_NAMES[(int) $rule->startDate->format('N')]
      );

      // All-day slot (00:00–23:59): "Mercredi 15 juillet 2026, toute la
      // journée.".
      if ($this->isAllDay($rule->timeSlots)) {
        return $dayName
          . ' ' . $this->formatFullDate($rule->startDate)
          . ', toute la journée.';
      }

      $result = $dayName
        . ' ' . $this->formatFullDate($rule->startDate)
        . ', de ' . $slots;

      if ($except !== '') {
        $result .= ' (' . $except . ')';
      }

      return $result . '.';
    }

    // Multi-day: "Du 13 avril 2026 au 4 janvier 2027 : lundi, mercredi, ...
    // de 13h30 à 18h15 (sauf ...).".
    $days  = $this->formatWeekdays($rule->weekdays);
    $range = $this->formatDateRangeLabel($rule->startDate, $rule->endDate);

    if ($except !== '') {
      $result = ucfirst($range) . ' : ' . $days . ' de ' . $slots . ' (' . $except . ')';
    }
    else {
      $result = ucfirst($range) . ' : ' . $days . ' de ' . $slots;
    }

    return $result . '.';
  }

  /**
   * Formats a SingleDayRule as a human-readable French string.
   *
   * @param \Zigazou\DateRules\Rule\SingleDayRule $rule
   *   The single day rule to format.
   *
   * @return string
   *   The formatted rule string.
   */
  private function formatSingleDayRule(SingleDayRule $rule): string {
    $slots = $this->formatTimeSlots($rule->timeSlots);

    // Single day: "Vendredi 10 juillet 2026, de 10h à 11h.".
    $dayName = ucfirst(
      self::WEEKDAY_NAMES[(int) $rule->date->format('N')]
    );

    // All-day slot (00:00–23:59): "Mercredi 15 juillet 2026, toute la
    // journée.".
    if ($this->isAllDay($rule->timeSlots)) {
      return $dayName
        . ' ' . $this->formatFullDate($rule->date)
        . ', toute la journée.';
    }

    $result = $dayName
      . ' ' . $this->formatFullDate($rule->date)
      . ', de ' . $slots;

    return $result . '.';
  }

  /**
   * Formats a WeekdayGroupRule as a multi-line French string.
   *
   * @param \Zigazou\DateRules\Rule\WeekdayGroupRule $rule
   *   The grouped weekday rule to format.
   *
   * @return string
   *   The formatted rule string.
   */
  private function formatWeekdayGroupRule(WeekdayGroupRule $rule): string {
    $range = $this->formatDateRangeLabel($rule->startDate, $rule->endDate);
    $lines = [ucfirst($range) . ' :'];
    $count = count($rule->subRules);

    foreach ($rule->subRules as $idx => $subRule) {
      $days   = $this->formatWeekdays($subRule->weekdays);
      $slots  = $this->formatTimeSlots($subRule->timeSlots);
      $except = $this->formatExceptions(
        $subRule->exceptions,
        $subRule->startDate,
        $subRule->endDate
      );

      if ($except !== '') {
        $line = '- ' . $days . ' de ' . $slots . ' (' . $except . ')';
      }
      else {
        $line = '- ' . $days . ' : de ' . $slots;
      }

      $lines[] = $line;
    }

    return implode("\n", $lines);
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
    $except = $this->formatExceptions(
      $rule->exceptions,
      $rule->startDate,
      $rule->endDate
    );

    // "Du 1er au 30 juillet 2026 : tous les jours de 23h à 23h59."
    $result = ucfirst($range) . ' : tous les jours de ' . $slots;

    if ($except !== '') {
      $result .= ' (' . $except . ')';
    }

    return $result . '.';
  }

  /**
   * Formats a list of weekday numbers as a French-language string.
   *
   * When all weekdays form a strictly consecutive sequence of 5 or more days,
   * a compact range "du X au Y" is returned instead of enumerating every name.
   *
   * @param int[] $weekdays
   *   ISO 8601 weekday numbers.
   *
   * @return string
   *   The formatted weekdays string.
   */
  private function formatWeekdays(array $weekdays): string {
    if (count($weekdays) >= 5) {
      $sorted = $weekdays;
      sort($sorted);

      $consecutive = TRUE;
      for ($i = 1, $c = count($sorted); $i < $c; $i++) {
        if ($sorted[$i] !== $sorted[$i - 1] + 1) {
          $consecutive = FALSE;
          break;
        }
      }

      if ($consecutive) {
        return 'du ' . self::WEEKDAY_NAMES[$sorted[0]]
          . ' au ' . self::WEEKDAY_NAMES[$sorted[count($sorted) - 1]];
      }
    }

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

    // "10h à 12h30 et de 13h30 à 18h15" (calling code prepends "de ")
    $last = array_pop($parts);

    return implode(', de ', $parts) . ' et de ' . $last;
  }

  /**
   * Returns TRUE when the time slots represent a full day (00:00–23:59).
   *
   * @param \Zigazou\DateRules\TimeSlot[] $timeSlots
   *   The time slots to check.
   *
   * @return bool
   *   TRUE if there is exactly one slot covering 00:00 to 23:59.
   */
  private function isAllDay(array $timeSlots): bool {
    return count($timeSlots) === 1
      && $timeSlots[0]->startHour === 0
      && $timeSlots[0]->startMinute === 0
      && $timeSlots[0]->endHour === 23
      && $timeSlots[0]->endMinute === 59;
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
    if ($hour === 23 && $minute === 59) {
      return 'minuit';
    }

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
   * @param \DateTimeImmutable $startDate
   *   The start date of the associated events.
   * @param \DateTimeImmutable $endDate
   *   The end date of the associated events.
   *
   * @return string
   *   The formatted exceptions string, or an empty string if none.
   */
  private function formatExceptions(array $exceptions, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): string {
    if (empty($exceptions)) {
      return '';
    }

    if (count($exceptions) > 5) {
      $exceptionsInInterval = array_filter(
        $exceptions,
        fn(\DateTimeImmutable $d) => $d >= $startDate && $d <= $endDate
      );

      $frenchRange = 'du ' . $this->formatFullDate($startDate)
          . ' au ' . $this->formatFullDate($endDate);

      if (!empty($exceptionsInInterval)) {
        $labels = array_map(
          fn(\DateTimeImmutable $d) => 'le ' . $this->formatFullDate($d),
          $exceptionsInInterval,
        );

        $frenchRange .= ' sauf ' . $this->joinFrench($labels);
      }

      return $frenchRange;
    }
    else {
      $labels = array_map(
        fn(\DateTimeImmutable $d) => 'le ' . $this->formatFullDate($d),
        $exceptions,
      );

      return 'sauf ' . $this->joinFrench($labels);
    }
  }

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
   * Formats a short exception date as a French-language string.
   *
   * E.g. "31 juil. 2027".
   *
   * @param \DateTimeImmutable $date
   *   The date to format.
   *
   * @return string
   *   The formatted short exception date string (with year).
   */
  private function formatShortExceptionDate(\DateTimeImmutable $date): string {
    return $this->dayOrdinal($date)
      . ' ' . self::SHORT_MONTH_NAMES[(int) $date->format('n')]
      . ' ' . $date->format('Y');
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
