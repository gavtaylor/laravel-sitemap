<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Support;

enum RobotsTxtSyncOutcome
{
    case AlreadyLinked;
    case Created;
    case Appended;

    /**
     * Nothing was written - either because writing wasn't allowed in this
     * context, the path genuinely isn't writable, or robots.txt already has
     * a different Sitemap: line that this package won't overwrite. A
     * warning explaining which of these it was has already been logged.
     */
    case Skipped;
}
