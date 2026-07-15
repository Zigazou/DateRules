<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Tests;

use Zigazou\DateRules\Analyzer\Analyzer;
use Zigazou\DateRules\Parser\PipeSeparatedParser;
use Zigazou\DateRules\RuleSet;

/**
 * Tests for the DateRules library.
 */
class GetAnalyzed {

  /**
   * Parser to convert a pipe-separated list of date intervals.
   */
  private PipeSeparatedParser $parser;

  /**
   * Analyzer to convert a list of date entries into a rule set.
   */
  private Analyzer $analyzer;

  /**
   * Sets up the environment.
   *
   * Initializes the parser and analyzer.
   */
  public function __construct() {
    $this->parser   = new PipeSeparatedParser();
    $this->analyzer = new Analyzer();
  }

  /**
   * Parses a file and formats the resulting rule set.
   *
   * @param string $filename
   *   The name of the file to parse.
   *
   * @return \Zigazou\DateRules\RuleSet
   *   The rules set.
   */
  public function parseAndFormat(string $filename): RuleSet {
    $entries = $this->parser->parse(
      file_get_contents(__DIR__ . '/' . $filename)
    );

    return $this->analyzer->analyze($entries);
  }

}

require 'vendor/autoload.php';

$getAnalyzed = new GetAnalyzed();
print_r($getAnalyzed->parseAndFormat('liste-date-6.txt'));
