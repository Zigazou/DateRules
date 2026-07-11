<?php

declare(strict_types=1);

namespace Zigazou\DateRules\Parser;

/**
 * Parses a string (or file contents) into a list of DateEntry objects.
 */
interface ParserInterface {

  /**
   * Parses a string into a list of DateEntry objects.
   *
   * @param string $input
   *   The string to parse.
   *
   * @return \Zigazou\DateRules\DateEntry[]
   *   The parsed date entries.
   *
   * @throws \InvalidArgumentException
   *   On malformed input.
   */
  public function parse(string $input): array;

}
