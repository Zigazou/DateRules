<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Parser;

use Zigazou\DateRules\DateEntry;

/**
 * Parses date interval lists where each line has the form:
 *
 *   YYYY-MM-DD HH:MM|YYYY-MM-DD HH:MM
 *
 * Lines that are empty or start with '#' are silently ignored.
 * Throws \InvalidArgumentException on malformed input.
 */
final class PipeSeparatedParser implements ParserInterface
{
    public function parse(string $input): array
    {
        $entries = [];

        foreach (explode("\n", $input) as $lineNumber => $rawLine) {
            $line = trim($rawLine);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('|', $line, 2);

            if (count($parts) !== 2) {
                throw new \InvalidArgumentException(
                    sprintf(
                        "Line %d: expected 'START|END' format, got: %s",
                        $lineNumber + 1,
                        $line,
                    ),
                );
            }

            $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i', trim($parts[0]));
            $end   = \DateTimeImmutable::createFromFormat('Y-m-d H:i', trim($parts[1]));

            if ($start === false || $end === false) {
                throw new \InvalidArgumentException(
                    sprintf(
                        "Line %d: invalid datetime (expected 'YYYY-MM-DD HH:MM'): %s",
                        $lineNumber + 1,
                        $line,
                    ),
                );
            }

            $entries[] = new DateEntry($start, $end);
        }

        return $entries;
    }
}
