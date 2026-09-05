<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Support;

use Illuminate\Support\Facades\Log;

/**
 * `robots.txt` - not an HTML <link> tag - is how a crawler actually
 * discovers a sitemap passively, via its `Sitemap:` directive. Most Laravel
 * apps serve `public/robots.txt` as a static file the web server returns
 * directly, so this package can't register a route to manage it, and can't
 * assume the process handling a real HTTP request (unlike a console/deploy
 * command, which normally runs as the same user that just wrote the rest of
 * the codebase) can write to `public/` in production - `$canWrite` is how a
 * caller tells this class which kind of context it's running in.
 *
 * Writing is otherwise deliberately conservative: a missing file or a
 * missing line is always safe to add, but an *existing* Sitemap: line
 * pointing somewhere else is never touched, even when writing is allowed -
 * that could be a deliberate choice (or a stale one) this package has no
 * way to judge from the file alone. Every case that isn't fixed gets a
 * logged warning instead, so a misconfiguration is never silent - the app
 * still loads fine either way, so this is a warning, not an error.
 */
final class RobotsTxtSync
{
    public function __construct(
        private readonly string $robotsTxtPath,
        private readonly string $xmlUrl,
        private readonly bool $canWrite,
    ) {
        //
    }

    public function check(): RobotsTxtSyncOutcome
    {
        if (! is_file($this->robotsTxtPath)) {
            return $this->handleMissingFile();
        }

        $contents = file_get_contents($this->robotsTxtPath);

        if ($contents === false) {
            return RobotsTxtSyncOutcome::Skipped;
        }

        if (str_contains($contents, $this->xmlUrl)) {
            return RobotsTxtSyncOutcome::AlreadyLinked;
        }

        if ($this->hasAnySitemapDirective($contents)) {
            Log::warning(sprintf(
                'gavtaylor/laravel-sitemap: robots.txt has a "Sitemap:" line that does not match the configured '.
                'URL (%s) - this looks like a misconfiguration (a stale entry from before a domain/path change, '.
                'perhaps) rather than something safe to fix automatically. Check robots.txt against '.
                "config('sitemap.xml_path') / APP_URL.",
                $this->xmlUrl,
            ));

            return RobotsTxtSyncOutcome::Skipped;
        }

        return $this->handleMissingLine($contents);
    }

    private function handleMissingFile(): RobotsTxtSyncOutcome
    {
        if ($this->canWrite && is_writable(dirname($this->robotsTxtPath))) {
            file_put_contents($this->robotsTxtPath, "User-agent: *\nDisallow:\n\nSitemap: {$this->xmlUrl}\n");

            return RobotsTxtSyncOutcome::Created;
        }

        Log::warning(sprintf(
            'gavtaylor/laravel-sitemap: %s does not exist, so crawlers have no robots.txt to discover the sitemap '.
            'from. Run `php artisan sitemap:link-robots` to create one (allows all crawling, points at %s).',
            $this->robotsTxtPath,
            $this->xmlUrl,
        ));

        return RobotsTxtSyncOutcome::Skipped;
    }

    private function handleMissingLine(string $contents): RobotsTxtSyncOutcome
    {
        if ($this->canWrite && is_writable($this->robotsTxtPath)) {
            file_put_contents($this->robotsTxtPath, rtrim($contents)."\n\nSitemap: {$this->xmlUrl}\n");

            return RobotsTxtSyncOutcome::Appended;
        }

        Log::warning(sprintf(
            'gavtaylor/laravel-sitemap: robots.txt exists but has no "Sitemap: %s" line - '.
            'crawlers that rely on robots.txt for discovery won\'t find it. '.
            'Run `php artisan sitemap:link-robots` to add it.',
            $this->xmlUrl,
        ));

        return RobotsTxtSyncOutcome::Skipped;
    }

    private function hasAnySitemapDirective(string $contents): bool
    {
        return (bool) preg_match('/^Sitemap:\s*\S+/mi', $contents);
    }
}
