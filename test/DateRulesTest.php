<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Tests;

use PHPUnit\Framework\TestCase;
use Zigazou\DateRules\Analyzer\Analyzer;
use Zigazou\DateRules\Formatter\FrenchFormatter;
use Zigazou\DateRules\Parser\PipeSeparatedParser;

/**
 * Tests for the DateRules library.
 */
class DateRulesTest extends TestCase {

  /**
   * Parser to convert a pipe-separated list of date intervals.
   */
  private PipeSeparatedParser $parser;

  /**
   * Analyzer to convert a list of date entries into a rule set.
   */
  private Analyzer $analyzer;

  /**
   * Formatter to convert a rule set into a human-readable string.
   */
  private FrenchFormatter $formatter;

  /**
   * Sets up the test environment.
   *
   * Initializes the parser, analyzer, and formatter.
   */
  protected function setUp(): void {
    $this->parser    = new PipeSeparatedParser();
    $this->analyzer  = new Analyzer();
    $this->formatter = new FrenchFormatter();
  }

  /**
   * Parses a file and formats the resulting rule set.
   *
   * @param string $filename
   *   The name of the file to parse.
   * @param bool $outputHtml
   *   Whether to output HTML or plain text.
   *
   * @return string
   *   The formatted rule set.
   */
  private function parseAndFormat(string $filename, bool $outputHtml = FALSE): string {
    $entries = $this->parser->parse(
      file_get_contents(__DIR__ . '/' . $filename)
    );
    $ruleSet = $this->analyzer->analyze($entries);

    return $this->formatter->format($ruleSet, $outputHtml);
  }

  /**
   * Tests formatting of 'liste-date-1.txt'.
   *
   * Classical event schedule with exceptions, covering multiple weekdays and
   * weekends.
   */
  public function testListeDate1(): void {
    $expected = implode("\n", [
      'Du 13 avril 2026 au 4 janvier 2027 :',
      '- lundi, mercredi, jeudi et vendredi de 13h30 à 18h15'
      . ' (sauf le 24 décembre 2026, le 25 décembre 2026,'
      . ' le 31 décembre 2026 et le 1er janvier 2027)',
      '- samedi et dimanche de 10h à 12h30 et de 13h30 à 18h15'
      . ' (sauf le 15 août 2026 et le 1er novembre 2026)',
    ]);

    $this->assertSame($expected, $this->parseAndFormat('liste-date-1.txt', FALSE));

    $expected = implode("", [
      '<p>Du 13 avril 2026 au 4 janvier 2027 :</p>',
      '<ul>',
      '<li>lundi, mercredi, jeudi et vendredi de 13h30 à 18h15'
      . ' (sauf le 24 décembre 2026, le 25 décembre 2026,'
      . ' le 31 décembre 2026 et le 1er janvier 2027)</li>',
      '<li>samedi et dimanche de 10h à 12h30 et de 13h30 à 18h15'
      . ' (sauf le 15 août 2026 et le 1er novembre 2026)</li>',
      '</ul>',
    ]);

    $this->assertSame($expected, $this->parseAndFormat('liste-date-1.txt', TRUE));
  }

  /**
   * Tests formatting of 'liste-date-2.txt'.
   *
   * Multiple date ranges with different time slots, covering various weekdays
   * and weekends.
   */
  public function testListeDate2(): void {
    $expected = implode("\n", [
      'Du 5 au 27 juin 2026 : vendredi et samedi de 23h à minuit.',
      'Du 1er au 30 juillet 2026 : tous les jours de 23h à minuit.',
      'Du 31 juillet au 14 août 2026 : tous les jours de 22h30 à 23h30.',
      'Du 15 au 30 août 2026 : tous les jours de 22h à 23h.',
      'Du 4 au 26 septembre 2026 : vendredi et samedi de 21h30 à 22h30.',
    ]);

    $this->assertSame($expected, $this->parseAndFormat('liste-date-2.txt', FALSE));

    $expected = implode("", [
      '<p>Du 5 au 27 juin 2026 : vendredi et samedi de 23h à minuit.</p>',
      '<p>Du 1er au 30 juillet 2026 : tous les jours de 23h à minuit.</p>',
      '<p>Du 31 juillet au 14 août 2026 : tous les jours de 22h30 à 23h30.</p>',
      '<p>Du 15 au 30 août 2026 : tous les jours de 22h à 23h.</p>',
      '<p>Du 4 au 26 septembre 2026 : vendredi et samedi de 21h30 à 22h30.</p>',
    ]);

    $this->assertSame($expected, $this->parseAndFormat('liste-date-2.txt', TRUE));
  }

  /**
   * Tests formatting of 'liste-date-3.txt'.
   *
   * Single-day events with different time slots, covering various weekdays.
   */
  public function testListeDate3(): void {
    $expected = implode("\n", [
      'Vendredi 10 juillet 2026, de 10h à 11h.',
      'Mercredi 15 juillet 2026, toute la journée.',
      'Lundi 20 juillet 2026, de 22h à minuit.',
    ]);

    $this->assertSame($expected, $this->parseAndFormat('liste-date-3.txt', FALSE));

    $expected = implode("", [
      '<p>Vendredi 10 juillet 2026, de 10h à 11h.</p>',
      '<p>Mercredi 15 juillet 2026, toute la journée.</p>',
      '<p>Lundi 20 juillet 2026, de 22h à minuit.</p>',
    ]);

    $this->assertSame($expected, $this->parseAndFormat('liste-date-3.txt', TRUE));
  }

  /**
   * Tests formatting of 'liste-date-4.txt'.
   *
   * Single date range with a single time slot, covering all weekdays.
   */
  public function testListeDate4(): void {
    $expected = 'Du 10 au 14 juillet 2026 : tous les jours de 10h à 12h.';

    $this->assertSame($expected, $this->parseAndFormat('liste-date-4.txt', FALSE));

    $expected = '<p>Du 10 au 14 juillet 2026 : tous les jours de 10h à 12h.</p>';

    $this->assertSame($expected, $this->parseAndFormat('liste-date-4.txt', TRUE));
  }

  /**
   * Tests formatting of 'liste-date-5.txt'.
   *
   * .
   */
  public function testListeDate5(): void {
    $expected = implode("\n", [
      'Du 11 avril au 30 juin 2026 : du mardi au dimanche de 10h à 18h.',
      'Du 1er juillet au 30 août 2026 :',
      '- mardi, mercredi, jeudi et vendredi : de 11h à 19h',
      '- samedi et dimanche : de 11h à 12h30 et de 13h30 à 19h',
      'Du 1er au 20 septembre 2026 :',
      '- mardi, mercredi, jeudi et vendredi : de 10h à 18h',
      '- samedi et dimanche : de 10h à 12h30 et de 13h30 à 18h',
    ]);

    $this->assertSame($expected, $this->parseAndFormat('liste-date-5.txt', FALSE));

    $expected = implode("", [
      '<p>Du 11 avril au 30 juin 2026 : du mardi au dimanche de 10h à 18h.</p>',
      '<p>Du 1er juillet au 30 août 2026 :</p>',
      '<ul>',
      '<li>mardi, mercredi, jeudi et vendredi : de 11h à 19h</li>',
      '<li>samedi et dimanche : de 11h à 12h30 et de 13h30 à 19h</li>',
      '</ul>',
      '<p>Du 1er au 20 septembre 2026 :</p>',
      '<ul>',
      '<li>mardi, mercredi, jeudi et vendredi : de 10h à 18h</li>',
      '<li>samedi et dimanche : de 10h à 12h30 et de 13h30 à 18h</li>',
      '</ul>',
    ]);

    $this->assertSame($expected, $this->parseAndFormat('liste-date-5.txt', TRUE));
  }

  // ---------------------------------------------------------------------------
  // Helper
  // ---------------------------------------------------------------------------

  /**
   * Parses an inline pipe-separated string and formats the resulting rule set.
   *
   * @param string $input
   *   Raw pipe-separated date interval text.
   * @param bool $outputHtml
   *   Whether to output HTML or plain text.
   *
   * @return string
   *   The formatted rule set.
   */
  private function parseStringAndFormat(string $input, bool $outputHtml = FALSE): string {
    $entries = $this->parser->parse($input);
    $ruleSet = $this->analyzer->analyze($entries);

    return $this->formatter->format($ruleSet, $outputHtml);
  }

  // ---------------------------------------------------------------------------
  // Parser edge cases
  // ---------------------------------------------------------------------------

  /**
   * An empty string produces no entries.
   */
  public function testParserEmptyInput(): void {
    $this->assertSame([], $this->parser->parse(''));
  }

  /**
   * Lines that are blank or start with '#' are silently skipped.
   */
  public function testParserIgnoresCommentsAndBlankLines(): void {
    $entries = $this->parser->parse(
      "# comment\n\n  \n2026-07-10 10:00|2026-07-10 11:00\n# trailing comment"
    );
    $this->assertCount(1, $entries);
  }

  /**
   * A line with no pipe separator throws an InvalidArgumentException.
   */
  public function testParserThrowsOnMissingPipe(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->parser->parse('2026-07-10 10:00 2026-07-10 11:00');
  }

  /**
   * A line with an invalid datetime value throws an InvalidArgumentException.
   */
  public function testParserThrowsOnInvalidDatetime(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->parser->parse('not-a-date|2026-07-10 11:00');
  }

  // ---------------------------------------------------------------------------
  // Single-day formatting edge cases
  // ---------------------------------------------------------------------------

  /**
   * A single Sunday event is prefixed with the capitalised French weekday name.
   *
   * July 12, 2026 is a Sunday.
   */
  public function testSingleDaySunday(): void {
    $this->assertSame(
      'Dimanche 12 juillet 2026, de 10h à 11h.',
      $this->parseStringAndFormat('2026-07-12 10:00|2026-07-12 11:00', FALSE)
    );

    $this->assertSame(
      '<p>Dimanche 12 juillet 2026, de 10h à 11h.</p>',
      $this->parseStringAndFormat('2026-07-12 10:00|2026-07-12 11:00', TRUE)
    );
  }

  /**
   * The 1st of a month uses the "1er" ordinal in a single-day label.
   *
   * August 1, 2026 is a Saturday.
   */
  public function testSingleDayOnFirstOfMonth(): void {
    $this->assertSame(
      'Samedi 1er août 2026, de 14h à 16h.',
      $this->parseStringAndFormat('2026-08-01 14:00|2026-08-01 16:00', FALSE)
    );

    $this->assertSame(
      '<p>Samedi 1er août 2026, de 14h à 16h.</p>',
      $this->parseStringAndFormat('2026-08-01 14:00|2026-08-01 16:00', TRUE)
    );
  }

  /**
   * A 00:00–23:59 slot on the 1st of month yields "toute la journée" and "1er".
   *
   * June 1, 2026 is a Monday.
   */
  public function testSingleDayAllDayOnFirstOfMonth(): void {
    $this->assertSame(
      'Lundi 1er juin 2026, toute la journée.',
      $this->parseStringAndFormat('2026-06-01 00:00|2026-06-01 23:59', FALSE)
    );

    $this->assertSame(
      '<p>Lundi 1er juin 2026, toute la journée.</p>',
      $this->parseStringAndFormat('2026-06-01 00:00|2026-06-01 23:59', TRUE)
    );
  }

  // ---------------------------------------------------------------------------
  // Date-range label formatting
  // ---------------------------------------------------------------------------

  /**
   * Two consecutive days within the same month produce a compressed label.
   *
   * This label has the format "du X au Y mois année".
   *
   * This is also the minimum span (2 days) that triggers a DateRangeRule.
   */
  public function testTwoConsecutiveDaysSameMonth(): void {
    $this->assertSame(
      'Du 10 au 11 juillet 2026 : tous les jours de 9h à 17h.',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-10 09:00|2026-07-10 17:00',
        '2026-07-11 09:00|2026-07-11 17:00',
      ]), FALSE)
    );

    $this->assertSame(
      '<p>Du 10 au 11 juillet 2026 : tous les jours de 9h à 17h.</p>',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-10 09:00|2026-07-10 17:00',
        '2026-07-11 09:00|2026-07-11 17:00',
      ]), TRUE)
    );
  }

  /**
   * A date range crossing a month boundary (same year).
   *
   * It uses the format "du D mois au D mois année".
   */
  public function testCrossMonthSameYearDateRange(): void {
    $this->assertSame(
      'Du 31 juillet au 2 août 2026 : tous les jours de 9h à 17h.',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-31 09:00|2026-07-31 17:00',
        '2026-08-01 09:00|2026-08-01 17:00',
        '2026-08-02 09:00|2026-08-02 17:00',
      ]), FALSE)
    );

    $this->assertSame(
      '<p>Du 31 juillet au 2 août 2026 : tous les jours de 9h à 17h.</p>',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-31 09:00|2026-07-31 17:00',
        '2026-08-01 09:00|2026-08-01 17:00',
        '2026-08-02 09:00|2026-08-02 17:00',
      ]), TRUE)
    );
  }

  /**
   * A date range crossing a year boundary.
   *
   * Such a date uses the full "du D mois YYYY au D mois YYYY" label; "1er
   * janvier" must use the ordinal form.
   */
  public function testCrossYearDateRange(): void {
    $this->assertSame(
      'Du 31 décembre 2025 au 1er janvier 2026 : tous les jours de 10h à 17h.',
      $this->parseStringAndFormat(implode("\n", [
        '2025-12-31 10:00|2025-12-31 17:00',
        '2026-01-01 10:00|2026-01-01 17:00',
      ]), FALSE)
    );

    $this->assertSame(
      '<p>Du 31 décembre 2025 au 1er janvier 2026 : tous les jours de 10h à 17h.</p>',
      $this->parseStringAndFormat(implode("\n", [
        '2025-12-31 10:00|2025-12-31 17:00',
        '2026-01-01 10:00|2026-01-01 17:00',
      ]), TRUE)
    );
  }

  // ---------------------------------------------------------------------------
  // Density-based "all-days" detection
  // ---------------------------------------------------------------------------

  /**
   * Case where 13 of 14 days are present with all 7 weekdays.
   *
   * If density ≥ 0.85, it produces a DateRangeRule with a single exception
   * rather than a WeekdayRule.
   *
   * July 6–19, 2026 (14-day span); July 13 is absent.
   */
  public function testDailyRangeWithOneException(): void {
    $lines = [];
    for ($d = 6; $d <= 19; $d++) {
      if ($d === 13) {
        continue;
      }
      $lines[] = sprintf('2026-07-%02d 10:00|2026-07-%02d 11:00', $d, $d);
    }

    $this->assertSame(
      'Du 6 au 19 juillet 2026 : tous les jours de 10h à 11h (sauf le 13 juillet 2026).',
      $this->parseStringAndFormat(implode("\n", $lines), FALSE)
    );

    $this->assertSame(
      '<p>Du 6 au 19 juillet 2026 : tous les jours de 10h à 11h (sauf le 13 juillet 2026).</p>',
      $this->parseStringAndFormat(implode("\n", $lines), TRUE)
    );
  }

  // ---------------------------------------------------------------------------
  // Weekday-pattern edge cases
  // ---------------------------------------------------------------------------

  /**
   * Entries that fall on a single weekday (Monday).
   *
   * If there are no missing occurrences, it produces a plain WeekdayRule with
   * no exception clause.
   */
  public function testWeeklyPatternSingleWeekdayNoExceptions(): void {
    $this->assertSame(
      'Du 6 au 27 juillet 2026 : lundi de 10h à 11h.',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-06 10:00|2026-07-06 11:00',
        '2026-07-13 10:00|2026-07-13 11:00',
        '2026-07-20 10:00|2026-07-20 11:00',
        '2026-07-27 10:00|2026-07-27 11:00',
      ]), FALSE)
    );

    $this->assertSame(
      '<p>Du 6 au 27 juillet 2026 : lundi de 10h à 11h.</p>',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-06 10:00|2026-07-06 11:00',
        '2026-07-13 10:00|2026-07-13 11:00',
        '2026-07-20 10:00|2026-07-20 11:00',
        '2026-07-27 10:00|2026-07-27 11:00',
      ]), TRUE)
    );
  }

  /**
   * A single missing occurrence in a weekday run appears in the "sauf" clause.
   *
   * Tuesdays July 7, 21, 28 (July 14 absent).
   */
  public function testWeeklyPatternWithOneException(): void {
    $this->assertSame(
      'Du 7 au 28 juillet 2026 : mardi de 15h à 16h (sauf le 14 juillet 2026).',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-07 15:00|2026-07-07 16:00',
        '2026-07-21 15:00|2026-07-21 16:00',
        '2026-07-28 15:00|2026-07-28 16:00',
      ]), FALSE)
    );

    $this->assertSame(
      '<p>Du 7 au 28 juillet 2026 : mardi de 15h à 16h (sauf le 14 juillet 2026).</p>',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-07 15:00|2026-07-07 16:00',
        '2026-07-21 15:00|2026-07-21 16:00',
        '2026-07-28 15:00|2026-07-28 16:00',
      ]), TRUE)
    );
  }

  /**
   * Two non-adjacent missing occurrences make a comma-separated "sauf" clause.
   *
   * Mondays July 6 and July 27 only (July 13 and 20 both absent).
   */
  public function testWeeklyPatternWithTwoExceptions(): void {
    $this->assertSame(
      "Lundi 6 juillet 2026, de 9h à 10h.\nLundi 27 juillet 2026, de 9h à 10h.",
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-06 09:00|2026-07-06 10:00',
        '2026-07-27 09:00|2026-07-27 10:00',
      ]), FALSE)
    );

    $this->assertSame(
      '<p>Lundi 6 juillet 2026, de 9h à 10h.</p><p>Lundi 27 juillet 2026, de 9h à 10h.</p>',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-06 09:00|2026-07-06 10:00',
        '2026-07-27 09:00|2026-07-27 10:00',
      ]), TRUE)
    );
  }

  /**
   * Saturdays crossing a year boundary use the cross-year date range label.
   */
  public function testCrossYearWeeklyPattern(): void {
    $this->assertSame(
      'Du 27 décembre 2025 au 10 janvier 2026 : samedi de 10h à 11h.',
      $this->parseStringAndFormat(implode("\n", [
        '2025-12-27 10:00|2025-12-27 11:00',
        '2026-01-03 10:00|2026-01-03 11:00',
        '2026-01-10 10:00|2026-01-10 11:00',
      ]), FALSE)
    );

    $this->assertSame(
      '<p>Du 27 décembre 2025 au 10 janvier 2026 : samedi de 10h à 11h.</p>',
      $this->parseStringAndFormat(implode("\n", [
        '2025-12-27 10:00|2025-12-27 11:00',
        '2026-01-03 10:00|2026-01-03 11:00',
        '2026-01-10 10:00|2026-01-10 11:00',
      ]), TRUE)
    );
  }

  // ---------------------------------------------------------------------------
  // Time-slot merging
  // ---------------------------------------------------------------------------

  /**
   * Two distinct time slots on the same weekday pattern.
   *
   * They should be merged into a single rule expressed as "de X à Y et de Z à
   * W".
   *
   * Mondays July 6 and 13, each with a morning and an afternoon slot.
   */
  public function testMultipleTimeSlotsOnSameWeekdayPattern(): void {
    $this->assertSame(
      'Du 6 au 13 juillet 2026 : lundi de 10h à 12h et de 14h à 18h.',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-06 10:00|2026-07-06 12:00',
        '2026-07-06 14:00|2026-07-06 18:00',
        '2026-07-13 10:00|2026-07-13 12:00',
        '2026-07-13 14:00|2026-07-13 18:00',
      ]), FALSE)
    );

    $this->assertSame(
      '<p>Du 6 au 13 juillet 2026 : lundi de 10h à 12h et de 14h à 18h.</p>',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-06 10:00|2026-07-06 12:00',
        '2026-07-06 14:00|2026-07-06 18:00',
        '2026-07-13 10:00|2026-07-13 12:00',
        '2026-07-13 14:00|2026-07-13 18:00',
      ]), TRUE)
    );
  }

  // ---------------------------------------------------------------------------
  // Stitched-entry normalisation
  // ---------------------------------------------------------------------------

  /**
   * An entry ending at 23:59 chained to a next-day entry starting at 00:00.
   *
   * It should be normalised into uniform per-day entries sharing the outer time
   * slot.
   */
  public function testStitchedEntriesAcrossMidnightBoundary(): void {
    $this->assertSame(
      'Du 10 au 11 juillet 2026 : tous les jours de 10h à 18h.',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-10 10:00|2026-07-10 23:59',
        '2026-07-11 00:00|2026-07-11 18:00',
      ]), FALSE)
    );

    $this->assertSame(
      '<p>Du 10 au 11 juillet 2026 : tous les jours de 10h à 18h.</p>',
      $this->parseStringAndFormat(implode("\n", [
        '2026-07-10 10:00|2026-07-10 23:59',
        '2026-07-11 00:00|2026-07-11 18:00',
      ]), TRUE)
    );
  }

  // ---------------------------------------------------------------------------
  // Analyzer branching thresholds
  // ---------------------------------------------------------------------------

  /**
   * Two runs of 5 consecutive weekdays (Mon–Fri) separated by a weekend.
   *
   * They do not meet MIN_DAILY_RUN_LENGTH (7) and therefore fall back to a
   * WeekdayRule.
   */
  public function testShortConsecutiveRunFallsBackToWeekdayRule(): void {
    $lines = [];
    foreach (['06', '07', '08', '09', '10', '13', '14', '15', '16', '17'] as $day) {
      $lines[] = "2026-07-{$day} 10:00|2026-07-{$day} 11:00";
    }

    $this->assertSame(
      'Du 6 au 17 juillet 2026 : du lundi au vendredi de 10h à 11h.',
      $this->parseStringAndFormat(implode("\n", $lines), FALSE)
    );

    $this->assertSame(
      '<p>Du 6 au 17 juillet 2026 : du lundi au vendredi de 10h à 11h.</p>',
      $this->parseStringAndFormat(implode("\n", $lines), TRUE)
    );
  }

  /**
   * A run of exactly 7 consecutive days.
   *
   * It is extracted as a DateRangeRule while isolated dates outside the run
   * become a separate WeekdayRule.
   *
   * July 1–7 (Wed–Tue) forms the daily range; July 20 and 27 (both Mondays)
   * become the remainder.
   */
  public function testLongRunExtractedLeavingWeekdayRemainder(): void {
    $lines = [];
    foreach (['01', '02', '03', '04', '05', '06', '07'] as $day) {
      $lines[] = "2026-07-{$day} 10:00|2026-07-{$day} 11:00";
    }
    $lines[] = '2026-07-20 10:00|2026-07-20 11:00';
    $lines[] = '2026-07-27 10:00|2026-07-27 11:00';

    $this->assertSame(
      implode("\n", [
        'Du 1er au 7 juillet 2026 : tous les jours de 10h à 11h.',
        'Du 20 au 27 juillet 2026 : lundi de 10h à 11h.',
      ]),
      $this->parseStringAndFormat(implode("\n", $lines), FALSE)
    );

    $this->assertSame(
      implode("", [
        '<p>Du 1er au 7 juillet 2026 : tous les jours de 10h à 11h.</p>',
        '<p>Du 20 au 27 juillet 2026 : lundi de 10h à 11h.</p>',
      ]),
      $this->parseStringAndFormat(implode("\n", $lines), TRUE)
    );
  }

  // ---------------------------------------------------------------------------
  // WeekdayGroupRule
  // ---------------------------------------------------------------------------

  /**
   * When a weekday set B is a proper subset of weekday set A.
   *
   * If both share the same date range, they are grouped into a
   * WeekdayGroupRule.
   *
   * Mon–Sat share a 14h–17h slot; Sat additionally has a 10h–12h slot. The
   * formatter emits a labelled block with: – Mon–Fri: only the shared slot –
   * Sat: both slots combined.
   */
  public function testWeekdayGroupRuleFromSubsetWeekdays(): void {
    $lines = [];
    // Mon–Sat, three weeks, 14h–17h.
    foreach ([
      '06', '07', '08', '09', '10', '11',
      '13', '14', '15', '16', '17', '18',
      '20', '21', '22', '23', '24', '25',
    ] as $day) {
      $lines[] = "2026-07-{$day} 14:00|2026-07-{$day} 17:00";
    }
    // Sat only, 10h–12h.
    foreach (['11', '18', '25'] as $day) {
      $lines[] = "2026-07-{$day} 10:00|2026-07-{$day} 12:00";
    }

    $this->assertSame(
      implode("\n", [
        'Du 6 au 25 juillet 2026 :',
        '- du lundi au vendredi : de 14h à 17h',
        '- samedi : de 10h à 12h et de 14h à 17h',
      ]),
      $this->parseStringAndFormat(implode("\n", $lines), FALSE)
    );

    $this->assertSame(
      implode("", [
        '<p>Du 6 au 25 juillet 2026 :</p>',
        '<ul>',
        '<li>du lundi au vendredi : de 14h à 17h</li>',
        '<li>samedi : de 10h à 12h et de 14h à 17h</li>',
        '</ul>',
      ]),
      $this->parseStringAndFormat(implode("\n", $lines), TRUE)
    );
  }

}
