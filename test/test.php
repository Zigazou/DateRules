<?php

/**
 * @file
 */

require '../vendor/autoload.php';

use Zigazou\DateRules\Parser\PipeSeparatedParser;
use Zigazou\DateRules\Analyzer\Analyzer;
use Zigazou\DateRules\Formatter\FrenchFormatter;

$parser    = new PipeSeparatedParser();
$analyzer  = new Analyzer();
$formatter = new FrenchFormatter();

echo "=== liste-date-1.txt ===\n";
$entries = $parser->parse(file_get_contents('liste-date-1.txt'));
$ruleSet = $analyzer->analyze($entries);
echo $formatter->format($ruleSet) . "\n\n";

echo "=== liste-date-2.txt ===\n";
$entries = $parser->parse(file_get_contents('liste-date-2.txt'));
$ruleSet = $analyzer->analyze($entries);
echo $formatter->format($ruleSet) . "\n";

echo "=== liste-date-3.txt ===\n";
$entries = $parser->parse(file_get_contents('liste-date-3.txt'));
$ruleSet = $analyzer->analyze($entries);
echo $formatter->format($ruleSet) . "\n";