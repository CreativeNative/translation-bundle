<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Root;

/**
 * An application-contributed count for `tmi:translation:adopt-root --check` (5.1):
 * rows in a table the bundle does not know that reference a Tuuid no translation row
 * carries any more -- a photo store keyed by a bare `tuuid` column, a review table
 * keyed by `(entity_class, tuuid)`.
 *
 * Tag implementations `tmi_translation.tuuid_orphan_counter`. The report prints EVERY
 * registered counter, including those at 0, so a counter the application forgot to
 * register is visibly absent rather than indistinguishable from "clean". Any non-zero
 * count fails the check; an exception thrown by {@see countOrphans()} is shown as
 * `ERROR` with the exception class and fails the check too -- a broken counter must
 * never look like a verified-clean one.
 */
interface TuuidOrphanCounterInterface
{
    /**
     * A stable report label, e.g. `rental_photo.tuuid`.
     */
    public function getName(): string;

    /**
     * 0 = clean.
     */
    public function countOrphans(): int;
}
