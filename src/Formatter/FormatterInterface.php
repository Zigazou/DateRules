<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Formatter;

use Zigazou\DateRules\RuleSet;

/**
 * Transforms a RuleSet into a human-readable text representation.
 */
interface FormatterInterface
{
    /**
     * Returns a human-readable string describing all rules in the set.
     * Each rule is typically on its own line.
     */
    public function format(RuleSet $ruleSet): string;
}
