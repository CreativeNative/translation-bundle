# Testing Guidelines

## Framework

PHPUnit 12.4.4+ with 100% code coverage target.

## Test Structure

```
tests/
├── IntegrationTestCase.php      # Base class for DB tests
├── TestKernel.php               # Minimal test kernel (SQLite); registers the two root adopters
├── Fixtures/Entity/             # Test entities (Root/: translation-root fixtures, both rollout phases)
├── Fixtures/Validation/         # Reflection-only fixtures for the compiler passes (Root/: one dir per contract error)
├── Translation/Handlers/        # Handler unit tests
├── Doctrine/                    # ORM integration tests (Root/: adopter registry, check aggregator)
├── DependencyInjection/         # Container tests
├── Command/                     # Console command tests (CommandTester)
├── Documentation/               # Doc link/anchor/class-name gate
├── Performance/                 # Query-budget tests (QueryBudgetTest.php)
└── Support/                     # Test-only infrastructure (QueryCounter.php, Root/: test adopters)
```

## Base Classes

### IntegrationTestCase

For tests requiring database access:

```php
class MyTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Test setup
    }
}
```

Uses in-memory SQLite database via TestKernel.

## Running Tests

```bash
docker exec php composer test                          # Full suite with coverage
docker exec php vendor/bin/phpunit                     # Without coverage
docker exec php vendor/bin/phpunit --filter MethodName # Single test
```

Targeted runs need `--no-coverage` (phpunit.xml configures coverage reports; without
`XDEBUG_MODE=coverage` PHPUnit stops with "No tests executed"). A test that fails inside the
first kernel boot (a compiler pass throwing, a fixture without its adopter) leaves Symfony's
container `flock()` held by the PHPUnit process and every later boot in that run blocks
forever: kill the stray `php vendor/bin/phpunit` process and remove `var/cache/test/*.lock`
before running again.

## Test Fixtures

Test entities live in `tests/Fixtures/Entity/`. Create specific fixtures for each relationship type being tested.

Two rules learned the hard way:

- Everything under `tests/Fixtures/Entity/` is mapped by `TestKernel` and validated by
  `AttributeValidationPass` at kernel compile — a fixture that breaks a compile-time contract
  (a root reference without an adopter, say) breaks **every** integration test. Reflection-only
  fixtures for the passes go under `tests/Fixtures/Validation/`, which is not mapped.
- Do not name a mapped fixture after a generic word an existing test asserts on in console
  output (`Property` collided with the `Property | Tuuids | Rows` table header in
  `SyncSharedTranslationsCommandTest`; the root fixture is `Estate`).

`tests/Fixtures/Entity/Bughunt/` holds the fixtures the 5.2 bug hunt produced, each one the
smallest shape that reproduced a bug proven red on 5.1 (`ValueObjectAndSelfReferenceTest`):
`Stamp` (value objects under `#[EmptyOnTranslate]`), `SeededStamp` (value objects under
`copy_source: false`), `Node` (self-referential bidirectional ManyToOne tree), `Link`
(self-referential bidirectional OneToOne chain), `RowRoot`/`RootedRow` (the SPEC § 4 root
reference with `inversedBy`; its adopter is `tests/Support/Root/RootedRowAdopter`).

Data providers run before coverage collection starts, and `#[CoversClass]` restricts what a
test is credited for: an exception class exercised only through other tests' `CoversClass`
scopes reports 0 % — yield closures from the provider and call them inside the test.

## Strict Mode

PHPUnit runs in strict mode — `failOnWarning`, `failOnNotice`, and `failOnRisky` are all enabled. No warnings, notices, or risky tests allowed.

## Assertions & Mocks

- Use `self::assertXxx()`, not `$this->assertXxx()`
- Use `createStub()` for objects without expectations
- Add `#[AllowMockObjectsWithoutExpectations]` when a mock has no expectations and `createStub()` is not suitable

## Coverage Requirements

- **Target**: 100% line coverage (enforced in CI)
- Coverage report: `var/clover.xml`
- `#[CoversClass]` (or `#[CoversTrait]`) on every test with one system under test; it restricts
  what the test is credited for, so a helper the test exercises incidentally needs its own test.
  End-to-end scenario tests (`tests/*Test.php`, `QueryBudgetTest`, `DocumentationReferencesTest`,
  `TranslateEventSeedingIntegrationTest`) carry none on purpose — never `#[CoversNothing]`,
  which would drop their credit entirely.

## Writing Tests

### Handler Tests

Test each handler's `supports()` and `translate()` — the latter for its `isShared()`, `isEmpty()`
and ordinary (neither) branches. Tests extend `UnitTestCase` and build a typed context with its
`propertyContext()` (non-entity value: scalar, embeddable, `Collection`) or `entityContext()`
(`TranslatableInterface` entity) helper, matching whichever shape the handler under test expects:

```php
public function testSupportsReturnsTrueForValidValue(): void
{
    $handler = new ScalarHandler();
    $context = $this->propertyContext('some value');

    self::assertTrue($handler->supports($context));
}

public function testTranslateReturnsNullWhenEmpty(): void
{
    $handler = new ScalarHandler();
    $context = $this->propertyContext('some value')->setEmpty(true);

    self::assertNull($handler->translate($context));
}
```

### Integration Tests

Test full translation flow through EntityTranslator:

```php
public function testTranslateCreatesNewEntityWithCorrectLocale(): void
{
    $entity = $this->createTestEntity('en_US');
    $translated = $this->translator->translate($entity, 'de_DE');

    self::assertSame('de_DE', $translated->getLocale());
    self::assertSame($entity->getTuuid(), $translated->getTuuid());
}
```

`IntegrationTestCase` registers nothing by hand: `TranslatableEventSubscriber` and every
listener reach Doctrine through the bundle's own `services.yaml` (autoconfigured
`#[AsDoctrineListener]` plus explicit tags), and `TranslatableEventSubscriberRegistrationTest`
asserts exactly one container instance serves its three events. A test that needs a
differently configured subscriber (a spy logger, `strictOrphanCheck: true`) builds its own and
adds it with `addEventListener([Events::prePersist, Events::postLoad, Events::onFlush], $subscriber)`
— the hooks are idempotent, a second instance is harmless.

Command tests execute with `['interactive' => false]` (the `run_()` helpers do): the
whole-table write of `sync-shared` asks `Continue?` on an interactive input and a
`CommandTester` without `setInputs()` would answer the default, no. Tests of the prompt
itself call `setInputs(['yes'])`/`['no']` on a tester built by `tester()`.

A streaming command must leave nothing managed behind: `AdoptRootCommandTest` asserts
`$em->getUnitOfWork()->size() === 0` after a run, rows and roots included — the proof that
`GroupBatch` detached every settled entity.

### Query-Budget Tests

`tests/Performance/QueryBudgetTest.php` (`IntegrationTestCase`) asserts an exact database
round-trip count for a documented operation — `assertSame`, never a ceiling (`assertLessThan`
lets a budget silently regress; `assertSame` fails the moment it does):

```php
public function testTranslateEntityWithExistingVariantIsOneQueryAndNoInserts(): void
{
    // ... seed a de_DE variant, reset the counter ...
    $this->translator->translate($product, 'de_DE');

    self::assertSame(1, $this->queryCounter->count());
}
```

`tests/Support/QueryCounter.php` is a PSR-3 logger wired behind DBAL's own logging middleware
in `TestKernel` (test env only); it counts one message per executed statement and
deliberately excludes transaction-control messages (`beginTransaction`/`commit`/`rollBack`),
so `flush()`'s implicit transaction never inflates a budget. Every number in README.md §
Performance and llms.md § Performance traces back to one of these assertions — changing a
number in either doc without a corresponding test change is a discrepancy the reviewer should
flag.

### Documentation Tests

`tests/Documentation/DocumentationReferencesTest.php` holds the prose to the same standard as
the code. For every bundle-owned document it asserts that relative links point at files that
exist, that every `#anchor` resolves to a heading the file actually produces (GitHub's slug
rules, duplicate suffixes included), and that every `Tmi\TranslationBundle\...` name still
resolves to a class, trait, enum or real namespace.

It exists because the docs were the one surface with no gate: a reference to the deleted
`Psr6TranslationCache` and a dead `#shared-value-propagation-v41` anchor both survived a
fully green build. The same fact is stated in six or seven files, so a rename is always a
multi-file edit — this is what notices the one that was missed.

Two deliberate exclusions, both documented in the test: `UPGRADING.md` and `CHANGELOG.md` are
exempt from the class check (documenting removed classes is their job), and the vendored
tooling skills (`skill-creator`, `agent-md-refactor`, `php-pro`, `git-commit`) are out of
scope, since their example links point at files only a consuming project would have.

### Documented Suite Counts

`tools/check-doc-claims.php` compares the "**N tests, M assertions**" claim in README.md § Why
This Bundle and llms.md § Overview against PHPUnit's JUnit log, and fails the build when they
diverge. Wired into `composer check` (after `@test`) and into CI next to the coverage
threshold. It reads `var/junit.xml` rather than scraping console output — no ANSI codes, and
no shell pipe that could mask PHPUnit's exit code.

**It fails on every pull request that adds a test. That is the point**, not a nuisance: the
counts are the load-bearing half of the verified-quality claim, and a claim nobody is forced
to update is a claim that goes stale. Run `composer test` then `composer doc-claims` locally;
the error message prints the exact replacement string.

### Negative-Proof Discipline

Every bug-fix commit in this codebase carries a test that is demonstrably **red against the
old code**, not merely green after the fix — proven either by `git stash`-ing the `src/`
change and re-running the new test (documented in the commit body), or by the test itself
asserting the specific wrong behaviour the old code produced (e.g. a second row with a new
id, not a generic exception) rather than a vague "it doesn't crash" check. This is a
reviewable claim, not a convention taken on faith — a reviewer checking a bug-fix PR asks for
the red run, the same way `composer check` is asked for the green one.

## CI Pipeline

Tests run in GitHub Actions with:
1. PHPUnit with xdebug coverage
2. Coverage upload to Codecov
3. Must pass for merge
