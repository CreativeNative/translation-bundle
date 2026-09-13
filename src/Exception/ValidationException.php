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
        $messages     = array_map(
            static fn (\LogicException $e) => $e->getMessage(),
            $errors,
        );

        parent::__construct(\sprintf(
            "TMI Translation validation failed with %d error(s):\n\n%s",
            \count($errors),
            implode("\n\n", $messages),
        ));
    }

    /**
     * The aggregate a compiler pass throws: one line per message under one label,
     * e.g. "TMI Translation Bundle: Compile-time validation failed with 2 error(s):".
     *
     * @param list<string> $messages
     */
    public static function fromMessages(string $label, array $messages): self
    {
        $exception          = new self(array_map(static fn (string $message): \LogicException => new \LogicException($message), $messages));
        $exception->message = \sprintf(
            "TMI Translation Bundle: %s failed with %d error(s):\n\n%s",
            $label,
            \count($messages),
            implode("\n", array_map(static fn (string $message): string => '- '.$message, $messages)),
        );

        return $exception;
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
