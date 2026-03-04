<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Scalar;

use Doctrine\ORM\EntityRepository;
use Tmi\TranslationBundle\Doctrine\Repository\TranslatableRepositoryTrait;

/**
 * @extends EntityRepository<scalar>
 */
class ScalarRepository extends EntityRepository
{
    use TranslatableRepositoryTrait;
}
