<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * Aggregate exception that collects multiple validation errors.
 *
 * When attribute validation finds multiple issues, this exception allows
 * reporting all of them at once rather than failing on the first error.
 */
final class ValidationException extends \LogicException
{
    /** @var array<\LogicException> */
    private readonly array $errors;

    /**
     * @param array<\LogicException> $errors Array of validation errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        $messages = array_map(
            static fn (\LogicException $e) => $e->getMessage(),
            $errors,
        );

        parent::__construct(sprintf(
            "TMI Translation validation failed with %d error(s):\n\n%s",
            count($errors),
            implode("\n\n", $messages),
        ));
    }

    /**
     * Get all validation errors for programmatic access.
     *
     * @return array<\LogicException>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
