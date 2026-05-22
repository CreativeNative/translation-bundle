# Doctrine Integration

## Making Entities Translatable

Implement `TranslatableInterface` and use `TranslatableTrait`:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class Product implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private string $name;
}
```

## Trait Provides

The `TranslatableTrait` adds:

| Field | Type | Purpose |
|-------|------|---------|
| `tuuid` | `Tuuid` | Groups translations (auto-generated) |
| `locale` | `string` | Entity's language |
| `translations` | `array` | JSON metadata |

## Custom Doctrine Type

`TuuidType` handles Tuuid ↔ database conversion:

```php
// In entity
#[ORM\Column(type: 'tuuid')]
private ?Tuuid $tuuid = null;
```

Registered automatically by the bundle.

## Locale Filter

`LocaleFilter` automatically filters queries by current locale. Configured via:

```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    locales: ['en_US', 'de_DE', 'it_IT']
    default_locale: 'en_US'
    disabled_firewalls: ['api']  # Disable for specific firewalls
```

### Disabling Filter Temporarily

```php
$this->entityManager->getFilters()->disable('locale_filter');
// ... query all locales
$this->entityManager->getFilters()->enable('locale_filter');
```

## Event Subscriber

`TranslatableEventSubscriber` handles Doctrine lifecycle events:

- Sets `tuuid` on persist if not set
- Validates locale is configured
- Maintains translation metadata
- Flags **orphaned translations** — an entity persisted in a non-default locale without a
  shared `Tuuid`. Governed by `strict_orphan_check` (`true` throws `OrphanTranslationException`,
  `false` logs a warning, `null` = auto: throws when `kernel.debug` is on).

## Composite Index on `(tuuid, locale)`

`TranslatableIndexListener` listens on `loadClassMetadata` and injects a composite
`(tuuid, locale)` index into every `TranslatableInterface` entity — a trait cannot declare a
class-level `#[ORM\Index]`, so without it the `tuuid` column ships unindexed. The index name
is `<table>_tuuid_locale_idx` (table-prefixed for SQLite's database-wide uniqueness).

```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    unique_locale_variants: true   # promote the index to a UNIQUE constraint
```

Enable `unique_locale_variants` only once `tmi:translation:doctor` confirms no duplicate
`(tuuid, locale)` rows exist — otherwise the schema migration fails.

## Diagnostic Commands

- `php bin/console tmi:translation:doctor` — scans translatable tables for broken linkage
  (standalone / incomplete translations, duplicate `(tuuid, locale)` pairs); exits non-zero.
- `php bin/console tmi:translation:sync-shared` — propagates `#[SharedAmongstTranslations]`
  column values from the default-locale row to all sibling locale variants (`--dry-run`,
  `--entity=<FQCN>`).

## Relationship Handling

| Relationship | Handler | Notes |
|--------------|---------|-------|
| ManyToOne | BidirectionalManyToOneHandler | Translates to matching locale |
| OneToMany | BidirectionalOneToManyHandler | Clones collection with translations |
| OneToOne | BidirectionalOneToOneHandler | Translates owned side |
| ManyToMany | BidirectionalManyToManyHandler | Clones with translations |
| Embedded | EmbeddedHandler | Deep clones embedded objects |

## Known Limitations

1. **ManyToMany + SharedAmongstTranslations**: Not yet supported
2. **Unique constraints**: May fail without locale-based uniqueness in schema
3. **Non-nullable scalars + EmptyOnTranslate**: Requires nullable field

See GitHub Issues for workarounds.
