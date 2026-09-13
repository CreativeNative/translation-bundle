# SPEC — Translation roots (bundle 5.1.0)

> **Status:** draft for review, 2026-09-13. Nothing built. Written for the session that implements
> 5.1.0; the application side (Terra Mia, stage 1 of its root-row plan) is referenced, not specified.
> Method: a readers' map of this repository at `v5.0.0` (six readers), three independent design
> drafts (Doctrine-native · explicit-contracts · migration-first), three judges; the migration-first
> draft won on adversarial robustness and absorbed the grafts named in § 11. The written spec was
> then attacked by four reviewers (mechanics · migration · silent failure · house style): 22 claims
> held, 15 corrections are folded in — the largest being that the shared-value write path must
> never touch a root reference (§ 3.2), that the adopter cross-check cannot live in an optional
> cache warmer (§ 3.4), and that groups must agree on root class and coherence key (§ 5.2).
>
> This file is deliberately **not** in `tests/Documentation/DocumentationReferencesTest.php`: it names
> classes that do not exist yet. Add it to that list — or fold it into `UPGRADING.md` — in the commit
> that makes them exist.

## 1. The problem in one paragraph

A translatable entity is one row per locale sharing a `tuuid` (`TranslatableTrait`). Data that
belongs to the *object* rather than to a *language* — children, foreign keys, shared scalars —
either hangs off one locale row (and is lost for the other two) or is keyed by the bare `tuuid`
string with no foreign key (and nothing cascades). The application fixes this with one **root row**
per object: a real entity with a primary key, owning the `tuuid`; every translation row carries a
FK to it and *copies* its `tuuid`. Doctrine then cascades natively. The bundle's job is small and
generic: (R1) know what a root is and keep the reference to it intact through `translate()`,
(R6) create roots for the rows that already exist, (R8) prove the invariant in CI, (R11) change
nothing for an application that declares no root. Plus one independent bug fix (§ 9).

Decided upstream and not reopened here: the root is **required in the translation row's
constructor**; shared scalars move to the root later via plain PHP delegation (no bundle mechanism);
the translation row keeps its own `tuuid` column as a copy; 5.1.0 is **additive on 5.0.0** — no
breaking change, no default that changes behaviour.

## 2. Vocabulary

| Term | Meaning |
|---|---|
| translation row | an entity implementing `TranslatableInterface` (one row per locale) |
| root | a **non-translatable** entity implementing `TranslationRootInterface`; owns the `tuuid` |
| root reference | the translation row's to-one association property whose declared type implements `TranslationRootInterface` (e.g. `Property::$listing`) |
| group | all translation rows sharing one `tuuid` |
| adopt | give a fresh root an **existing** group's `tuuid` (never mint) |
| mint | give a brand-new root a brand-new `tuuid` (the only place an identity is born) |

## 3. R1 — the root contract

### 3.1 What the bundle ships

```php
namespace Tmi\TranslationBundle\Doctrine\Model;

interface TranslationRootInterface
{
    public function hasTuuid(): bool;
    public function getTuuid(): Tuuid;          // throws if neither minted nor adopted — never lazy-mints
}

trait TranslationRootTrait                       // used by the app's STI root class (e.g. abstract Listing)
{
    #[ORM\Column(type: 'tuuid', length: 36, nullable: false, unique: true)]
    private Tuuid|null $tuuid = null;

    final public function hasTuuid(): bool;
    final public function mintTuuid(): void;      // throws LogicException if already set
    final public function adoptTuuid(Tuuid $tuuid): void; // first set: ok · same value: no-op · different: LogicException
    final public function getTuuid(): Tuuid;      // throws LogicException while null
}
```

Design points, each with its reason:

- **A root is not `TranslatableInterface`.** `TranslatableEventSubscriber::postLoad()` stamps a
  default locale on every `TranslatableInterface` it loads and `prePersist()` runs the orphan
  heuristic; both are wrong for a row that has no locale. The root therefore gets its own, smaller
  trait with **no listener attached**.
- **Not PHP `readonly`.** Doctrine's `ReflectionReadonlyProperty` tolerates a second write only by
  object identity (`!==`), never by value; a re-hydration that constructs a logically equal
  `Tuuid` instance would throw. `TranslatableTrait::setTuuid()` already avoids exactly this by
  comparing with `Tuuid::equals()` — the root trait mirrors that guard. (An integration test in
  § 8 pins `EntityManager::refresh()` on a root.)
- **Mint and adopt are two methods, never one.** `mintTuuid()` is for a brand-new object;
  `adoptTuuid()` is the migration path (§ 5). A root that is asked to do both, or either twice with
  a different value, throws — never silently.
- **`getTuuid()` on the root does not lazy-mint.** The root is the authority translation rows copy
  from; reading it before it has an identity is a programming error and surfaces loudly. This is
  the deliberate asymmetry with `TranslatableTrait::getTuuid()`, which keeps its lazy mint for
  classes that declare no root (R11).
- **The unique column lives in the trait**, as `TranslatableTrait` does for `tuuid`/`locale`: the
  property has to exist physically on one class, and the abstract STI root (`Listing`) declares it
  once for every leaf. (`unique: true` on a column follows the property wherever it is used; the
  separate Doctrine rule that *table-level* attributes such as `#[ORM\Index]` are ignored on an STI
  child is unrelated — the application has hit that one before, this is not it.)

### 3.2 How the translation row declares its root — structurally, with an optional marker

A property is a **root reference** when both hold: it carries `#[ORM\ManyToOne]` (only — a root
has two or more rows by construction, so `OneToOne`'s cardinality is never right, and its
idiomatic `unique` join column would refuse the second locale's INSERT), and its declared PHP type
implements `TranslationRootInterface`. Resolution rule: `is_a($type->getName(),
TranslationRootInterface::class, true)` on the `ReflectionNamedType`, **never** gated behind
`class_exists()` — that guard is `false` for a property typed to the bare interface, which is a
legitimate declaration.

```php
// src/Utils/AttributeHelper.php (new methods; existing methods keep their literal meaning)
public function isTranslationRootReference(\ReflectionProperty $property): bool;   // structural test above, cached per class::property
public function isEffectivelyShared(\ReflectionProperty $property): bool;          // isSharedAmongstTranslations() || isTranslationRootReference()
```

`#[Tmi\TranslationBundle\Doctrine\Attribute\TranslationRoot]` (TARGET_PROPERTY) exists as an
**optional** marker: documentation of intent, and a validation hook (§ 3.4). It is never the only
signal. Why structural first: the silent failure R1 exists to close is *a forgotten attribute*
(§ 3.3). Renaming the attribute does not close it; deriving sharedness from the referenced class's
own type does — a pre-5.1 class cannot implement a 5.1 interface by accident, so the structural
test is `false` for every existing property in every existing application (this is R11's proof).

`isSharedAmongstTranslations()` stays literal to its name. `isEffectivelyShared()` replaces it at
every consumer that can see a to-one association: `EntityTranslator::runHandlers()` (`:340`),
`AttributeHelper::collectValidationErrors()` (`:297`, the Shared+Empty conflict), and
`SharedValueSynchronizer::sharedProperties()` (`:310`) / `SharedDriftScanner` — so
`tmi:translation:sync-shared --check` sees the root reference as shared and compares it by
identity, as it does for every shared association today. `EmbeddedHandler`'s two call sites
(`:90`, `:173`) are excluded on purpose: they resolve properties *inside* an `#[Embedded]` value
object, and an embeddable cannot declare an association.

**A root reference is never written by the shared-value machinery.** `SharedValueSynchronizer`'s
write path (`reconcile($write = true)`, `:230-235` — `setValue()` by identity, no clone) and
`SharedValuePropagationListener` must treat every root-reference entry as **read-only**: a mismatch
between siblings is reported in the same bucket as a readonly drift (visible, never applied), and
the only thing that may resolve it is `tmi:translation:adopt-root`'s own classification (§ 5.2).
Without this rule the routine `sync-shared` write mode — whose `pickSource()` names the
default-locale row canonical — would silently re-point every other sibling's FK to that row's
root, collapse an *ambiguous* group into one root, and leave `adopt-root --check` clean afterwards:
a data-loss event with a green gate. The application already documents this command as dangerous
in write mode for a lower-stakes scalar; the bundle must not extend that hazard to identities.

### 3.3 The clone path — what guarantees the root survives `translate()`

Two verified facts compose:

1. PHP `clone` never calls a constructor and copies members. The two whole-entity clone sites are
   `TranslatableEntityHandler.php:92` and `DoctrineObjectHandler.php:86`; no `__clone()` exists in
   the bundle. The clone starts out holding the **same** root instance as its source.
2. When `DoctrineObjectHandler::translateProperties()` walks the clone and reaches the root
   reference, `runHandlers()` now resolves `isEffectivelyShared()` → `true` and dispatches with
   `setShared(true)`. The property is wrapped as a `PropertyTranslationContext` (the target is not
   translatable, `DoctrineObjectHandler.php:148-150`), no association handler's `supports()` claims
   it, so `DoctrineObjectHandler::translate()` runs and its existing `isShared()` branch
   (`:73-75`) returns the identical instance — not a clone.

Today, without the attribute, the same walk falls into `clone $data` on the root
(`DoctrineObjectHandler.php:86`) — under `copy_source: false` and a non-nullable property via the
"non-nullable object safety fallback" (`EntityTranslator.php:372-382`). That is the silent clone the
application's plan measured. After 5.1 the reference is *reaffirmed to identity on every walk*, not
merely left alone.

The constructor rule (§ 3.4) closes the `getTuuid()` lazy-mint window for `new`; the clone path
never had a window (no constructor runs). What neither can see — a raw SQL write, a hand-built row
that copied the wrong `tuuid`, a future explicit `clone $root` — is R8's job (§ 6).

### 3.4 What is validated, where, and the two-phase switch

All checks are **reflection-only** and run in the existing `AttributeValidationPass` (compile time,
no EntityManager); they iterate every concrete class the pass already discovers and every property
of its hierarchy. **New scope in the pass:** it must hold one dedup set keyed by
`declaringClass::$property` for the whole `foreach ($translatableClasses …)` run — today
`validateEntity()` builds a fresh `AttributeHelper` per concrete class (`:231-233`), so a property
declared on an abstract ancestor (`Property::$listing`) would otherwise be reported once per STI
leaf. The constructor check below is exempt from that dedup by design (it is keyed by concrete
class). Errors are aggregated into one `LogicException` as the pass does today.

For every root reference (structural test), always:

| Check | Error |
|---|---|
| at most one root reference per concrete class | `TranslationRootContractException::forAmbiguousRootProperty()` |
| target implements `TranslationRootInterface` **and not** `TranslatableInterface` | `::forTranslatableRootType()` |
| not `#[ORM\Id]` (`PrimaryKeyHandler` nulls every Id before sharing is considered) | `::forIdRootProperty()` |
| not `#[EmptyOnTranslate]` | `::forEmptyOnTranslate()` |
| `#[ORM\JoinColumn]` is not `unique: true` (one root, many rows) | `::forUniqueJoinColumn()` |
| `#[TranslationRoot]` present on a property that is **not** a root reference | `::forMarkerWithoutRoot()` |
| `#[SharedAmongstTranslations]` also present | **tolerated**, redundant — the application's transition carries it today; nothing to migrate |

For a root reference whose PHP type is **non-nullable** — this is the switch that says "the class
is done migrating":

| Check | Error |
|---|---|
| the concrete class's own constructor has a parameter without default, non-nullable, typed to `TranslationRootInterface` (or a subtype) | `::forMissingRootConstructorParameter()` |

A **nullable** root reference is phase 1 (§ 7): the FK column is nullable, `adopt-root` fills it,
no constructor rule yet. Making the property non-nullable is the phase-2 act that turns the
constructor rule on — one declaration change, no configuration key.

At container compile (`RootAdopterPass`, § 5.1 — **not** a cache warmer:
`TranslatableEntityValidationWarmer::isOptional()` is `true`, and Symfony's aggregate skips optional
warmers on the ordinary rebuild path, so a check placed there never runs after
`cache:clear` + first request): every class with a root reference has **exactly one** adopter
registered, and every adopter's tag names a class that has a root reference. The adopter's class
is read from the tag attribute `class` (no service is instantiated at compile time); the set of
root-declaring classes is what `AttributeValidationPass` just computed, exposed as a container
parameter for the later pass. The two declarations cannot drift apart silently.

### 3.5 Failure modes

- `LogicException` from `mintTuuid()` / `adoptTuuid()` / `getTuuid()` on the root — house style of
  `TranslatableTrait::setTuuid()`.
- `TranslationRootContractException extends \LogicException` with named static factories (style of
  `SharedValueConflictException` / `OrphanTranslationException`) and a `Solution:` line in every
  message (style of `AttributeConflictException`, `ClassLevelAttributeConflictException`,
  `ReadonlyPropertyException` — the classes `AttributeValidationPass` throws today). One class for
  all declaration errors above is a deliberate departure from those three one-class-per-error
  precedents: every check here concerns the same thing, the root reference, not a pair of
  conflicting attributes.
- `TranslatableTrait::setTuuid()` is **unmodified**: the translation row's constructor calls
  `$this->setTuuid($root->getTuuid())`; a later different value throws as it does today.

### 3.6 What R1 does not touch

`TranslatableTrait::getTuuid()`'s lazy mint, every existing handler, every handler priority, every
configuration key and default. No new configuration key is introduced anywhere in 5.1.

## 4. Application usage (reference shape, not part of the bundle)

```php
#[ORM\Entity, ORM\Table(name: 'listing'), ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'property_type', type: 'string')]
#[ORM\DiscriminatorMap(['vacation_rental' => VacationRentalListing::class, 'real_estate' => RealEstateListing::class])]
abstract class Listing implements TranslationRootInterface
{
    use TranslationRootTrait;
    // id, domainFamily, later owner/subscription and the children (stage 2/3b)
    /** @return static */ public static function mint(DomainFamily $f): static { $l = new static($f); $l->mintTuuid(); return $l; }
    /** @return static */ public static function adopt(DomainFamily $f, Tuuid $t): static { $l = new static($f); $l->adoptTuuid($t); return $l; }
}

abstract class Property implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\ManyToOne(targetEntity: Listing::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'listing_id', nullable: false)]
    #[TranslationRoot]                     // optional marker; the type already makes it a root reference
    protected Listing $listing;            // non-nullable => constructor rule is on (phase 2)

    public function __construct(DomainFamily $domainFamily, Listing $listing)
    {
        $this->listing = $listing;
        $this->setTuuid($listing->getTuuid());   // copy, never mint
        // ...
    }
}

// an importer: one identity per object, born on the root
$listing = RealEstateListing::mint($family);
$em->persist($listing);
foreach (['it_IT', 'de_DE', 'en_US'] as $locale) {
    $row = new RealEstate($family, $listing);
    $row->setLocale($locale);
    $em->persist($row);
}
```

The named constructors `mint()` / `adopt()` are application sugar; the bundle contract is the two
trait methods. The discriminator column name in the example is illustrative — keeping `listing`'s
and `property`'s independently written discriminators in agreement is an application concern
(§ 5.2's coherence key is where the application asserts it), not something the bundle checks. Decided on the application side (2026-09-13): `LongTermRental` is deleted (class and
discriminator entry), so the STI factory has two arms and no escape hatch; `translation_review`
gets a `listing_id` FK in the application's stage 2.

## 5. R6 — `tmi:translation:adopt-root`

### 5.1 Extension point (app supplies the STI decision)

```php
namespace Tmi\TranslationBundle\Doctrine\Root;

interface RootAdopterInterface
{
    /** The translatable hierarchy root this adopter serves, e.g. Property::class. */
    public function getTranslatableClass(): string;

    /** The root already attached to $row, or null. Must use isset() — the property may be uninitialized in phase 1. */
    public function getRoot(TranslatableInterface $row): TranslationRootInterface|null;

    /**
     * A NEW root for one group, WITHOUT a tuuid (the command adopts the group's). The concrete class
     * may depend on the rows (STI: property_type). Returning a root that already carries a tuuid is an
     * error the command refuses.
     * @param non-empty-list<TranslatableInterface> $group
     */
    public function createRootFor(array $group): TranslationRootInterface;

    /** Attach $root to $row without a constructor (reflection- or setter-based). */
    public function attach(TranslatableInterface $row, TranslationRootInterface $root): void;

    /**
     * The concrete root class this row implies (STI: from property_type). Every row of a group must
     * agree, and createRootFor()'s result must be an instance of it — otherwise the command refuses.
     * @return class-string<TranslationRootInterface>
     */
    public function rootClassFor(TranslatableInterface $row): string;

    /**
     * Everything that must agree inside one group beyond the root class (e.g. the domain family).
     * Rows with different keys are a MISMATCHED group (§ 5.2): never adopted, always a --check failure.
     * A pre-migration audit the application would otherwise script by hand becomes a standing guarantee.
     */
    public function coherenceKey(TranslatableInterface $row): string;
}
```

Registered with tag `tmi_translation.root_adopter` carrying the required attribute
`class` (the translatable hierarchy root, readable at compile time), collected by `RootAdopterPass`
into `RootAdopterRegistry` — the literal shape of `TranslationHandlerPass` (`findTaggedServiceIds()`
+ `addMethodCall()`; this bundle has never used `!tagged_iterator`). "Which classes declare a
root" is answered by the structural test (§ 3.2); the compile-time cross-check (§ 3.4) guarantees
exactly one adopter for each.

### 5.2 Algorithm

For each class with a root reference (`--entity` restricts, validated against Doctrine metadata as
the two existing commands do):

1. Stream `LocaleVariantFinder::streamGroupedByTuuid($class)` — the locale filter is suspended by the
   finder itself and restored in `finally`; a CLI has no firewall, so this is load-bearing regardless
   of the application's `disabled_firewalls`.
2. **Classify every group before writing anything.** Collect `getRoot()`, `rootClassFor()` and
   `coherenceKey()` over **every** row:
   - *mismatched* — rows disagree on `rootClassFor()` or `coherenceKey()` (a tuuid collision, a bad
     import, a self-registration bug). Checked first; never adopted.
   - *new* — no row has a root.
   - *complete* — every row has the same root, `root.tuuid == group.tuuid`, and `$root` is an
     instance of `rootClassFor()` → no-op (idempotency).
   - *partial* — some rows have the same root, others none.
   - *drift* — a row's `tuuid` differs from its root's `tuuid`, or the root's class is not the one
     `rootClassFor()` implies.
   - *ambiguous* — two or more distinct roots inside one group.
3. Write mode: if any group is *mismatched*, *drift* or *ambiguous*, print the full report and exit
   `FAILURE` **without writing** (the plan's rule: abort before the first UPDATE). Otherwise, in
   batches of `ADOPT_BATCH_SIZE` **groups** with flush + detach of settled entities only (the finder's
   lookahead row is already hydrated — never a blanket `clear()`):
   - *new*: `$root = $adopter->createRootFor($group)`; refuse if `$root->hasTuuid()`
     (`RootAdoptionException::forMintedRoot()`) or if `$root` is not an instance of
     `rootClassFor($group[0])` (`::forWrongRootClass()`); `$root->adoptTuuid($group[0]->getTuuid())`;
     persist; `attach()` every row.
   - *partial*: `attach()` the missing rows to the **existing** root (self-healing, keeps the command
     idempotent under an interrupted run); reported as an anomaly in `--check`.
   - **Group atomicity:** a group's `persist(root)` and every one of its `attach()` calls complete
     before the batch's `flush()`; the batch counter increments per completed group, never per row.
     An interruption therefore never commits a half-attached group.
4. Single-row groups need no special case (a group of one; nothing to reconcile).
5. `--dry-run`: steps 1–2 and the report, no `persist()`/`attach()`/flush. `--check`: implies dry run
   and fails on **any** *new*, *partial*, *drift* or *ambiguous* group, any root without rows (§ 6.1)
   and any counter above zero (§ 6.2).

Exit codes and output follow `SyncSharedTranslationsCommand` / `TranslationDoctorCommand`: one
`SymfonyStyle::table()` per anomaly class, one aggregate line, `SUCCESS` only when every count is 0.

## 6. R8 — `--check`, CI-grade

### 6.1 Counted by the bundle (needs no app code)

| # | Count | How |
|---|---|---|
| 1 | groups without a root · partial groups | § 5.2 classification |
| 2 | rows whose `tuuid` ≠ their root's `tuuid` · wrong root class · ambiguous groups | § 5.2 classification (string comparison — object identity is not observable across a cold process) |
| 2b | mismatched groups (rows disagree on root class or coherence key) | § 5.2 classification via the adopter's `rootClassFor()` / `coherenceKey()` |
| 3 | roots with zero rows | one DQL per root-declaring class: `SELECT COUNT(r) FROM <Root> r WHERE NOT EXISTS (SELECT 1 FROM <Translatable> t WHERE t.<rootProperty> = r)` |

Rule for #3: **`NOT EXISTS` or `LEFT JOIN … IS NULL`, never `NOT IN (subquery)`** — during the
migration window the subquery contains NULLs and `NOT IN` then evaluates to UNKNOWN for every row,
reporting zero orphans regardless of the truth. The query's cost is pinned by an exact-count
assertion in `QueryBudgetTest` (house rule: `assertSame`, never a ceiling).

### 6.2 Contributed by the application

```php
namespace Tmi\TranslationBundle\Doctrine\Root;

interface TuuidOrphanCounterInterface
{
    public function getName(): string;      // stable report label, e.g. "rental_photo.tuuid"
    public function countOrphans(): int;    // 0 = clean
}
```

Tag `tmi_translation.tuuid_orphan_counter`, collected by `TuuidOrphanCounterPass` into
`RootCheckAggregator`. The report **prints every registered counter, including those at 0**, so a
counter the application forgot to register is visibly absent rather than indistinguishable from
"clean". Any non-zero counter fails the check. **An exception from a counter — or from the
bundle's own roots-without-rows query — propagates:** the row shows `ERROR` with the exception
class, never a coerced `0`, and the command exits `FAILURE`. A broken counter must not look like a
verified-clean one.

Reference implementations for the application (illustrative):

- `rental_photo.tuuid` — rows whose `tuuid` matches no `property`, no `sh_guide` and no
  `sh_guide_category` row, each tested with `EXISTS` (the table is a store shared by three owners;
  measured 160 / 1310 / 25 / 0 — joining `property` would triple the count).
- `translation_review.tuuid` — rows whose `(entity_class, tuuid)` resolve to nothing (measured 44,
  all pre-dating the delete guard); retired once the application's stage 2 gives the table a FK.

## 7. R11 — opt-in, and the two-phase rollout

An application with no root reference is unchanged, provably:

1. No new configuration key.
2. The only runtime change is `isEffectivelyShared()` replacing `isSharedAmongstTranslations()` at
   three call sites; its second operand is `false` for every property whose type does not implement
   a 5.1 interface — i.e. for every property in every existing application.
3. The validation additions iterate root references and `#[TranslationRoot]` markers; zero of
   either → zero iterations.
4. `adopt-root` with no root-declaring class prints "No entity declares a translation root" and
   exits `SUCCESS`.
5. No handler, priority, service definition or listener changes.

The existing suite must pass unchanged with zero configuration change; that run is the R11
acceptance test, not a design argument.

**Two-phase rollout the command tolerates by design** (phase = nullability of the root reference):

| Phase | Application | Bundle behaviour |
|---|---|---|
| 1 | FK column nullable; `Listing|null $listing = null`; adopter registered; constructor unchanged | structural detection works (type implements the interface); no constructor rule; `adopt-root` fills the FK; `--check` to zero |
| 2 | column `NOT NULL`; `Listing $listing`; constructor requires the root; four raw `new` sites updated | constructor rule enforced at compile time; `--check` stays in CI |

A nullable root reference is still *shared* through `translate()` (a `null` stays `null`, an
instance is reaffirmed to identity).

## 8. Tests the release needs (each proven red first — house rule)

- `TranslationRootTraitTest`: mint twice throws; adopt a different value throws; adopt the same
  value is a no-op; `getTuuid()` before either throws.
- Integration: a persisted root survives `EntityManager::refresh()` and re-hydration in a fresh
  EntityManager without throwing (the readonly trap, pinned).
- `AttributeHelperTest`: `isTranslationRootReference()` true for a `ManyToOne` typed to a root
  class **and** to the bare interface, false for `Owner`-like targets, for `OneToOne`, for
  collections and for builtins; `isEffectivelyShared()` truth table.
- `EntityTranslatorTest` (fixture with a root): `translate()` returns a clone whose root reference
  is the **same instance** — once with, once without `#[TranslationRoot]`; and, on a fixture
  *without* a root type, the pre-5.1 behaviour is unchanged (R11 boundary).
- `SharedValueSynchronizerTest`: a root reference is not reported as drift by `sync-shared --check`
  when siblings share the root; when they do **not**, write mode and the flush-time propagation
  leave both FKs untouched and report the mismatch (negative proof: the write is refused).
- `AttributeValidationPassTest`: one fixture pair per row of the § 3.4 tables, including the
  nullable-vs-non-nullable switch and the STI dedupe (an abstract root declaring the property, two
  concrete leaves: one error per concrete class's constructor, none for the abstract parent).
- `TranslatableEntityValidationWarmerTest`: adopter missing for a root-declaring class; adopter
  registered for a class without a root reference.
- `AdoptRootCommandTest`: new group creates one root with the group's `tuuid`; second run is a
  no-op; single-row group; partial group self-heals in write mode and fails `--check`; drift,
  ambiguous and mismatched groups fail before any write; a factory returning a minted root or a
  root of the wrong class is refused; `--dry-run` writes nothing; roots-without-rows counted with
  `NOT EXISTS` under NULL FKs (the `NOT IN` trap as a negative fixture); every counter printed at
  0; a throwing counter yields `ERROR` and `FAILURE`; `--entity` given a concrete STI leaf still
  streams the hierarchy root; an interruption after `persist(root)` leaves no half-attached group.
- `QueryBudgetTest`: exact query count for the roots-without-rows check.
- § 9 tests.

## 9. Independent fix — `BidirectionalManyToOneHandler` and the shared back-reference

`ItineraryDay::$itinerary` is a `#[SharedAmongstTranslations]` `ManyToOne(inversedBy: 'days')`.
Translating the `Itinerary` walks `$days` (`BidirectionalOneToManyHandler`), which builds an
`EntityTranslationContext` per child with `setTranslatedParent($clone)` and the child's own
back-reference property; `runHandlers()` marks it shared; `BidirectionalManyToOneHandler::translate()`
throws unconditionally on `isShared()` (`:66-73`) before distinguishing the two shapes its own
docblock names (direct form vs. back-reference form).

Fix, in that one method: compute `$isBackReferenceForm = isset($associations[$propertyName]) &&
null !== $translatedParent` (the discriminator the non-shared repair at `:100-113` already uses);
throw only when shared **and not** back-reference; for the back-reference form clear
`isShared()` on the context before delegating to `TranslatableEntityHandler` (it throws on the same
flag and receives the same context object), then apply the existing repair
`setValue($translated, $propertyName, $translatedParent)` — the child now holds the parent's
**clone**. Recursion terminates through `EntityTranslator::processTranslation()`'s in-progress
guard — the `isInProgress()` short-circuit at `:253-256` (the `:263-284` block sets and, in
`finally`, clears that flag for the outer frame). `BidirectionalOneToManyHandler` needs no change.

Scope: `ItineraryDay::$destination` (unidirectional, to a translatable target) stays a throw — a
separate, catalogued design question. Known gap kept: the shared early return in `runHandlers()`
skips `PostTranslateEvent` and cache bookkeeping (`:339-348` vs `:433-451`); the repaired child now
also passes through it. Document in `.claude/doctrine.md`.

This **is** a behaviour change on an existing throw path. It is safe because no consumer relies
on that throw (the application's only instance has zero rows on every environment), and
`UPGRADING.md` says so plainly instead of calling 5.1 purely additive.

Tests: new fixtures `SharedBackReferenceParent` / `SharedBackReferenceChild` (the child's *to-one*
back-reference carries the attribute — no existing fixture has this shape);
`BidirectionalManyToOneHandlerTest` — back-reference form resolves to a **distinct** clone, direct
form still throws; integration `SharedBackReferenceBidirectionalTest`. Application side: drop
`'itinerary'` from `TranslatableManyToOneContractTest::ALLOWED`, keep `'destination'`.

## 10. R7 — what stays in the application

`collapse-shared` (re-hanging `vacation_rental_fee`, `_seasonal_rate`, `rental_photo`, bookings …
onto the root and deduplicating rows replicated across three locale copies) operates on tables that
are **not translatable entities** — bare `property_id` / `tuuid` columns the translation machinery
never touches — and the dedup key differs per table (`account_property` is a join table needing a
new column; `booking` needs no dedup at all). A generic bundle command would either cover only the
FK-repoint case or grow table-specific branches. It stays in the application as one command per
table, each with dry run and count, sequenced by the application's plan. The bundle offers what it
already has: `LocaleVariantFinder::streamGroupedByTuuid()` and the root's unique `tuuid` column for
`findOneBy(['tuuid' => …])`.

## 11. Grafts absorbed from the runner-up drafts (why the design looks as it does)

- Structural sharedness by type, with the attribute as optional marker (explicit-contracts draft);
  `isEffectivelyShared()` wrapper instead of widening a literally-named method; the synchronizer
  ships in the same release with a regression test.
- Reflection-only validation placed in the existing `AttributeValidationPass`, no new cache warmer
  for shape checks; constructor requirement as a compile-time check; STI dedupe by declaring class
  (Doctrine-native and explicit-contracts drafts).
- Roots-without-rows computed generically by the bundle (Doctrine-native draft) — the
  migration-first draft had asked every adopter to hand-write it.
- `NOT EXISTS` mandated, `NOT IN` banned by name (the judges found the trap in one draft's own code).
- Whole-group classification with named *ambiguous* and *drift* branches; factory result verified
  as tuuid-less; self-healing partial groups in write mode; write mode aborts before the first write
  on any drift/ambiguity.
- Every counter printed at zero.
- Not PHP `readonly` for the root's `tuuid` (verified Doctrine identity-comparison trap).
- Two-phase rollout stated as a table with the nullability switch.

## 12. Definition of done (this repository's gates)

- `composer check` green: PHPUnit with **100 % line coverage**, PHPStan level max with strict rules
  and no new `ignoreErrors`, php-cs-fixer, `tools/check-doc-claims.php` (README/llms test counts),
  `DocumentationReferencesTest` (every new FQCN mentioned in the docs must exist).
- CI matrix on both Symfony legs (`.github/workflows/php.yml`).
- `UPGRADING.md`: new "UPGRADE FROM 5.0 to 5.1" section in the file's own contents list (GitHub
  slug rules) — the root contract as addition; the § 9 behaviour change stated as such.
- `CHANGELOG.md` 5.1.0 entry (Added: attribute, interface, trait, command, two extension points;
  Fixed: the handler); `llms.md` sections for the new API; `.claude/doctrine.md` known-limitation
  note; `.claude/architecture.md` if the handler/dispatch description changes.
- Release tag `v5.1.0`; the application bumps `^5.1` in its stage 1.

Effort: measured against this repository's own throughput, not an abstract day count — 4.1.0
(listener, exception, synchronizer, scanner, streaming finder, two command flags) shipped in one
commit on 2026-09-05, and 5.0.0 (default flip plus the whole documentation-gate tooling,
1659(+)/437(−) over 29 files) shipped the same day in seven commits. 5.1.0 is not larger than
either: **one to two focused sessions**, in this order — § 9 fix with fixtures · trait + interface +
structural test + synchronizer read-only rule · compiler checks · command with both extension
points · docs and gates. What can make it slower is only the gates (100 % coverage, negative proof
per test), not the code. The application's stage 1 is separately scoped in its own plan.

## 13. Open questions (none blocking the build)

- Whether the `PostTranslateEvent`/cache gap on the shared early return (§ 9) should be closed in
  5.1 or 5.2 — no listener depends on it today.
- (Resolved, not open.) `--entity` accepts a concrete STI leaf as the two existing commands do, but
  the class handed to `streamGroupedByTuuid()` is **always** the adopter's hierarchy root: a group
  whose rows span two leaves must be seen whole, otherwise a leaf-scoped run mints a root for its
  own rows and turns the sibling into an *ambiguous* group on the next full run.
- Whether to keep `#[TranslationRoot]` at all once the structural test exists — proposal: keep it,
  it is the one line a reader of `Property` sees.
