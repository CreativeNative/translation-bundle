# Codebase Concerns

**Analysis Date:** 2026-02-02

## Tech Debt

**Translation Cache Service Missing:**
- Issue: The `EntityTranslator` class contains an inline translation cache implementation that grows unbounded during request lifecycle. No external cache abstraction exists. Referenced in GitHub Issue #3.
- Files: `src/Translation/EntityTranslator.php` (lines 30-37, 88-98)
- Impact: Long-running processes (CLI commands, queue workers) will accumulate memory as translations are cached indefinitely. No TTL or size limits. For high-volume translation operations, this can cause memory exhaustion.
- Fix approach: Introduce a `TranslationCacheInterface` backed by Symfony Cache component, allowing PSR-6 cache backends (Redis, Memcached, APCu). Deprecate inline cache in favor of external caching.

**EmptyOnTranslate Attribute Limited Scope:**
- Issue: The `#[EmptyOnTranslate]` attribute only works with nullable fields or collections. Cannot empty scalar non-nullable fields without throwing `\LogicException`. Referenced in GitHub Issue #2.
- Files: `src/Translation/EntityTranslator.php` (lines 21, 144-150), `src/Doctrine/Model/TranslatableTrait.php`
- Impact: Developers cannot use `#[EmptyOnTranslate]` on required string/int fields without making them nullable first. Forces schema changes or workarounds (e.g., empty strings instead of null).
- Fix approach: Introduce default values configuration per field or property-level hints for scalar types. Consider allowing default string/int values when emptying scalars.

**Embedded Entity Mixed Shared/Empty Logic:**
- Issue: Handling of `#[SharedAmongstTranslations]` and `#[EmptyOnTranslate]` attributes on embedded objects requires complex decision logic. Currently unresolved: behavior when embeddable contains both shared AND empty properties.
- Files: `src/Translation/EntityTranslator.php` (lines 152-169), `src/Translation/Handlers/EmbeddedHandler.php` (lines 108-139)
- Impact: Embedded objects with mixed attribute configurations may produce unexpected results. GitHub Issue #6 explicitly tracks this as "Support Embedded Entities with Both Shared and EmptyOnTranslate Properties".
- Fix approach: Clarify priority rules in documentation and implement comprehensive test suite for mixed scenarios. Consider simplifying logic or disallowing combinations that are ambiguous.

## Known Bugs

**Tuuid Auto-Generation Side Effect:**
- Symptoms: Calling `getTuuid()` on an entity that hasn't persisted will auto-generate a new Tuuid if none exists. This is problematic in testing and non-persistent scenarios where Tuuid identity should be stable until explicit persistence.
- Files: `src/Doctrine/Model/TranslatableTrait.php` (lines 57-64), `src/Doctrine/Type/TuuidType.php` (lines 17-21, 36-37)
- Trigger: Accessing `getTuuid()` on unpersisted entity or calling `convertToPHPValue(null)` in DBAL type
- Workaround: Explicitly call `generateTuuid()` before accessing `getTuuid()`, or initialize Tuuid in entity constructor.

**LocaleFilter SQL Injection Risk:**
- Symptoms: The `LocaleFilter::addFilterConstraint()` uses `sprintf()` to directly interpolate the locale parameter into SQL. If locale parameter contains special characters or is not properly parameterized at Doctrine filter level, could produce malformed SQL.
- Files: `src/Doctrine/Filter/LocaleFilter.php` (lines 28-41)
- Trigger: Setting locale via `setLocale()` with untrusted input
- Workaround: Locale should always be validated against configured locales list in `LocaleFilterConfigurator` before reaching filter. Currently relies on implicit Symfony request locale handling.

## Security Considerations

**Tuuid Generation in Doctrine Type Converter:**
- Risk: `TuuidType::convertToPHPValue(null)` and `convertToDatabaseValue(null)` both auto-generate new Tuuids. This means unset or null Tuuid columns silently become new entities with different identities. Could cause data loss or identity confusion in nullable Tuuid scenarios.
- Files: `src/Doctrine/Type/TuuidType.php` (lines 17-21, 34-37)
- Current mitigation: `TranslatableTrait` trait field is defined as nullable with initializer, and `prePersist` hook generates Tuuid. Doctrine DBAL type acts as fallback.
- Recommendations: Document that Tuuid columns should NEVER be nullable in production. Add explicit null checks before type conversion. Consider throwing exception instead of auto-generating on null input.

**Locale Filter Not Applied to Admin/Edit Contexts:**
- Risk: The `LocaleFilterConfigurator` respects `disabled_firewalls` configuration, allowing unconfigured firewalls to bypass locale filtering entirely. Admin interfaces may inadvertently expose translated content from wrong locales.
- Files: `src/EventSubscriber/LocaleFilterConfigurator.php` (lines 62-74)
- Current mitigation: Configuration option allows explicit disabling per firewall. Requires deliberate opt-in.
- Recommendations: Document security implications of disabling filter. Add auditing or logging when filter is disabled. Consider default-deny approach (filter on unless explicitly disabled).

## Performance Bottlenecks

**Reflection-Heavy Property Discovery:**
- Problem: Every translation operation iterates over entity properties using Reflection to discover attributes (`#[SharedAmongstTranslations]`, `#[EmptyOnTranslate]`, Doctrine mappings). No caching of reflection metadata.
- Files: `src/Translation/Handlers/DoctrineObjectHandler.php` (lines 96-97), `src/Translation/Handlers/BidirectionalManyToManyHandler.php` (lines 159-174), `src/Translation/Handlers/EmbeddedHandler.php` (lines 78-103)
- Cause: Reflection calls are O(n) per property per translation. With many attributes, multiple reflection queries create redundant work.
- Improvement path: Cache reflection metadata per class using Symfony Cache or static arrays. Pre-compute attribute maps on bundle initialization.

**Warmup Query For Each Unique Tuuid/Locale Pair:**
- Problem: `EntityTranslator::warmupTranslations()` issues one query per entity class (good) but requires parsing the returned translations into cache. For bulk operations (e.g., translating 1000 entities to 5 locales), this creates 5 separate queries.
- Files: `src/Translation/EntityTranslator.php` (lines 232-271)
- Cause: Warmup is called reactively during translation, not proactively. No batching mechanism.
- Improvement path: Introduce eager-loading strategy for translation operations on collections. Allow pre-warming cache with explicit batch queries before translation pipeline.

**Multiple Property Accessor Fallbacks:**
- Problem: `DoctrineObjectHandler::translateProperties()` tries PropertyAccessor first, catches `NoSuchPropertyException`, then falls back to Reflection. Adds overhead on every property access.
- Files: `src/Translation/Handlers/DoctrineObjectHandler.php` (lines 94-107, 128-133)
- Cause: Defensive programming for edge cases where PropertyAccessor can't access fields (e.g., private properties without getters). Fallback is necessary but checked on every iteration.
- Improvement path: Cache which access method works per class upfront. Skip accessor check once determined.

## Fragile Areas

**Bidirectional ManyToMany Relationship Mutation:**
- Files: `src/Translation/Handlers/BidirectionalManyToManyHandler.php` (lines 145-154)
- Why fragile: Handler directly mutates the inverse relationship using reflection (`ReflectionProperty::setValue()`) without validating that the relationship will remain consistent. Clears the inverse side to empty collection, then manually adds translated parent.
- Safe modification: Add integration tests that verify cascading deletions and orphan removal work correctly. Document that ManyToMany translations may have side effects on bidirectional relationships.
- Test coverage: `tests/Translation/Handlers/` has tests but may not cover all edge cases with circular references.

**Tuuid Immutability Constraints:**
- Files: `src/Doctrine/Model/TranslatableTrait.php` (lines 34-52)
- Why fragile: `setTuuid()` allows initial assignment and Doctrine rehydration equality checks via `equals()` method, but throws on any other reassignment. Logic depends on `Tuuid::equals()` correctness.
- Safe modification: Add comprehensive unit tests for Tuuid reassignment scenarios. Document that Tuuid should be treated as immutable in entity lifecycle.
- Test coverage: Basic tests exist but edge cases with cloning and Doctrine proxies need verification.

**Embedded Object Cloning Without Deep Clone Strategy:**
- Files: `src/Translation/Handlers/EmbeddedHandler.php` (lines 108-111)
- Why fragile: Uses shallow `clone` for embedded objects. If embedded objects contain object properties, references are shared between translations.
- Safe modification: Document that embedded objects must be value objects (no nested objects). Add validation or explicit deep-clone utility.
- Test coverage: No tests for nested object graphs within embeddings.

## Scaling Limits

**Translation Cache Memory Unbounded:**
- Current capacity: In-memory array with no size limits
- Limit: Process memory exhaustion after translating thousands of unique entities to multiple locales
- Scaling path: Replace inline cache with external PSR-6 cache backend (Redis, Memcached). Implement automatic cache invalidation on entity update.

**Locale Filter Performance on Large Tables:**
- Current capacity: Single SQL filter applies to all Translatable entities
- Limit: Table with millions of rows will scan entire table, then filter by locale. No indexes assumed.
- Scaling path: Ensure `(locale)` or `(locale, tuuid)` composite indexes exist on translatable tables. Consider partitioning by locale at database level.

**Reflection Cache Missing:**
- Current capacity: Every translation triggers full reflection on entity class
- Limit: High-volume translation (10,000+ entities per request) causes CPU spikes from repeated reflection
- Scaling path: Pre-compute and cache reflection metadata during service initialization or first access.

## Dependencies at Risk

**Symfony UID Component (symfony/uid):**
- Risk: Used for UUID generation in `Tuuid::generate()`. If Symfony UID has breaking changes in major versions, Tuuid generation breaks.
- Impact: All new translatable entities cannot be generated without Uuid::v7()
- Migration plan: Abstract UUID generation behind a `UuidGeneratorInterface`. Allow pluggable generators (e.g., ulid, uuid4, uuid6).

**Doctrine ORM 3.5+ Requirement:**
- Risk: Bundle targets Doctrine 3.5.7+. Breaking changes in Doctrine 4.0+ would require major refactor.
- Impact: Custom type handling, filter logic, metadata factory calls may fail
- Migration plan: Test compatibility with Doctrine 4.0 early. Abstract Doctrine-specific code behind interfaces.

**PHPUnit 12.4+ Requirement:**
- Risk: Testing framework tied to specific major version. Newer versions may change assertion APIs or PHPUnit Bridge behavior.
- Impact: Tests cannot upgrade PHPUnit independently
- Migration plan: Current constraint is reasonable for PHP 8.4 era. Plan deprecation once PHP 8.5+ ecosystem stabilizes.

## Missing Critical Features

**No Handler for Unique Fields:**
- Problem: Translating entities with `UNIQUE` constraints on translatable columns (e.g., slug) fails with integrity violations. README explicitly mentions this as a limitation.
- Blocks: Cannot translate products with unique SKUs or articles with unique slugs without schema changes
- Impact: Users must implement workarounds (composite unique keys with locale, or external slug management)
- Priority: High - mentioned in README limitations section

**ManyToMany SharedAmongstTranslations Not Supported:**
- Problem: Applying `#[SharedAmongstTranslations]` to ManyToMany relationships throws `\RuntimeException`.
- Blocks: Cannot share ManyToMany collections across translations (e.g., shared category tags across language versions)
- Impact: Developers must use workarounds or restructure data model
- Priority: High - documented limitation

**No Soft Delete Support:**
- Problem: No handlers for soft-delete attributes or strategies. Cascading deletes may purge translation history.
- Blocks: Cannot recover translations after soft deletion
- Priority: Medium - depends on application domain

## Test Coverage Gaps

**Embedded Objects with Nested References:**
- What's not tested: Embedded objects containing other embedded objects or entity references
- Files: `src/Translation/Handlers/EmbeddedHandler.php`, `tests/Translation/Handlers/EmbeddedHandlerTest.php`
- Risk: Nested cloning behavior is untested; may leave unintended references
- Priority: High

**Locale Filter with Null/Empty Locales:**
- What's not tested: Behavior when request locale is null or empty string
- Files: `src/Doctrine/Filter/LocaleFilter.php`, `src/EventSubscriber/LocaleFilterConfigurator.php`
- Risk: Filter may not apply or produce empty results silently
- Priority: Medium

**Circular Reference Handling in Translation:**
- What's not tested: Entity A translates to B, B references A. Does cycle detection work?
- Files: `src/Translation/EntityTranslator.php` (lines 100-103)
- Risk: Infinite recursion possible if cycle detection fails
- Priority: Medium

**PHP 8.4 First-Class Callables (array_any):**
- What's not tested: `array_any()` with first-class callable in `EmbeddedHandler` (PHP 8.4 feature)
- Files: `src/Translation/Handlers/EmbeddedHandler.php` (line 137)
- Risk: Requires PHP 8.4+; behavior may differ on older PHP or with opcache
- Priority: Low (intentional PHP 8.4 requirement)

---

*Concerns audit: 2026-02-02*
