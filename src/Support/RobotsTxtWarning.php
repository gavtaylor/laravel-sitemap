<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Support;

use Illuminate\Support\Facades\Log;

/**
 * `robots.txt` - not an HTML <link> tag - is how a crawler actually
 * discovers a sitemap passively, via its `Sitemap:` directive. Most Laravel
 * apps serve `public/robots.txt` as a static file the web server returns
 * directly, so this package can't safely register a route to add the line
 * itself (Laravel would never even see the request), nor assume the web
 * server user can write to `public/` in production. So: warn, don't act -
 * `php artisan sitemap:link-robots` is the actual fix.
 */
final class RobotsTxtWarning
{
    public function __construct(
        private readonly string $robotsTxtPath,
        private readonly string $xmlUrl,
    ) {
        //
    }

    public function check(): void
    {
        if (! is_file($this->robotsTxtPath)) {
            return;
        }

        $contents = file_get_contents($this->robotsTxtPath);

        if ($contents !== false && str_contains($contents, $this->xmlUrl)) {
            return;
        }

        Log::warning(sprintf(
            'gavtaylor/laravel-sitemap: robots.txt exists but has no "Sitemap: %s" line - '.
            'crawlers that rely on robots.txt for discovery won\'t find it. '.
            'Run `php artisan sitemap:link-robots` to add it.',
            $this->xmlUrl,
        ));
    }
}
