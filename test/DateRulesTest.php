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
   *
   * @return string
   *   The formatted rule set.
   */
  private function parseAndFormat(string $filename): string {
    $entries = $this->parser->parse(
      file_get_contents(__DIR__ . '/' . $filename)
    );
    $ruleSet = $this->analyzer->analyze($entries);

    return $this->formatter->format($ruleSet);
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
      . ' le 31 décembre 2026 et le 1er janvier 2027).',
      '- samedi et dimanche de 10h à 12h30 et de 13h30 à 18h15'
      . ' (sauf le 15 août 2026 et le 1er novembre 2026)',
    ]);

    $this->assertSame($expected, $this->parseAndFormat('liste-date-1.txt'));
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

    $this->assertSame($expected, $this->parseAndFormat('liste-date-2.txt'));
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

    $this->assertSame($expected, $this->parseAndFormat('liste-date-3.txt'));
  }

  /**
   * Tests formatting of 'liste-date-4.txt'.
   *
   * Single date range with a single time slot, covering all weekdays.
   */
  public function testListeDate4(): void {
    $expected = 'Du 10 au 14 juillet 2026 : tous les jours de 10h à 12h.';

    $this->assertSame($expected, $this->parseAndFormat('liste-date-4.txt'));
  }

}
