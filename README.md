# DateRules

DateRules is a PHP library that infers reusable scheduling rules from a flat list
of date-time intervals.

It helps you turn raw entries like:

- 2026-07-06 10:00 -> 2026-07-06 11:00
- 2026-07-13 10:00 -> 2026-07-13 11:00
- 2026-07-20 10:00 -> 2026-07-20 11:00

into compact rules such as:

- every Monday from 10h to 11h (within a date range)
- every day from X to Y, with explicit exceptions
- grouped weekday patterns sharing the same date range

## Features

- Analyze a list of intervals into a minimal rule set
- Detect weekly patterns, date ranges, and missing-date exceptions
- Merge compatible rules and time slots automatically
- Format results as human-readable French text
- Keep the API simple and composable

## Installation

```bash
composer require zigazou/date-rules
```

## Quick Start

```php
<?php

declare(strict_types=1);

use Zigazou\DateRules\Analyzer\Analyzer;
use Zigazou\DateRules\DateEntry;
use Zigazou\DateRules\Formatter\FrenchFormatter;

require __DIR__ . '/vendor/autoload.php';

$entries = [
	new DateEntry(
		new DateTimeImmutable('2026-07-06 10:00'),
		new DateTimeImmutable('2026-07-06 11:00'),
	),
	new DateEntry(
		new DateTimeImmutable('2026-07-13 10:00'),
		new DateTimeImmutable('2026-07-13 11:00'),
	),
	new DateEntry(
		new DateTimeImmutable('2026-07-20 10:00'),
		new DateTimeImmutable('2026-07-20 11:00'),
	),
];

$analyzer = new Analyzer();
$ruleSet = $analyzer->analyze($entries);

$formatter = new FrenchFormatter();
echo $formatter->format($ruleSet) . PHP_EOL;
```

Example output:

```text
Du 6 au 20 juillet 2026 : lundi de 10h a 11h.
```

## Typical Workflow

1. Build an array of `DateEntry` objects from your source data.
2. Call `Analyzer::analyze()` to produce a `RuleSet`.
3. Format the `RuleSet` for display (`FrenchFormatter`) or consume it directly.

## API Overview

### Core Value Objects

- `DateEntry`: one interval (`start`, `end`) on a calendar day
- `TimeSlot`: time-only range extracted from an entry
- `RuleSet`: ordered list of inferred rules

### Analyzer

- `Analyzer::analyze(array $entries): RuleSet`

The analyzer groups entries by time slot, detects the best rule shape for each
group, and merges compatible rules.

### Rule Types

- `DateRangeRule`: applies to every day in a contiguous date range
- `WeekdayRule`: applies to selected weekdays in a date range
- `WeekdayGroupRule`: groups related weekday sub-rules sharing one date range

### Formatting

- `FormatterInterface`: contract for output rendering
- `FrenchFormatter`: built-in human-readable formatter in French

## Inspecting Rules Programmatically

```php
<?php

declare(strict_types=1);

use Zigazou\DateRules\Rule\DateRangeRule;
use Zigazou\DateRules\Rule\WeekdayGroupRule;
use Zigazou\DateRules\Rule\WeekdayRule;

foreach ($ruleSet->rules as $rule) {
	if ($rule instanceof DateRangeRule) {
		echo 'DateRangeRule from '
			. $rule->startDate->format('Y-m-d')
			. ' to '
			. $rule->endDate->format('Y-m-d')
			. PHP_EOL;
	}

	if ($rule instanceof WeekdayRule) {
		echo 'WeekdayRule on weekdays: '
			. implode(',', $rule->weekdays)
			. PHP_EOL;
	}

	if ($rule instanceof WeekdayGroupRule) {
		echo 'WeekdayGroupRule with '
			. count($rule->subRules)
			. ' sub-rules'
			. PHP_EOL;
	}
}
```

## Working From Text Input

This package focuses on analysis and formatting. If your data source is text,
parse it first into `DateEntry[]`.

Expected line format:

```text
YYYY-MM-DD HH:MM|YYYY-MM-DD HH:MM
```

Example of converting lines into entries:

```php
<?php

declare(strict_types=1);

use Zigazou\DateRules\DateEntry;

function parsePipeSeparatedEntries(string $input): array
{
	$entries = [];

	foreach (explode("\n", $input) as $rawLine) {
		$line = trim($rawLine);

		if ($line === '' || str_starts_with($line, '#')) {
			continue;
		}

		[$startRaw, $endRaw] = array_map('trim', explode('|', $line, 2));

		$entries[] = new DateEntry(
			new DateTimeImmutable($startRaw),
			new DateTimeImmutable($endRaw),
		);
	}

	return $entries;
}
```

## Tests

```bash
vendor/bin/phpunit
```

## License

BSD-3-Clause
