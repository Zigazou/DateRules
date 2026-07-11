<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Parser;

use Zigazou\DateRules\DateEntry;

/**
 * Parses a string (or file contents) into a list of DateEntry objects.
 */
interface ParserInterface
{
    /**
     * @return DateEntry[]
     * @throws \InvalidArgumentException On malformed input.
     */
    public function parse(string $input): array;
}
