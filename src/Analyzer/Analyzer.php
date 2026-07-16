<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Analyzer;

use Zigazou\DateRules\DateEntry;
use Zigazou\DateRules\Rule\DateRangeRule;
use Zigazou\DateRules\Rule\RuleInterface;
use Zigazou\DateRules\Rule\WeekdayGroupRule;
use Zigazou\DateRules\Rule\WeekdayRule;
use Zigazou\DateRules\Rule\SingleDayRule;
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
   * ISO 8601 weekday format character for DateTime::format().
   */
  private const ISO8601_WEEKDAY = 'N';

  /**
   * Format string for 24-hour time with minutes (e.g. 23:59).
   */
  private const HOUR24_MINUTE = 'H:i';

  /**
   * Format string for minutes (e.g. 59).
   */
  private const MINUTE = 'i';

  /**
   * Format string for 24-hour time (e.g. 23).
   */
  private const HOUR24 = 'H';

  /**
   * Format string for year-month-day (e.g. 2024-06-01).
   */
  private const YEAR_MONTH_DAY = 'Y-m-d';

  /**
   * Last minute of the day (23:59).
   *
   * This is used to detect "stitched" entries that chain across midnight
   * boundaries.
   */
  private const LAST_MINUTE_OF_DAY = '23:59';

  /**
   * First minute of the day (00:00).
   *
   * This is used to detect "stitched" entries that chain across midnight
   * boundaries.
   */
  private const FIRST_MINUTE_OF_DAY = '00:00';

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
   * Maximum gap in calendar days allowed between consecutive dates.
   *
   * Gaps larger than this indicate two distinct date clusters that should be
   * analysed as separate rules. A value of 14 lets fortnightly (every-other-
   * week) patterns pass through unaltered while still splitting the kind of
   * multi-month seasonal breaks seen in practice.
   */
  private const MAX_WEEKDAY_GAP_DAYS = 14;

  /**
   * Maximum difference in days when grouping weekday rules.
   *
   * If the difference in start dates of two weekday rules is larger than this
   * value, they will not be grouped together.
   */
  private const MAX_WEEKDAY_GROUPING_DAYS = 21;

  /**
   * Analyzes a list of date entries and returns a minimal RuleSet.
   *
   * @param \Zigazou\DateRules\DateEntry[] $entries
   *   All input entries, sorted by start date.
   *
   * @return \Zigazou\DateRules\RuleSet
   *   The minimal rule set covering all input entries.
   */
  public function analyze(array $entries): RuleSet {
    usort(
      $entries,
      static fn(DateEntry $a, DateEntry $b) => $a->start <=> $b->start
    );

    $entries = $this->normalizeStitchedEntries($entries);

    // Group by time slot key.
    $groups = [];
    foreach ($entries as $entry) {
      $groups[$entry->getTimeSlot()->key()][] = $entry;
    }

    // Analyze each group and merge the resulting rules.
    $rules = [];
    foreach ($groups as $groupEntries) {
      array_push($rules, ...$this->analyzeGroup($groupEntries));
    }

    $rules = $this->groupSingleDayRules($rules);
    $rules = $this->mergeWeekdayRules($rules);
    $rules = $this->groupCompatibleWeekdayRules($rules);

    // Sort rules chronologically by their start date.
    usort($rules, static function (RuleInterface $a, RuleInterface $b): int {
      /** @var \Zigazou\DateRules\Rule\WeekdayRule|\Zigazou\DateRules\Rule\DateRangeRule $a */
      /** @var \Zigazou\DateRules\Rule\WeekdayRule|\Zigazou\DateRules\Rule\DateRangeRule $b */
      return $a->startDate <=> $b->startDate;
    });

    return new RuleSet($rules);
  }

  /**
   * Detects and normalises "stitched" entries.
   *
   * When the user mistakenly chains daily intervals across midnight boundaries
   * (entry[i] ends at 23:59, entry[i+1] starts at 00:00 the next day), the
   * chain is collapsed into per-day entries all sharing the first entry's start
   * time and the last entry's end time.
   *
   * A chain is only normalised when:
   *  - It contains at least two entries.
   *  - The last entry does NOT end at 23:59 (a clear closing time exists).
   *  - The implied daily slot is strictly forward (T1 < T2 in minutes).
   *
   * @param \Zigazou\DateRules\DateEntry[] $entries
   *   Entries sorted by start datetime.
   *
   * @return \Zigazou\DateRules\DateEntry[]
   *   Entries with stitched chains replaced by uniform per-day entries.
   */
  private function normalizeStitchedEntries(array $entries): array {
    $result = [];
    $i      = 0;
    $n      = count($entries);

    while ($i < $n) {
      $chain = [$entries[$i]];
      $j     = $i + 1;

      while ($j < $n) {
        $prev = $chain[count($chain) - 1];
        $next = $entries[$j];

        if (
          $prev->end->format(self::HOUR24_MINUTE)
            === self::LAST_MINUTE_OF_DAY &&
          $next->start->format(self::HOUR24_MINUTE)
            === self::FIRST_MINUTE_OF_DAY &&
          $next->start->format(self::YEAR_MONTH_DAY)
            === $prev->end->modify('+1 day')->format(self::YEAR_MONTH_DAY)
        ) {
          $chain[] = $next;
          $j++;
        }
        else {
          break;
        }
      }

      $lastInChain = $chain[count($chain) - 1];

      if (
        count($chain) >= 2 &&
        $lastInChain->end->format(self::HOUR24_MINUTE) !== self::LAST_MINUTE_OF_DAY
      ) {
        $startH = (int) $chain[0]->start->format(self::HOUR24);
        $startM = (int) $chain[0]->start->format(self::MINUTE);
        $endH   = (int) $lastInChain->end->format(self::HOUR24);
        $endM   = (int) $lastInChain->end->format(self::MINUTE);

        if ($startH * 60 + $startM < $endH * 60 + $endM) {
          $current  = $chain[0]->getDate();
          $lastDate = $lastInChain->getDate();

          while (
            $current->format(self::YEAR_MONTH_DAY)
              <= $lastDate->format(self::YEAR_MONTH_DAY)
          ) {
            $result[] = new DateEntry(
              $current->setTime($startH, $startM),
              $current->setTime($endH, $endM),
            );
            $current = $current->modify('+1 day');
          }

          $i = $j;
          continue;
        }
      }

      $result[] = $entries[$i];
      $i++;
    }

    return $result;
  }

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
   *   Sorted, unique calendar dates (midnight).
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

    // Step 0 – Every day in the span is present: unambiguous daily range.
    // Requires span ≥ 2 so that single-day entries keep their weekday format.
    if ($span >= 2 && count($dates) === $span) {
      return [new DateRangeRule($firstDate, $lastDate, [$timeSlot], [])];
    }

    // Step 1 – Direct daily range: all 7 weekdays present and density is high
    // enough. Requiring exactly 7 distinct weekdays ensures that a
    // 6-day-per-week pattern (max density ≈ 85.7 %) never accidentally triggers
    // this branch.
    if (
      count($distinctWeekdays) === 7 &&
      $density >= self::DAILY_DENSITY_THRESHOLD
    ) {
      $exceptions = $this->findMissingDates($firstDate, $lastDate, $dates);

      // If there is a long consecutive run of missing dates (e.g. a whole
      // summer period where this time slot doesn't apply), treat the series
      // as not a single continuous daily range and fall through to the
      // consecutive-run extraction. This avoids collapsing distant blocks
      // separated by a large gap into one DateRangeRule with many exceptions.
      $longMissingRun = $this->findLongestConsecutiveRun($exceptions);
      if (
        $longMissingRun !== NULL &&
        count($longMissingRun) >= self::MIN_DAILY_RUN_LENGTH
      ) {
        // Do not treat as a direct daily range; continue to next steps.
      }
      else {
        return [
          new DateRangeRule($firstDate, $lastDate, [$timeSlot], $exceptions),
        ];
      }
    }

    // Step 2 – Extract a long consecutive run as a daily sub-range and recurse.
    $longestRun = $this->findLongestConsecutiveRun($dates);

    if (
      $longestRun !== NULL &&
      count($longestRun) >= self::MIN_DAILY_RUN_LENGTH
    ) {
      $runFirst      = $longestRun[0];
      $runLast       = $longestRun[array_key_last($longestRun)];
      $runExceptions = $this->findMissingDates($runFirst, $runLast, $longestRun);

      $remaining = $this->datesOutsideRange($dates, $runFirst, $runLast);

      return array_merge(
        [new DateRangeRule($runFirst, $runLast, [$timeSlot], $runExceptions)],
        $this->analyzeDates($remaining, $timeSlot),
      );
    }

    // Step 2.5 – Split on a large temporal gap and recurse on each cluster.
    $splitIdx = $this->findSplitIndexOnLargeGap(
      $dates,
      self::MAX_WEEKDAY_GAP_DAYS
    );

    if ($splitIdx !== NULL) {
      $before = array_slice($dates, 0, $splitIdx);
      $after  = array_slice($dates, $splitIdx);

      return array_merge(
        $this->analyzeDates($before, $timeSlot),
        $this->analyzeDates($after, $timeSlot),
      );
    }

    $weekdayRules = $this->buildWeekdayRules($dates, $timeSlot);

    // Test if there are exceptions in the weekday rules. If there are no
    // exceptions, we can return the weekday rules directly.
    $exceptionsCount = 0;
    foreach ($weekdayRules as $rule) {
      $exceptions = $rule->getExceptions();
      $exceptionsCount += count($rule->getExceptions());
    }

    if ($exceptionsCount === 0 && count($dates) > 2) {
      return $weekdayRules;
    }

    $exceptionsRatio = $exceptionsCount / count($dates);
    if (count($dates) <= 3 || $exceptionsRatio > 0.5) {
      // Step 2.75 – Small clusters of dates (≤ 4) are treated as individual
      // DateRangeRules, even if they are not strictly consecutive. This avoids
      // generating a WeekdayRule with many exceptions for a small number of
      // dates.
      $rules = [];
      foreach ($dates as $date) {
        $rules[] = new SingleDayRule($date, [$timeSlot]);
      }

      return $rules;
    }

    // Step 3 – Fall back to a weekly pattern.
    return $weekdayRules;
  }

  /**
   * Groups SingleDayRule in an array.
   *
   * It does so by combining time slots for rules with the same date.
   *
   * @param \Zigazou\DateRules\RuleInterface[] $rules
   *   The array of rules to merge.
   *
   * @return \Zigazou\DateRules\RuleInterface[]
   *   The merged array of rules.
   */
  private function groupSingleDayRules(array $rules): array {
    $merged = [];

    foreach ($rules as $rule) {
      if ($rule instanceof SingleDayRule) {
        $key = $rule->structureKey();

        if (!isset($merged[$key])) {
          $merged[$key] = new SingleDayRule($rule->date, $rule->timeSlots);
        }
        else {
          $merged[$key] = new SingleDayRule($rule->date, array_merge(
            $merged[$key]->timeSlots,
            $rule->timeSlots
          ));
        }
      }
      else {
        $merged[] = $rule;
      }
    }

    return array_values($merged);
  }

  /**
   * Extracts sorted unique calendar dates from a list of entries.
   *
   * @param \Zigazou\DateRules\DateEntry[] $entries
   *   The date entries to extract dates from.
   *
   * @return \DateTimeImmutable[]
   *   Sorted unique calendar dates (midnight).
   */
  private function extractSortedDates(array $entries): array {
    $dateMap = [];
    foreach ($entries as $entry) {
      $key           = $entry->getDate()->format(self::YEAR_MONTH_DAY);
      $dateMap[$key] = $entry->getDate();
    }
    ksort($dateMap);

    return array_values($dateMap);
  }

  /**
   * Returns the distinct ISO day-of-week numbers present in $dates.
   *
   * @param \DateTimeImmutable[] $dates
   *   The dates to analyze.
   *
   * @return int[]
   *   The distinct ISO 8601 day-of-week numbers present.
   */
  private function distinctWeekdays(array $dates): array {
    $weekdays = [];
    foreach ($dates as $date) {
      $weekdays[(int) $date->format(self::ISO8601_WEEKDAY)] = TRUE;

      if (count($weekdays) === 7) {
        break;
      }
    }

    return array_keys($weekdays);
  }

  /**
   * Returns all calendar dates in [$start, $end] that are absent from $actual.
   *
   * @param \DateTimeImmutable $start
   *   The start of the range (inclusive).
   * @param \DateTimeImmutable $end
   *   The end of the range (inclusive).
   * @param \DateTimeImmutable[] $actual
   *   The actual dates present in the range.
   *
   * @return \DateTimeImmutable[]
   *   The missing calendar dates.
   */
  private function findMissingDates(
    \DateTimeImmutable $start,
    \DateTimeImmutable $end,
    array $actual,
  ): array {
    $actualSet = array_flip(array_map(
      static fn(\DateTimeImmutable $d) => $d->format(self::YEAR_MONTH_DAY),
      $actual,
    ));

    $missing = [];
    $current = $start;

    while (
      $current->format(self::YEAR_MONTH_DAY)
        <= $end->format(self::YEAR_MONTH_DAY)
    ) {
      if (!isset($actualSet[$current->format(self::YEAR_MONTH_DAY)])) {
        $missing[] = $current;
      }
      $current = $current->modify('+1 day');
    }

    return $missing;
  }

  /**
   * Finds the index of the first date after the largest gap exceeding $minGap.
   *
   * Returns null when no gap exceeds the threshold.
   *
   * @param \DateTimeImmutable[] $dates
   *   Sorted date array.
   * @param int $minGap
   *   Minimum gap size (in calendar days) to trigger a split.
   *
   * @return int|null
   *   The index of $dates[$i] where the gap from $dates[$i-1] is largest and
   *   exceeds $minGap, or null if no such gap exists.
   */
  private function findSplitIndexOnLargeGap(array $dates, int $minGap): ?int {
    $maxGap = 0;
    $maxIdx = NULL;

    for ($i = 1, $n = count($dates); $i < $n; $i++) {
      $gap = (int) $dates[$i - 1]->diff($dates[$i])->days;

      if ($gap > $minGap && $gap > $maxGap) {
        $maxGap = $gap;
        $maxIdx = $i;
      }
    }

    return $maxIdx;
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
   *   The longest consecutive run, or null if all runs have length 1.
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
   *   The dates outside the specified range.
   */
  private function datesOutsideRange(
    array $dates,
    \DateTimeImmutable $rangeStart,
    \DateTimeImmutable $rangeEnd,
  ): array {
    $start = $rangeStart->format(self::YEAR_MONTH_DAY);
    $end   = $rangeEnd->format(self::YEAR_MONTH_DAY);

    return array_values(
      array_filter(
        $dates,
        static fn(\DateTimeImmutable $d) =>
          $d->format(self::YEAR_MONTH_DAY) < $start ||
          $d->format(self::YEAR_MONTH_DAY) > $end,
      )
    );
  }

  /**
   * Builds an array of WeekdayRule from a set of dates.
   *
   * It does so by detecting which weekdays are consistently present and
   * computing the set of exceptional missing dates.
   *
   * @param \DateTimeImmutable[] $dates
   *   Sorted.
   * @param \Zigazou\DateRules\TimeSlot $timeSlot
   *   Time slot shared by all dates.
   *
   * @return \Zigazou\DateRules\Rule\WeekdayRule[]
   *   The constructed WeekdayRule.
   */
  private function buildWeekdayRules(array $dates, TimeSlot $timeSlot): array {
    $weekdays = $this->distinctWeekdays($dates);
    sort($weekdays);

    // Group entries by their weekday.
    $weekdayGroups = [];
    foreach ($dates as $date) {
      $weekdayGroups[(int) $date->format(self::ISO8601_WEEKDAY)][] = $date;
    }

    ksort($weekdayGroups);

    // Determine start date and end date for each weekday group.
    $weekdayRanges = [];
    foreach ($weekdayGroups as $weekday => $groupEntries) {
      $startDay = reset($groupEntries);
      $endDay   = end($groupEntries);

      $weekdayRanges[$weekday] = ['start' => $startDay, 'end' => $endDay];
    }

    // According to the weekday ranges, group the weekdays so that each group
    // has the same start and end date with a maximum gap of
    // MAX_WEEKDAY_GAP_DAYS between them. Each group will become a WeekdayRule.
    $weekdayGroups = [];
    foreach ($weekdayRanges as $weekday => $range) {
      $added = FALSE;
      foreach ($weekdayGroups as &$group) {
        $groupStart = $group['start'];
        $groupEnd   = $group['end'];

        if (
          abs((int) $groupStart->diff($range['start'])->days)
            <= self::MAX_WEEKDAY_GROUPING_DAYS &&
          abs((int) $groupEnd->diff($range['end'])->days)
            <= self::MAX_WEEKDAY_GROUPING_DAYS
        ) {
          $group['weekdays'][] = $weekday;
          $group['start']      = min($groupStart, $range['start']);
          $group['end']        = max($groupEnd, $range['end']);
          $added               = TRUE;
          break;
        }
      }

      if (!$added) {
        $weekdayGroups[] = [
          'weekdays' => [$weekday],
          'start'    => $range['start'],
          'end'      => $range['end'],
        ];
      }
    }

    $rules = [];

    foreach ($weekdayGroups as $group) {
      $expectedDates = $this->generateDatesForWeekdays(
        $group['weekdays'],
        $group['start'],
        $group['end']
      );

      $actualSet = array_flip(array_map(
        static fn(\DateTimeImmutable $d) => $d->format(self::YEAR_MONTH_DAY),
        $dates
      ));

      $exceptions = [];
      foreach ($expectedDates as $expected) {
        if (!isset($actualSet[$expected->format(self::YEAR_MONTH_DAY)])) {
          $exceptions[] = $expected;
        }
      }

      $rules[] = new WeekdayRule(
        $group['weekdays'],
        [$timeSlot],
        $group['start'],
        $group['end'],
        $exceptions
       );
    }

    return $rules;
  }

  /**
   * Generates all dates in [$start, $end] that fall on any $weekdays.
   *
   * @param int[] $weekdays
   *   ISO 8601 weekday numbers (1 = Mon ... 7 = Sun).
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

    while (
      $current->format(self::YEAR_MONTH_DAY)
        <= $end->format(self::YEAR_MONTH_DAY)
    ) {
      if (isset($weekdaySet[(int) $current->format(self::ISO8601_WEEKDAY)])) {
        $dates[] = $current;
      }
      $current = $current->modify('+1 day');
    }

    return $dates;
  }

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

    // Group WeekdayRules by their structure key (weekdays + date range).
    foreach ($rules as $rule) {
      if ($rule instanceof WeekdayRule) {
        $weekdayGroups[$rule->structureKey()][] = $rule;
      }
      else {
        $otherRules[] = $rule;
      }
    }

    $merged = $otherRules;

    // Merge each group of WeekdayRules into a single rule with combined time
    // slots.
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

  /**
   * Groups WeekdayRules by two complementary strategies.
   *
   * Phase 1 – Subset relationship: when rule B's weekdays are a proper subset
   * of rule A's weekdays AND B's date range is contained within A's date range,
   * the two rules are combined into a WeekdayGroupRule:
   *  - An outer sub-rule covers A's weekdays minus B's weekdays with A's slots.
   *  - An inner sub-rule covers B's weekdays with combined slots from A and B.
   * Exceptions are distributed by weekday.
   *
   * Phase 2 – Disjoint weekday sets: when two rules have completely disjoint
   * weekday sets AND their date ranges overlap, they are combined into a single
   * WeekdayGroupRule spanning the union of both date ranges.
   *
   * @param \Zigazou\DateRules\Rule\RuleInterface[] $rules
   *   List of rules to process.
   *
   * @return \Zigazou\DateRules\Rule\RuleInterface[]
   *   List of rules after grouping.
   */
  private function groupCompatibleWeekdayRules(array $rules): array {
    $weekdayRules = [];
    $otherRules   = [];

    foreach ($rules as $rule) {
      if ($rule instanceof WeekdayRule) {
        $weekdayRules[] = $rule;
      }
      else {
        $otherRules[] = $rule;
      }
    }

    $n    = count($weekdayRules);
    $used = array_fill(0, $n, FALSE);

    $phase1Result  = [];
    $standaloneIdx = [];

    // Phase 1: subset relationship.
    for ($i = 0; $i < $n; $i++) {
      if ($used[$i]) {
        continue;
      }

      $ruleA     = $weekdayRules[$i];
      $setA      = array_flip($ruleA->weekdays);
      $subRuleBs = [];
      $usedJ     = [];

      for ($j = 0; $j < $n; $j++) {
        if ($i === $j || $used[$j]) {
          continue;
        }

        $ruleB = $weekdayRules[$j];

        // B must have fewer weekdays than A (proper subset).
        if (count($ruleB->weekdays) >= count($ruleA->weekdays)) {
          continue;
        }

        // Every weekday in B must also be in A.
        $bSubsetOfA = TRUE;
        foreach ($ruleB->weekdays as $day) {
          if (!isset($setA[$day])) {
            $bSubsetOfA = FALSE;
            break;
          }
        }
        if (!$bSubsetOfA) {
          continue;
        }

        // B's date range must be contained within A's date range.
        if (
          $ruleB->startDate->format(self::YEAR_MONTH_DAY)
            < $ruleA->startDate->format(self::YEAR_MONTH_DAY) ||
          $ruleB->endDate->format(self::YEAR_MONTH_DAY)
            > $ruleA->endDate->format(self::YEAR_MONTH_DAY)
        ) {
          continue;
        }

        $subRuleBs[] = $ruleB;
        $usedJ[]     = $j;
      }

      // Mark this rule (and any paired rules) as consumed.
      $used[$i] = TRUE;
      foreach ($usedJ as $j) {
        $used[$j] = TRUE;
      }

      if (empty($subRuleBs)) {
        // No subset found: defer to Phase 2.
        $standaloneIdx[] = $i;
        continue;
      }

      // Collect the union of all inner (B) weekdays.
      $innerWeekdaySet = [];
      foreach ($subRuleBs as $ruleB) {
        foreach ($ruleB->weekdays as $day) {
          $innerWeekdaySet[$day] = TRUE;
        }
      }

      // Outer weekdays: A minus all inner weekdays.
      $outerWeekdays = array_values(array_filter(
        $ruleA->weekdays,
        static fn(int $d) => !isset($innerWeekdaySet[$d]),
      ));

      $groupSubRules = [];

      // Sub-rule for outer weekdays (only A's slots, exceptions filtered).
      if (!empty($outerWeekdays)) {
        $outerExceptions = array_values(array_filter(
          $ruleA->exceptions,
          static fn(\DateTimeImmutable $d) => in_array(
            (int) $d->format(self::ISO8601_WEEKDAY),
            $outerWeekdays,
            TRUE
          ),
        ));

        $groupSubRules[] = new WeekdayRule(
          $outerWeekdays,
          $ruleA->timeSlots,
          $ruleA->startDate,
          $ruleA->endDate,
          $outerExceptions,
        );
      }

      // Sub-rules for inner weekdays (combined slots, exceptions filtered).
      foreach ($subRuleBs as $ruleB) {
        $innerExceptions = array_values(array_filter(
          $ruleA->exceptions,
          static fn(\DateTimeImmutable $d) => in_array(
            (int) $d->format(self::ISO8601_WEEKDAY),
            $ruleB->weekdays,
            TRUE
          ),
        ));

        $allSlots = [];
        foreach (array_merge($ruleA->timeSlots, $ruleB->timeSlots) as $slot) {
          $allSlots[$slot->key()] = $slot;
        }
        uasort(
          $allSlots,
          static fn(TimeSlot $a, TimeSlot $b) =>
            $a->startInMinutes() <=> $b->startInMinutes(),
        );

        if (count($innerExceptions) > 5) {
          // Separate each inner weekday into its own sub-rule to avoid a long
          // list of exceptions.
          foreach ($ruleB->weekdays as $day) {
            $dayExceptions = array_values(array_filter(
              $innerExceptions,
              static fn(\DateTimeImmutable $d) =>
                (int) $d->format(self::ISO8601_WEEKDAY) === $day,
            ));

            $groupSubRules[] = new WeekdayRule(
              [$day],
              array_values($allSlots),
              $ruleA->startDate,
              $ruleA->endDate,
              $dayExceptions,
            );
          }
        }
        else {
          $groupSubRules[] = new WeekdayRule(
            $ruleB->weekdays,
            array_values($allSlots),
            $ruleA->startDate,
            $ruleA->endDate,
            $innerExceptions,
          );
        }
      }

      $phase1Result[] = new WeekdayGroupRule(
        $groupSubRules,
        $ruleA->startDate,
        $ruleA->endDate,
      );
    }

    // Collect standalone rules that were not consumed in Phase 1.
    $standaloneRules = [];
    foreach ($standaloneIdx as $idx) {
      $standaloneRules[] = $weekdayRules[$idx];
    }

    // Phase 2: combine rules with disjoint weekday sets and overlapping ranges.
    $m            = count($standaloneRules);
    $used2        = array_fill(0, $m, FALSE);
    $phase2Result = [];

    for ($i = 0; $i < $m; $i++) {
      if ($used2[$i]) {
        continue;
      }

      $ruleA       = $standaloneRules[$i];
      $setA        = array_flip($ruleA->weekdays);
      $partners    = [];
      $partnerIdxs = [];

      for ($j = $i + 1; $j < $m; $j++) {
        if ($used2[$j]) {
          continue;
        }

        $ruleB = $standaloneRules[$j];

        // Weekday sets must be completely disjoint.
        $disjoint = TRUE;
        foreach ($ruleB->weekdays as $day) {
          if (isset($setA[$day])) {
            $disjoint = FALSE;
            break;
          }
        }
        if (!$disjoint) {
          continue;
        }

        // Date ranges must overlap.
        if (
          $ruleA->startDate->format(self::YEAR_MONTH_DAY)
            > $ruleB->endDate->format(self::YEAR_MONTH_DAY) ||
          $ruleB->startDate->format(self::YEAR_MONTH_DAY)
            > $ruleA->endDate->format(self::YEAR_MONTH_DAY)
        ) {
          continue;
        }

        $partners[]    = $ruleB;
        $partnerIdxs[] = $j;
      }

      $used2[$i] = TRUE;

      if (empty($partners)) {
        $phase2Result[] = $ruleA;
        continue;
      }

      foreach ($partnerIdxs as $j) {
        $used2[$j] = TRUE;
      }

      // Build the group: compute the union date range and sort by weekday.
      $allSubRules = array_merge([$ruleA], $partners);

      $startDate = $ruleA->startDate;
      $endDate   = $ruleA->endDate;
      foreach ($partners as $partner) {
        if ($partner->startDate < $startDate) {
          $startDate = $partner->startDate;
        }
        if ($partner->endDate > $endDate) {
          $endDate = $partner->endDate;
        }
      }

      usort(
        $allSubRules,
        static fn(WeekdayRule $a, WeekdayRule $b) =>
          min($a->weekdays) <=> min($b->weekdays),
      );

      $phase2Result[] = new WeekdayGroupRule(
        $allSubRules,
        $startDate,
        $endDate
      );
    }

    return array_merge($phase1Result, $phase2Result, $otherRules);
  }

}
