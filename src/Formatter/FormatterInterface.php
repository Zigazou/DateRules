<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Formatter;

use Zigazou\DateRules\RuleSet;

/**
 * Transforms a RuleSet into a human-readable text representation.
 */
interface FormatterInterface {

  /**
   * Returns a human-readable string describing all rules in the set.
   *
   * Each rule is typically on its own line.
   *
   * @param \Zigazou\DateRules\RuleSet $ruleSet
   *   The rule set to format.
   * @param bool $outputHtml
   *   Whether to output HTML or plain text.
   *
   * @return string
   *   A human-readable string describing the rule set.
   */
  public function format(RuleSet $ruleSet, bool $outputHtml = FALSE): string;

}
