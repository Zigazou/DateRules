<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Analyzer;

use Zigazou\DateRules\DateEntry;
use Zigazou\DateRules\Rule\DateRangeRule;
use Zigazou\DateRules\Rule\RuleInterface;
use Zigazou\DateRules\Rule\WeekdayRule;
use Zigazou\DateRules\RuleSet;
use Zigazou\DateRules\TimeSlot;

/**
 * Transforms a flat list of date+time intervals into a minimal RuleSet.
 *
 * Detection strategy (per time slot group):
 *
 *  1. If all 7 weekdays are present AND density ≥ DAILY_DENSITY_THRESHOLD →
 *     DateRangeRule covering the whole span, with exceptions for missing days.
 *
 *  2. Otherwise, search for the longest run of strictly consecutive calendar
 *     days. If that run is ≥ MIN_DAILY_RUN_LENGTH (must be > 6 to avoid
 *     confusing a 6-day weekly pattern with a daily range), extract it as a
 *     DateRangeRule and recurse on the remaining dates.
 *
 *  3. Fall back to a WeekdayRule using the distinct days of the week present,
 *     with exceptions for expected occurrences that are absent.
 *
 * After all groups are processed, WeekdayRules that share the same weekday set
 * and date range are merged into a single rule with multiple time slots.
 */
final class Analyzer {
  /**
   * Minimum fraction of days in [first, last] that must be covered.
   *
   * It must be covered before a group is treated as a continuous "every day"
   * range. Requires count(distinctWeekdays) === 7 in addition to this
   * threshold.
   */
  private const DAILY_DENSITY_THRESHOLD = 0.85;

  /**
   * Minimum number of strictly consecutive calendar days required.
   *
   * They are required to extract a sub-sequence as a DateRangeRule.  Must be >
   * 6 so that the longest possible run inside a 6-day-per-week pattern (Wed →
   * Thu → Fri → Sat → Sun = 5 days) is never mistaken for a daily range.
   */
  private const MIN_DAILY_RUN_LENGTH = 7;

  /**
   * Analyzes a list of date entries and returns a minimal RuleSet.
   *
   * @param \Zigazou\DateRules\DateEntry[] $entries
   *   All input entries, sorted by start date.
   */
  public function analyze(array $entries): RuleSet {
    usort(
      $entries,
      static fn(DateEntry $a, DateEntry $b) => $a->start <=> $b->start
    );

    // Group by time slot key.
    $groups = [];
    foreach ($entries as $entry) {
      $groups[$entry->getTimeSlot()->key()][] = $entry;
    }

    $rules = [];
    foreach ($groups as $groupEntries) {
      array_push($rules, ...$this->analyzeGroup($groupEntries));
    }

    $rules = $this->mergeWeekdayRules($rules);

    // Sort rules chronologically by their start date.
    usort($rules, static function (RuleInterface $a, RuleInterface $b): int {
      /** @var \Zigazou\DateRules\Rule\WeekdayRule|DateRangeRule $a */
      /** @var \Zigazou\DateRules\Rule\WeekdayRule|DateRangeRule $b */
      return $a->startDate <=> $b->startDate;
    });

    return new RuleSet($rules);
  }

  // =========================================================================
  // Group analysis
  // =========================================================================

  /**
   * Analyzes a group of entries that share the same time slot.
   *
   * It returns a list of rules that cover all the dates.
   *
   * @param \Zigazou\DateRules\DateEntry[] $entries
   *   All entries sharing the same time slot.
   *
   * @return \Zigazou\DateRules\Rule\RuleInterface[]
   *   List of rules that cover all the dates in $entries.
   */
  private function analyzeGroup(array $entries): array {
    $timeSlot = $entries[0]->getTimeSlot();
    $dates    = $this->extractSortedDates($entries);

    return $this->analyzeDates($dates, $timeSlot);
  }

  /**
   * Core recursive analysis.
   *
   * It decides whether $dates best fit a DateRangeRule, a WeekdayRule, or a mix
   * of both.
   *
   * @param \DateTimeImmutable[] $dates
   *   Sorted, unique calendar dates (midnight)
   * @param \Zigazou\DateRules\TimeSlot $timeSlot
   *   Time slot shared by all dates.
   *
   * @return \Zigazou\DateRules\Rule\RuleInterface[]
   *   List of rules that cover all the dates in $dates.
   */
  private function analyzeDates(array $dates, TimeSlot $timeSlot): array {
    if (empty($dates)) {
      return [];
    }

    $firstDate        = $dates[0];
    $lastDate         = $dates[array_key_last($dates)];
    $span             = (int) $firstDate->diff($lastDate)->days + 1;
    $density          = count($dates) / $span;
    $distinctWeekdays = $this->distinctWeekdays($dates);

    // Step 1 – Direct daily range: all 7 weekdays present and density is high
    // enough. Requiring exactly 7 distinct weekdays ensures that a
    // 6-day-per-week pattern (max density ≈ 85.7 %) never accidentally triggers
    // this branch.
    if (count($distinctWeekdays) === 7 && $density >= self::DAILY_DENSITY_THRESHOLD) {
      $exceptions = $this->findMissingDates($firstDate, $lastDate, $dates);

      return [new DateRangeRule($firstDate, $lastDate, [$timeSlot], $exceptions)];
    }

    // Step 2 – Extract a long consecutive run as a daily sub-range and recurse.
    $longestRun = $this->findLongestConsecutiveRun($dates);

    if ($longestRun !== NULL && count($longestRun) >= self::MIN_DAILY_RUN_LENGTH) {
      $runFirst      = $longestRun[0];
      $runLast       = $longestRun[array_key_last($longestRun)];
      $runExceptions = $this->findMissingDates($runFirst, $runLast, $longestRun);

      $remaining = $this->datesOutsideRange($dates, $runFirst, $runLast);

      return array_merge(
            [new DateRangeRule($runFirst, $runLast, [$timeSlot], $runExceptions)],
            $this->analyzeDates($remaining, $timeSlot),
        );
    }

    // Step 3 – Fall back to a weekly pattern.
    return [$this->buildWeekdayRule($dates, $timeSlot)];
  }

  // =========================================================================
  // Date helpers
  // =========================================================================

  /**
   * @param \Zigazou\DateRules\DateEntry[] $entries
   * @return \DateTimeImmutable[] Sorted unique calendar dates (midnight)
   */
  private function extractSortedDates(array $entries): array {
    $dateMap = [];
    foreach ($entries as $entry) {
      $key           = $entry->getDate()->format('Y-m-d');
      $dateMap[$key] = $entry->getDate();
    }
    ksort($dateMap);

    return array_values($dateMap);
  }

  /**
   * Returns the distinct ISO day-of-week numbers present in $dates.
   *
   * @param \DateTimeImmutable[] $dates
   *
   * @return int[]
   */
  private function distinctWeekdays(array $dates): array {
    $weekdays = [];
    foreach ($dates as $date) {
      $weekdays[(int) $date->format('N')] = TRUE;
    }

    return array_keys($weekdays);
  }

  /**
   * Returns all calendar dates in [$start, $end] that are absent from $actual.
   *
   * @param \DateTimeImmutable[] $actual
   *
   * @return \DateTimeImmutable[]
   */
  private function findMissingDates(
    \DateTimeImmutable $start,
    \DateTimeImmutable $end,
    array $actual,
  ): array {
    $actualSet = array_flip(array_map(
          static fn(\DateTimeImmutable $d) => $d->format('Y-m-d'),
          $actual,
      ));

    $missing = [];
    $current = $start;

    while ($current->format('Y-m-d') <= $end->format('Y-m-d')) {
      if (!isset($actualSet[$current->format('Y-m-d')])) {
        $missing[] = $current;
      }
      $current = $current->modify('+1 day');
    }

    return $missing;
  }

  /**
   * Returns the longest subsequence of consecutive calendar days (gap = 1 day).
   *
   * It returns null when all runs have length 1.
   *
   * @param \DateTimeImmutable[] $dates
   *   Sorted.
   *
   * @return \DateTimeImmutable[]|null
   */
  private function findLongestConsecutiveRun(array $dates): ?array {
    if (count($dates) < 2) {
      return NULL;
    }

    $bestRun    = [$dates[0]];
    $currentRun = [$dates[0]];

    for ($i = 1, $n = count($dates); $i < $n; $i++) {
      $gap = (int) $dates[$i - 1]->diff($dates[$i])->days;

      if ($gap === 1) {
        $currentRun[] = $dates[$i];
      }
      else {
        if (count($currentRun) > count($bestRun)) {
          $bestRun = $currentRun;
        }
        $currentRun = [$dates[$i]];
      }
    }

    if (count($currentRun) > count($bestRun)) {
      $bestRun = $currentRun;
    }

    return count($bestRun) >= 2 ? $bestRun : NULL;
  }

  /**
   * Returns dates in $dates that fall strictly outside $rangeStart, $rangeEnd.
   *
   * @param \DateTimeImmutable[] $dates
   *   Sorted.
   * @param \DateTimeImmutable $rangeStart
   *   Start of the range (inclusive).
   * @param \DateTimeImmutable $rangeEnd
   *   End of the range (inclusive).
   *
   * @return \DateTimeImmutable[]
   */
  private function datesOutsideRange(
    array $dates,
    \DateTimeImmutable $rangeStart,
    \DateTimeImmutable $rangeEnd,
  ): array {
    $start = $rangeStart->format('Y-m-d');
    $end   = $rangeEnd->format('Y-m-d');

    return array_values(array_filter(
          $dates,
          static fn(\DateTimeImmutable $d) =>
                $d->format('Y-m-d') < $start || $d->format('Y-m-d') > $end,
      ));
  }

  // =========================================================================
  // WeekdayRule construction
  // =========================================================================

  /**
   * Builds a WeekdayRule from a set of dates.
   *
   * It does so by detecting which weekdays are consistently present and
   * computing the set of exceptional missing dates.
   *
   * @param \DateTimeImmutable[] $dates
   *   Sorted.
   * @param \Zigazou\DateRules\TimeSlot $timeSlot
   *   Time slot shared by all dates.
   *
   * @return \Zigazou\DateRules\Rule\WeekdayRule
   */
  private function buildWeekdayRule(array $dates, TimeSlot $timeSlot): WeekdayRule {
    $weekdays = $this->distinctWeekdays($dates);
    sort($weekdays);

    $firstDate = $dates[0];
    $lastDate  = $dates[array_key_last($dates)];

    $expectedDates = $this->generateDatesForWeekdays($weekdays, $firstDate, $lastDate);
    $actualSet     = array_flip(array_map(
          static fn(\DateTimeImmutable $d) => $d->format('Y-m-d'),
          $dates,
      ));

    $exceptions = [];
    foreach ($expectedDates as $expected) {
      if (!isset($actualSet[$expected->format('Y-m-d')])) {
        $exceptions[] = $expected;
      }
    }

    return new WeekdayRule($weekdays, [$timeSlot], $firstDate, $lastDate, $exceptions);
  }

  /**
   * Generates all dates in [$start, $end] that fall on any $weekdays.
   *
   * @param int[] $weekdays
   *   ISO 8601 weekday numbers (1 = Mon … 7 = Sun)
   * @param \DateTimeImmutable $start
   *   Start of the range (inclusive).
   * @param \DateTimeImmutable $end
   *   End of the range (inclusive).
   *
   * @return \DateTimeImmutable[]
   *   An array of dates in [$start, $end] that fall on any of the $weekdays.
   */
  private function generateDatesForWeekdays(
    array $weekdays,
    \DateTimeImmutable $start,
    \DateTimeImmutable $end,
  ): array {
    $weekdaySet = array_flip($weekdays);
    $dates      = [];
    $current    = $start;

    while ($current->format('Y-m-d') <= $end->format('Y-m-d')) {
      if (isset($weekdaySet[(int) $current->format('N')])) {
        $dates[] = $current;
      }
      $current = $current->modify('+1 day');
    }

    return $dates;
  }

  // =========================================================================
  // Merging
  // =========================================================================

  /**
   * Merges WeekdayRules that share the same weekday set and date range.
   *
   * It does so by combining their time slots into a single rule. DateRangeRules
   * are left unchanged.
   *
   * @param \Zigazou\DateRules\Rule\RuleInterface[] $rules
   *   List of rules to merge.
   *
   * @return \Zigazou\DateRules\Rule\RuleInterface[]
   *   List of rules after merging.
   */
  private function mergeWeekdayRules(array $rules): array {
    $weekdayGroups = [];
    $otherRules    = [];

    foreach ($rules as $rule) {
      if ($rule instanceof WeekdayRule) {
        $weekdayGroups[$rule->structureKey()][] = $rule;
      }
      else {
        $otherRules[] = $rule;
      }
    }

    $merged = $otherRules;

    foreach ($weekdayGroups as $group) {
      /** @var \Zigazou\DateRules\Rule\WeekdayRule $base */
      $base     = $group[0];
      $allSlots = [];

      foreach ($group as $r) {
        foreach ($r->getTimeSlots() as $slot) {
          $allSlots[$slot->key()] = $slot;
        }
      }

      // Sort time slots by start time.
      uasort(
            $allSlots,
            static fn(TimeSlot $a, TimeSlot $b) =>
                    $a->startInMinutes() <=> $b->startInMinutes(),
        );

      $merged[] = new WeekdayRule(
        $base->weekdays,
        array_values($allSlots),
        $base->startDate,
        $base->endDate,
        $base->exceptions,
      );
    }

    return $merged;
  }

}
