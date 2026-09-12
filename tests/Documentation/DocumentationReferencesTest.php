<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Documentation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Turns the repository's own documentation into assertions.
 *
 * The bundle gates its PHP at 100% line coverage and PHPStan level max, but the prose
 * had no gate at all. That is how three defects survived a fully green build: `llms.txt`
 * credited `Psr6TranslationCache` (deleted in 4.0) with a 60s TTL, and
 * `.claude/architecture.md` linked to `#shared-value-propagation-v41`, an anchor that no
 * longer existed. Each check below is one of those failure modes turned into a test.
 *
 * The same fact is stated in six or seven files (`sync-shared`'s options in six, the
 * `(tuuid, locale)` index in seven), so a rename is always a multi-file edit. These tests
 * are what notices when one of those edits is forgotten.
 */
#[CoversNothing]
final class DocumentationReferencesTest extends TestCase
{
    /**
     * Bundle-owned documentation.
     *
     * The generic tooling skills (`skill-creator`, `agent-md-refactor`, `php-pro`,
     * `git-commit`) are deliberately absent: they are vendored boilerplate whose example
     * links point at files only a consuming project would have (`.claude/typescript.md`,
     * `DOCX-JS.md`), so holding them to this repository's file tree would be wrong.
     * `AGENTS.md` and `.claude/skills/` are absent for a different reason: both are
     * gitignored, so they exist locally but not in a fresh checkout — asserting on them
     * would fail CI for files the repository does not carry.
     *
     * @var list<string>
     */
    private const array DOCUMENTATION = [
        'README.md',
        'CHANGELOG.md',
        'llms.md',
        'llms.txt',
        'UPGRADING.md',
        'CLAUDE.md',
        '.claude/architecture.md',
        '.claude/code-style.md',
        '.claude/doctrine.md',
        '.claude/testing.md',
        '.agents/skills/custom-handler-creator/SKILL.md',
        '.agents/skills/custom-handler-creator/references/examples.md',
        '.agents/skills/custom-handler-creator/references/handler-priority.md',
        '.agents/skills/custom-handler-creator/references/handler-template.md',
        '.agents/skills/custom-handler-creator/references/test-template.md',
        '.agents/skills/entity-translation-setup/SKILL.md',
        '.agents/skills/translation-debugger/SKILL.md',
        '.agents/skills/translation-debugger/references/diagnostics.md',
    ];

    /**
     * `UPGRADING.md` and `CHANGELOG.md` document classes this version no longer ships (that
     * is their whole job — telling a reader what was removed), so the "every class named
     * still exists" rule cannot apply to them.
     *
     * @var list<string>
     */
    private const array NOT_CLASS_CHECKED = [
        'UPGRADING.md',
        'CHANGELOG.md',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function documentationProvider(): iterable
    {
        foreach (self::DOCUMENTATION as $relativePath) {
            yield $relativePath => [$relativePath];
        }
    }

    #[DataProvider('documentationProvider')]
    public function testDocumentationFileExists(string $relativePath): void
    {
        self::assertFileExists(
            self::projectDir().'/'.$relativePath,
            \sprintf(
                'Documentation file "%s" is listed in %s::DOCUMENTATION but is not in the '
                .'repository. Remove it from the list, or restore the file.',
                $relativePath,
                self::class,
            ),
        );
    }

    /**
     * A relative link in the docs must point at a file that exists.
     */
    #[DataProvider('documentationProvider')]
    public function testEveryRelativeLinkTargetExists(string $relativePath): void
    {
        $absolutePath = self::projectDir().'/'.$relativePath;
        $directory    = \dirname($absolutePath);

        $missing = [];

        foreach (self::linkTargets($absolutePath) as $target) {
            [$file] = self::splitTarget($target);

            if ('' === $file) {
                continue;
            }

            if (!file_exists($directory.'/'.$file)) {
                $missing[] = $target;
            }
        }

        self::assertSame(
            [],
            $missing,
            \sprintf('"%s" links to files that do not exist.', $relativePath),
        );
    }

    /**
     * Every `#anchor` must resolve to a heading — in this file or in the file linked to.
     *
     * This is the check that would have caught `#shared-value-propagation-v41` surviving
     * the rename of the heading it pointed at.
     */
    #[DataProvider('documentationProvider')]
    public function testEveryAnchorResolves(string $relativePath): void
    {
        $absolutePath = self::projectDir().'/'.$relativePath;
        $directory    = \dirname($absolutePath);

        $unresolved = [];

        foreach (self::linkTargets($absolutePath) as $target) {
            [$file, $anchor] = self::splitTarget($target);

            if ('' === $anchor) {
                continue;
            }

            $targetPath = '' === $file ? $absolutePath : $directory.'/'.$file;

            if (!str_ends_with($targetPath, '.md') || !file_exists($targetPath)) {
                continue;
            }

            if (!\in_array($anchor, self::headingAnchors($targetPath), true)) {
                $unresolved[] = $target;
            }
        }

        self::assertSame(
            [],
            $unresolved,
            \sprintf('"%s" links to anchors that no heading produces.', $relativePath),
        );
    }

    /**
     * Every `Tmi\TranslationBundle\...` name the docs mention must still exist.
     *
     * Member-level references (`::method()`, `::CONSTANT`) are reduced to their class on
     * purpose: the failure this guards against is a class being deleted or moved while
     * the prose keeps naming it, which is what happened to `Psr6TranslationCache`.
     */
    #[DataProvider('documentationProvider')]
    public function testEveryReferencedBundleClassExists(string $relativePath): void
    {
        if (\in_array($relativePath, self::NOT_CLASS_CHECKED, true)) {
            self::markTestSkipped(\sprintf('"%s" documents removed classes by design.', $relativePath));
        }

        $contents = self::read(self::projectDir().'/'.$relativePath);

        $pattern = '/\bTmi\\\\TranslationBundle(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+/';
        preg_match_all($pattern, $contents, $matches);

        $unknown = [];

        foreach (array_unique($matches[0]) as $name) {
            if (!self::nameResolves($name)) {
                $unknown[] = $name;
            }
        }

        sort($unknown);

        self::assertSame(
            [],
            $unknown,
            \sprintf(
                '"%s" names classes or namespaces that do not exist. A rename or removal '
                .'must be carried into the documentation in the same commit.',
                $relativePath,
            ),
        );
    }

    /**
     * A name resolves if it is a loadable class-like symbol, or a real namespace —
     * `Tmi\TranslationBundle\Doctrine\Model` is a legitimate thing to mention in prose.
     */
    private static function nameResolves(string $name): bool
    {
        if (
            class_exists($name)
            || interface_exists($name)
            || trait_exists($name)
            || enum_exists($name)
        ) {
            return true;
        }

        $relative = substr($name, \strlen('Tmi\\TranslationBundle\\'));
        $path     = self::projectDir().'/src/'.str_replace('\\', '/', $relative);

        if (is_dir($path)) {
            return true;
        }

        // Test-only namespaces (fixtures, the test kernel) live under tests/, not src/.
        $testPath = self::projectDir().'/tests/'.str_replace('\\', '/', $relative);

        return is_dir($testPath) || is_file($testPath.'.php');
    }

    /**
     * Markdown link targets, ignoring fenced code blocks and external URLs.
     *
     * @return list<string>
     */
    private static function linkTargets(string $absolutePath): array
    {
        preg_match_all('/\]\(([^)\s]+)\)/', self::withoutCodeFences($absolutePath), $matches);

        $targets = [];

        foreach ($matches[1] as $target) {
            if (
                str_starts_with($target, 'http://')
                || str_starts_with($target, 'https://')
                || str_starts_with($target, 'mailto:')
            ) {
                continue;
            }

            $targets[] = $target;
        }

        return $targets;
    }

    /**
     * @return array{string, string} the file part and the anchor part, either may be empty
     */
    private static function splitTarget(string $target): array
    {
        $position = strpos($target, '#');

        if (false === $position) {
            return [$target, ''];
        }

        return [substr($target, 0, $position), substr($target, $position + 1)];
    }

    /**
     * GitHub's heading-to-anchor rules: drop inline formatting, lowercase, strip every
     * character that is not a letter, digit, underscore, hyphen or space, then turn
     * spaces into hyphens. Repeated headings get `-1`, `-2`, ... suffixes.
     *
     * An emoji heading such as `## ⚡ Performance` keeps the space the emoji leaves
     * behind and therefore anchors as `#-performance`, with a leading hyphen.
     *
     * @return list<string>
     */
    private static function headingAnchors(string $absolutePath): array
    {
        $anchors     = [];
        $seen        = [];
        $insideFence = false;

        foreach (explode("\n", self::read($absolutePath)) as $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $insideFence = !$insideFence;

                continue;
            }

            if ($insideFence) {
                continue;
            }

            if (1 !== preg_match('/^#{1,6}\s+(.*?)\s*$/', $line, $matched)) {
                continue;
            }

            $text = $matched[1];
            $text = (string) preg_replace('/`([^`]*)`/', '$1', $text);
            $text = (string) preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text);
            $text = mb_strtolower($text);
            $text = (string) preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $text);
            $text = str_replace(' ', '-', $text);

            $occurrence  = $seen[$text] ?? 0;
            $seen[$text] = $occurrence + 1;

            $anchors[] = 0 === $occurrence ? $text : $text.'-'.$occurrence;
        }

        return $anchors;
    }

    private static function withoutCodeFences(string $absolutePath): string
    {
        $kept        = [];
        $insideFence = false;

        foreach (explode("\n", self::read($absolutePath)) as $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $insideFence = !$insideFence;

                continue;
            }

            if (!$insideFence) {
                $kept[] = $line;
            }
        }

        return implode("\n", $kept);
    }

    private static function read(string $absolutePath): string
    {
        $contents = file_get_contents($absolutePath);

        self::assertIsString($contents, \sprintf('Could not read "%s".', $absolutePath));

        return $contents;
    }

    private static function projectDir(): string
    {
        return \dirname(__DIR__, 2);
    }
}
