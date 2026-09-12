#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Gate: the suite counts quoted in the documentation must match the suite that just ran.
 *
 * README § "Why This Bundle" and llms.md § Overview each state an exact
 * "**N tests, M assertions**". Those numbers are the load-bearing half of the
 * verified-quality claim, and until this gate existed they were copied across by hand —
 * so they went stale the moment anyone added a test, in the one document a reader uses
 * to decide whether to trust the bundle at all.
 *
 * Failing on every pull request that adds a test is the intended behaviour, not a
 * nuisance: it is what forces the claim and the suite to move together.
 *
 * Reads PHPUnit's JUnit log rather than scraping console output — no ANSI colours to
 * strip, and no shell pipe that could mask PHPUnit's own exit code.
 *
 * Usage: php tools/check-doc-claims.php [path/to/junit.xml]
 */

$root = \dirname(__DIR__);
$logPath = $argv[1] ?? $root . '/var/junit.xml';

if (!is_file($logPath)) {
    fwrite(STDERR, \sprintf(
        "error: JUnit log \"%s\" not found.\n"
        . "Run the suite first (composer test), or pass the log path as an argument.\n",
        $logPath,
    ));

    exit(1);
}

$xml = @simplexml_load_file($logPath);

if (false === $xml) {
    fwrite(STDERR, \sprintf("error: could not parse JUnit log \"%s\".\n", $logPath));

    exit(1);
}

$actualTests = 0;
$actualAssertions = 0;

foreach ($xml->testsuite as $suite) {
    $actualTests += (int) $suite['tests'];
    $actualAssertions += (int) $suite['assertions'];
}

if (0 === $actualTests) {
    fwrite(STDERR, \sprintf("error: JUnit log \"%s\" reports no tests.\n", $logPath));

    exit(1);
}

/**
 * Files that state the counts. Each MUST contain at least one claim — a file that has
 * quietly lost its claim would otherwise pass this gate silently.
 */
$documents = [
    'README.md',
    'llms.md',
];

$expected = \sprintf(
    '**%s tests, %s assertions**',
    number_format($actualTests),
    number_format($actualAssertions),
);

$problems = [];

foreach ($documents as $document) {
    $path = $root . '/' . $document;
    $contents = is_file($path) ? (string) file_get_contents($path) : '';

    if ('' === $contents) {
        $problems[] = \sprintf('%s: could not be read.', $document);

        continue;
    }

    $found = preg_match_all(
        '/\*\*([\d,]+) tests, ([\d,]+) assertions\*\*/',
        $contents,
        $matches,
        PREG_SET_ORDER,
    );

    if (0 === $found) {
        $problems[] = \sprintf(
            '%s: states no "**N tests, M assertions**" claim at all. It should say %s.',
            $document,
            $expected,
        );

        continue;
    }

    foreach ($matches as $match) {
        $claimedTests = (int) str_replace(',', '', $match[1]);
        $claimedAssertions = (int) str_replace(',', '', $match[2]);

        if ($claimedTests !== $actualTests || $claimedAssertions !== $actualAssertions) {
            $problems[] = \sprintf('%s: claims %s, suite ran %s.', $document, $match[0], $expected);
        }
    }
}

if ([] !== $problems) {
    fwrite(STDERR, "Documented suite counts do not match the suite:\n\n");

    foreach ($problems as $problem) {
        fwrite(STDERR, '  - ' . $problem . "\n");
    }

    fwrite(STDERR, \sprintf(
        "\nUpdate the claim in every file listed above to:\n\n    %s\n\n"
        . "The numbers are part of the README's verified-quality argument; they are only\n"
        . "worth stating while they are true.\n",
        $expected,
    ));

    exit(1);
}

fwrite(STDOUT, \sprintf("Documented suite counts match: %s\n", $expected));

exit(0);
