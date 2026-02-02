---
name: entity-translation-setup
description: Guide users through making Doctrine entities translatable with TranslatableInterface, TranslatableTrait, and attribute configuration. Use when user asks to "make this entity translatable", "add translations to [Entity]", "translate [Entity] fields", or needs help implementing multilingual entities with the TMI Translation Bundle.
---

# Entity Translation Setup Skill

## Activation

When triggered, announce: **"I'll use the entity-translation-setup skill to guide you through making this entity translatable."**

Ask: **"Want me to use defaults (quick mode), or walk through each decision (guided mode)?"**

## Step 1: Read Entity

1. Read the entity file the user specified
2. Parse fields by type:
   - Scalars: string, text, integer, float, decimal, boolean, datetime
   - Relations: OneToOne, OneToMany, ManyToOne, ManyToMany
   - Embedded objects
3. Filter out non-translatable fields:
   - IDs (primary keys)
   - Timestamps (createdAt, updatedAt)
   - Generated values (auto-increment counters)

## Step 2: Explain TranslatableTrait (Brief)

Tell the user:

**"The TranslatableTrait provides three fields automatically:**
- **tuuid**: Translation UUID linking all locale variants (already marked SharedAmongstTranslations)
- **locale**: Current locale code (e.g., 'en', 'fr')
- **translations**: JSON storage for all locale data

You don't add these fields yourself - the trait handles them."

## Quick Mode Workflow

**Defaults applied:**
- Translate all scalar fields EXCEPT price/cost/amount/value (those are SharedAmongstTranslations)
- Share all relationship fields (same entity across locales)
- Apply EmptyOnTranslate only if field name contains "slug" or "seo"

**Process:**
1. Show diff-style output with changes
2. Ask: "Apply these changes?"
3. Wait for user confirmation
4. Apply changes
5. Remind: "Run migration: `bin/console doctrine:migrations:diff`"

## Guided Mode Workflow

### 2.1: Select Fields to Translate

Show grouped fields:
```
Scalar fields:
  - name (string)
  - description (text)
  - price (decimal)
  - sku (string)
  - slug (string)

Relationships:
  - category (ManyToOne → Category)
  - images (OneToMany → ProductImage)

Which fields should be translatable? (comma-separated)
```

### 2.2: SharedAmongstTranslations Guidance

**Examples-first approach:**

"Some fields have the SAME value across all languages. Mark these with SharedAmongstTranslations:

**Examples:**
- **Product SKU**: 'WIDGET-123' is the same in English, French, German
- **Price**: $999 appears as $999 regardless of locale
- **Creation date**: 2025-01-15 is the same timestamp everywhere
- **Category relation**: Product belongs to same category in all languages

**For your entity:**"

Show smart suggestions based on field names:
- price/cost/amount/value → suggest SharedAmongstTranslations
- *id (any relation with 'id' suffix) → suggest SharedAmongstTranslations
- createdAt/updatedAt → suggest SharedAmongstTranslations

Ask: "Which fields should be SharedAmongstTranslations? (comma-separated, or 'none')"

### 2.3: EmptyOnTranslate Guidance (Optional)

**Examples-first approach:**

"Some fields need a NEW value in each language (they can't be copied from source). Mark these with EmptyOnTranslate:

**Examples:**
- **Slug**: 'blue-widget' (EN) → 'widget-bleu' (FR) — must be regenerated
- **SEO URL**: '/products/blue-widget' → '/produits/widget-bleu' — needs new path

**For your entity:**"

Show smart suggestions:
- slug/seoUrl/permalink/path → suggest EmptyOnTranslate

Ask: "Which fields should be EmptyOnTranslate? (comma-separated, or 'none')"

### 2.4: Auto-Detect Relationships

For each relationship field selected:

```
Product has OneToMany to ProductImage.
Should ProductImage be translated together with Product?
- Yes: Each locale of Product has its own set of ProductImages
- No: All locales share the same ProductImages
```

## Step 3: Generate Diff

Show changes in diff-style format with inline comments:

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
+ use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
+ use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
+ use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
+ use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;

#[ORM\Entity]
- class Product
+ class Product implements TranslatableInterface
{
+     use TranslatableTrait;  // Adds: tuuid, locale, translations

    #[ORM\Column(type: Types::STRING)]
    private string $name;  // Translatable (no attribute = copied on translate)

    #[ORM\Column(type: Types::DECIMAL)]
+     #[SharedAmongstTranslations]  // Same price across all languages
    private string $price;

    #[ORM\Column(type: Types::STRING)]
+     #[EmptyOnTranslate]  // Slug must be regenerated per locale
    private string $slug;

    #[ORM\ManyToOne(targetEntity: Category::class)]
+     #[SharedAmongstTranslations]  // All locales share same category
    private Category $category;
}
```

## Step 4: Confirm and Apply

Ask: **"Apply these changes?"**

Wait for user confirmation (yes/y/apply/confirm).

**After applying:**
- Save changes to entity file
- Show success message
- Remind: **"Run migration to update database: `bin/console doctrine:migrations:diff`"**

## Relationship Handler Behavior Summary

**How relationship translation works:**
- **OneToMany**: Translates collection items, maintains inverse property pointing back to parent (BidirectionalOneToManyHandler)
- **ManyToOne**: Creates new relation pointing to translated target OR same shared entity (ManyToOneHandler)
- **ManyToMany**: Clones association collection for the translation (ManyToManyHandler)

For complete handler chain details, priority order, and edge cases, see **llms.md → "Handler Chain Decision Tree"** section.

## Smart Field Suggestions

**Auto-suggest SharedAmongstTranslations for:**
- price, cost, amount, value, total, subtotal
- sku, barcode, isbn, upc
- weight, width, height, depth, quantity
- createdAt, updatedAt, publishedAt
- Relations ending in: *Id, *Ref

**Auto-suggest EmptyOnTranslate for:**
- slug, permalink, path, route
- seoUrl, canonicalUrl
- handle, identifier (if string-based and locale-specific)

**Always confirm suggestions with user before applying.**
