<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Console\Commands;

use GavTaylor\Sitemap\SitemapCache;
use Illuminate\Console\Command;

final class ClearSitemapCacheCommand extends Command
{
    protected $signature = 'sitemap:clear';

    protected $description = 'Clear the cached sitemap route scan';

    public function handle(SitemapCache $cache): int
    {
        $cache->clear();

        $this->components->info('Sitemap cache cleared.');

        return self::SUCCESS;
    }
}
