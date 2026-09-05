<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Console\Commands;

use GavTaylor\Sitemap\Support\RobotsTxtSync;
use GavTaylor\Sitemap\Support\RobotsTxtSyncOutcome;
use Illuminate\Console\Command;

final class LinkRobotsTxtCommand extends Command
{
    protected $signature = 'sitemap:link-robots';

    protected $description = 'Add a Sitemap: line to public/robots.txt pointing at the XML sitemap';

    public function handle(): int
    {
        $xmlUrl = (string) url((string) config('sitemap.xml_path', '/sitemap.xml'));
        $path = public_path('robots.txt');

        $outcome = (new RobotsTxtSync($path, $xmlUrl, canWrite: true))->check();

        return match ($outcome) {
            RobotsTxtSyncOutcome::AlreadyLinked => $this->reportSuccess('robots.txt already references the sitemap.'),
            RobotsTxtSyncOutcome::Created, RobotsTxtSyncOutcome::Appended => $this->reportSuccess("Added Sitemap: {$xmlUrl} to robots.txt."),
            RobotsTxtSyncOutcome::Skipped => $this->reportFailure(
                "Could not update {$path} - it may not be writable, or already have a different Sitemap: line ".
                '(check the log for which).',
            ),
        };
    }

    private function reportSuccess(string $message): int
    {
        $this->components->info($message);

        return self::SUCCESS;
    }

    private function reportFailure(string $message): int
    {
        $this->components->error($message);

        return self::FAILURE;
    }
}
