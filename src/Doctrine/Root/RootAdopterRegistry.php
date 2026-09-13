<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Root;

/**
 * The `tmi_translation.root_adopter` services, keyed by the translatable hierarchy
 * each one serves. Filled by RootAdopterPass at compile time; read by
 * `tmi:translation:adopt-root`.
 */
final class RootAdopterRegistry
{
    /** @var array<class-string, RootAdopterInterface> */
    private array $adopters = [];

    /**
     * @param class-string $class the tag's `class` attribute -- must equal the adopter's own
     *                            {@see RootAdopterInterface::getTranslatableClass()}, so the
     *                            compile-time cross-check and the runtime lookup can never
     *                            disagree about which hierarchy the adopter serves
     *
     * @throws \LogicException when the tag and the adopter disagree
     */
    public function addAdopter(RootAdopterInterface $adopter, string $class): void
    {
        if ($adopter->getTranslatableClass() !== $class) {
            throw new \LogicException(\sprintf('Root adopter %s is tagged for %s but getTranslatableClass() returns %s. Solution: make the tag\'s `class` attribute and the method agree.', $adopter::class, $class, $adopter->getTranslatableClass()));
        }

        $this->adopters[$class] = $adopter;
    }

    /**
     * Every registered adopter, ordered by the class it serves.
     *
     * @return list<RootAdopterInterface>
     */
    public function all(): array
    {
        $adopters = $this->adopters;
        ksort($adopters);

        return array_values($adopters);
    }

    /**
     * The adopter serving $class -- registered for the class itself or for an ancestor
     * (a concrete SINGLE_TABLE leaf resolves to its hierarchy's adopter).
     *
     * @param class-string $class
     */
    public function adopterFor(string $class): RootAdopterInterface|null
    {
        foreach ($this->adopters as $served => $adopter) {
            if (is_a($class, $served, true)) {
                return $adopter;
            }
        }

        return null;
    }
}
