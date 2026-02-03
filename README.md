# TMI Translation Bundle - Doctrine Entity Translations for Symfony

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-8892BF.svg)](https://php.net/)
[![Symfony 7.3+](https://img.shields.io/badge/Symfony-7.3%2B-000000.svg)](https://symfony.com/)
[![Doctrine ORM 3.5+](https://img.shields.io/badge/Doctrine-ORM%203.5%2B-FF6D00.svg)](https://www.doctrine-project.org/)
[![codecov](https://codecov.io/github/CreativeNative/translation-bundle/graph/badge.svg?token=D2PXJL5T2Y)](https://codecov.io/github/CreativeNative/translation-bundle)

A modern, high-performance translation bundle for Symfony that stores entity translations **in the same table** as the source entity - no expensive joins, no complex relations.

## 🚀 Why This Bundle?

This bundle solves: **Symfony Doctrine translation**, **entity localization**, **multilingual entities**, **Doctrine translatable**, **Symfony translation bundle**, **database translations**, **entity translations**

### ❌ Traditional Translation Problems:
- **Multiple tables** with complex joins
- **Performance overhead** on translated entities
- **Complex queries** for simple translations
- **Schema changes** required for each new translation

### ✅ Our Solution:
- **Single table** for all translations
- **No performance penalty** - same query speed as non-translated entities
- **Simple implementation** - just add interface and trait
- **Zero schema changes** when adding new languages

## 🎯 Key Features

- **🏷️ Same-table storage** - Translations stored with source entity (no joins needed)
- **⚡ Blazing fast** - No performance overhead on translated entities
- **🔄 Auto-population** - Automatic relation translation handling
- **🎯 Inherited entity support** - Works with complex entity hierarchies
- **🛡️ Type-safe** - Full PHP 8.4 type declarations throughout
- **🧪 100% tested** - Comprehensive test suite with full coverage
- **🤖 AI-ready** - Includes [AI skills](#-ai-assisted-development) for Claude Code and other assistants

## 🏗️ About This Version

This is a **complete refactoring** based on PHP 8.4, Symfony 7.3, and Doctrine ORM 3.5 of the fork from [umanit/translation-bundle](https://github.com/umanit/translation-bundle), implemented with modern development practices and featuring **100% code coverage** with comprehensive test suites.

## ⚠️ Limitations

* **ManyToMany associations** are currently not supported. This includes usage with the `SharedAmongstTranslations` attribute.
* There is currently **no handler for unique fields** (e.g. `uuid`, `slug`).  When translating entities with unique columns, the translation process may fail with a unique constraint violation.
  See the [Quick Fix for unique fields](#quick-fix-for-unique-fields) section below.
* Requires **PHP 8.4+**, **Symfony 7.3+** and **Doctrine ORM 3.5+** (see legacy versions for older support)

## 📦 Installation

```
composer require tmi/translation-bundle
```

Register the bundle to your `config/bundles.php`.

```php
return [
// ...
Tmi\TranslationBundle\TmiTranslationBundle::class => ['all' => true],
];
```

## ⚙️ Configuration
Configure your available locales and, optionally, the default one and disabled firewalls. That's it!
```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    locales: ['en_US', 'de_DE', 'it_IT'] # Required: available locales
    # default_locale: 'en_US'            # Optional: uses kernel.default_locale if not set
    # disabled_firewalls: ['main']       # Optional: disable filter for specific firewalls
```

### Doctrine DBAL Custom Type - TuuidType

To use the `TuuidType` in your Symfony project, you must register it in your Doctrine configuration:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        types:
            tuuid: Tmi\TranslationBundle\Doctrine\Type\TuuidType
```
This ensures that Doctrine recognizes the tuuid type and avoids errors like:
```pgsql
Unknown column type "tuuid" requested. Any Doctrine type that you use has to be registered with \Doctrine\DBAL\Types\Type::addType(). 
```
## 🚀 Quick Start

### Make your entity translatable

Implement `Tmi\TranslationBundle\Doctrine\TranslatableInterface` and use the trait
`Tmi\TranslationBundle\Doctrine\ModelTranslatableTrait`on an entity you want to make translatable.
```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class Product implements TranslatableInterface
{
    use TranslatableTrait;
    
    #[ORM\Column]
    private string $name;
    
    // ... your other fields
}
```

### Translate your entity

Use the service `tmi_translation.translator.entity_translator` to translate a source entity to a target language.

```php
$translatedEntity = $this->get('tmi_translation.translator.entity_translator')->translate($entity, 'de_DE');
```

Every attribute of the source entity will be cloned into a new entity, unless specified otherwise with the `EmptyOnTranslate`
attribute.

## 🔧 Advanced Usage

Usually, you don't wan't to get **all** fields of your entity to be cloned. Some should be shared throughout all
translations, others should be emptied in a new translation. Two special attributes are provided in order to
solve this.

### SharedAmongstTranslations

Using this attribute will make the value of your field identical throughout all translations: if you update this
field in any translation, all the others will be synchronized.
If the attribute is a relation to a translatable entity, it will associate the correct translation to each language.

***Note***: `ManyToMany` associations are not supported with `SharedAmongstTranslations` yet.

```php
#[ORM\ManyToOne(targetEntity: Media::class)]
#[SharedAmongstTranslations]
private Media $video; // Shared across all translations

```

### EmptyOnTranslate

This attribute will empty the field when creating a new translation. **ATTENTION**: The field has to be nullable or instance of Doctrine\Common\Collections\Collection! 

```php
#[ORM\ManyToOne(targetEntity: Owner::class, cascade: ['persist'], inversedBy: 'product')]
#[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: true)]
#[EmptyOnTranslate]
private Owner|null $owner = null

#[ORM\Column(type: 'string', nullable: true)]
#[EmptyOnTranslate]
private string|null $title = null;
```
### Translate event
You can alter the entities to translate or translated, before and after translation using the `Tmi\TranslationBundle\Event\TranslateEvent`

- `TranslateEvent::PRE_TRANSLATE` called before starting to translate the properties. The new translation is just instanciated with the right `oid` and `locale`
- `TranslateEvent::POST_TRANSLATE` called after saving the translation

### Filtering your contents

To fetch your contents out of your database in the current locale, you'd usually do something like `$repository->findByLocale($request->getLocale())`.

Alternatively, you can use the provided filter that will automatically filter any Translatable entity by the current locale, every time you query the ORM.
This way, you can simply do `$repository->findAll()` instead of the previous example.

Add this to your `config.yml` file:

```yaml
# Doctrine Configuration
doctrine:
  orm:
    filters:
      # ...
      tmi_translation_locale_filter:
        class:   'Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter'
        enabled: true
```  

#### (Optional) Disable the filter for a specific firewall

Usually you'll need to administrate your contents.
For doing so, you can disable the filter by configuring the disabled_firewalls option in your configuration:

```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
  locales: [en, de, it]
  disabled_firewalls: ['main']  # Disable filter for 'main' firewall
```

### Quick Fix for unique fields

If you need a translatable slug (or UUID), adjust your database schema to make the **slug unique per locale**, instead of globally:

```php
#[ORM\Entity]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(
    name: "uniq_slug_locale",
    columns: ["slug_value", "locale"]
)]
class Product
{
    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(length: 5)]
    private string $locale;
}
```

## 📊 Performance Comparison

| Operation               | Traditional Bundles | TMI Translation Bundle |
|-------------------------|---------------------|------------------------|
| Fetch translated entity | 3-5 SQL queries     | **1 SQL query**        |
| Schema complexity       | Multiple tables     | **Single table**       |
| Join operations         | Required            | **None**               |
| Cache efficiency        | Low                 | **High**               |

## 🤖 AI-Assisted Development

This bundle includes AI skills that help you implement translations correctly. These skills work with [Claude Code](https://claude.com/product/claude-code) and other AI coding assistants.

### Available Skills

| Skill | Purpose | When to Use |
|-------|---------|-------------|
| **Entity Translation Setup** | Guides you through making any Doctrine entity translatable | "Make my Product entity translatable" |
| **Translation Debugger** | Diagnoses and fixes translation configuration issues | "Translation not working", "Why isn't my entity translating?" |
| **Custom Handler Creator** | Helps create custom handlers for specialized field types | "Create a handler for encrypted fields" |

### Using with Claude Code

If you're using [Claude Code](https://claude.ai/claude-code), the skills are automatically available when working in this project. Simply describe what you need:

```
# Make an entity translatable
"Make my Article entity translatable with shared author and translated title/content"

# Debug translation issues
"Translation is not working for my Product entity"

# Create custom handlers
"Create a custom handler for my Money value object"
```

Claude Code will automatically invoke the appropriate skill and guide you through the process.

### Using with Other AI Assistants

The skills are defined in `.agents/skills/` and follow a standard markdown format. Point your AI assistant to:

- [Entity Translation Setup](.agents/skills/entity-translation-setup/SKILL.md)
- [Translation Debugger](.agents/skills/translation-debugger/SKILL.md)
- [Custom Handler Creator](.agents/skills/custom-handler-creator/SKILL.md)

For comprehensive documentation optimized for AI assistants, see:
- [llms.txt](llms.txt) - Quick reference with all important links
- [llms.md](llms.md) - Detailed guide with handler chain decision tree and troubleshooting

## 🤝 Contributing

We welcome contributions!

## 📄 License

This bundle is licensed under the MIT License.

## 🙏 Acknowledgments

Based on the original work by [umanit/translation-bundle](https://github.com/umanit/translation-bundle), now completely modernized for current PHP and Symfony ecosystems.

---

**⭐ If this bundle helps you, please give it a star on GitHub!**