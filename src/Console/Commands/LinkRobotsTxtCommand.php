<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Console\Commands;

use Illuminate\Console\Command;

final class LinkRobotsTxtCommand extends Command
{
    protected $signature = 'sitemap:link-robots';

    protected $description = 'Add a Sitemap: line to public/robots.txt pointing at the XML sitemap';

    public function handle(): int
    {
        $xmlUrl = (string) url((string) config('sitemap.xml_path', '/sitemap.xml'));
        $path = public_path('robots.txt');

        $existing = is_file($path) ? (string) file_get_contents($path) : '';

        if (str_contains($existing, $xmlUrl)) {
            $this->components->info('robots.txt already references the sitemap.');

            return self::SUCCESS;
        }

        $writable = is_file($path) ? is_writable($path) : is_writable(dirname($path));

        if (! $writable) {
            $this->components->error("Could not write to {$path} - check it's writable by the user running this command.");

            return self::FAILURE;
        }

        $contents = $existing === ''
            ? "User-agent: *\nDisallow:\n\nSitemap: {$xmlUrl}\n"
            : rtrim($existing)."\n\nSitemap: {$xmlUrl}\n";

        file_put_contents($path, $contents);

        $this->components->info('Added Sitemap: '.$xmlUrl.' to robots.txt.');

        return self::SUCCESS;
    }
}
