[![codecov](https://codecov.io/github/CreativeNative/translation-bundle/graph/badge.svg?token=D2PXJL5T2Y)](https://codecov.io/github/CreativeNative/translation-bundle)

# Translation Bundle
This bundle intends to ease Doctrine entity translations.
Unlike most translations libraries, every translation is stored in the same table as the source entity.

## ToDo

* ManyToMany associations are not supported with SharedAmongstTranslations yet.

## Features

* Add translations without changing existing entities
* Translation fields are stored in the same table (no expensive joins)
* Supports inherited entities
* Handle more than just text fields
* Auto-population of translated relations

## Install

```
composer require tmi/translation-bundle
```

Register the bundle to your `app/AppKernel.php` if it's not done automatically.

```php
    new TMI\TranslationBundle\tmiTranslationBundle(),
```

Configure your available locales and, optionally, the default one:

```yaml
tmi_translation:
  locales: [en, fr, ja]
  # default_locale: en is optional, otherwise kernel.default_locale is used
```

That's it!

## Usage

### Make your entity translatable

Implement `TMI\TranslationBundle\Doctrine\TranslatableInterface` and use the trait
`TMI\TranslationBundle\Doctrine\ModelTranslatableTrait`on an entity you want to make translatable.
```php
<?php

namespace App\Entity\Content;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * HomePage
 *
 * @ORM\Table(name="page")
 */
class Page implements TranslatableInterface
{
    use TranslatableTrait;
}
```

### Translate your entity

Use the service `tmi_translation.translator.entity_translator` to translate a source entity to a target language.

```php
$translatedEntity = $this->get('tmi_translation.translator.entity_translator')->translate($entity, 'fr');
```

The `$translatedEntity` will be persisted with Sonata, jumpstarted with EasyAdmin: with both, you'll be redirected to the
edit form.

Every attribute of the source entity will be cloned into a new entity, unless specified otherwise with the `EmptyOnTranslate`
attribute.

## Options

Usually, you don't wan't to get **all** fields of your entity to be cloned. Some should be shared throughout all
translations, others should be emptied in a new translation. Two special attributes are provided in order to
solve this.

**SharedAmongstTranslations**

Using this attribute will make the value of your field identical throughout all translations: if you update this
field in any translation, all the others will be synchronized.
If the attribute is a relation to a translatable entity, it will associate the correct translation to each language.

**Note :** `ManyToMany` associations are not supported with `SharedAmongstTranslations` yet.

```php
<?php

namespace App\Entity\Content;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

#[ORM\Table(name: "page")]
class Page implements TranslatableInterface
{
    use TranslatableTrait;
    
     #[ORM\ManyToOne(targetEntity: "Application\Sonata\MediaBundle\Entity\Media", cascade: {"persist"})]
     #[ORM\JoinColumn(name: "video_id", referencedColumnName: "id")]
     #[SharedAmongstTranslations]
    protected Application\Sonata\MediaBundle\Entity\Media $video;
    
}
```

**EmptyOnTranslate**

This attribute will empty the field when creating a new translation.

```php
<?php

namespace App\Entity\Content;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;

 #[ORM\Table(name: "page")]
class Page implements TranslatableInterface
{
    use TranslatableTrait;
    
    // ...
    
     #[ORM\ManyToOne(targetEntity: "Application\Sonata\MediaBundle\Entity\Media", cascade: {"persist"})]
     #[ORM\JoinColumn(name: "image_id", referencedColumnName: "id")]
     #[EmptyOnTranslate]
    protected Application\Sonata\MediaBundle\Entity\Media $image;
    
}
```

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
        class:   'TMI\TranslationBundle\Doctrine\Filter\LocaleFilter'
        enabled: true
```  

#### (Optional) Disable the filter for a specific firewall

Usually you'll need to administrate your contents.
For doing so, you can disable the filter by configuring the disabled_firewalls option.

```yaml
tmi_translation:
  # ...
  disabled_firewalls: ['admin']
```

## Advanced usage

You can alter the entities to translate or translated, before and after translation using the `TMI\TranslationBundle\Event\TranslateEvent`

- `TranslateEvent::PRE_TRANSLATE` called before starting to translate the properties. The new translation is just instanciated with the right `oid` and `locale`
- `TranslateEvent::POST_TRANSLATE` called after saving the translation
