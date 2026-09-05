<?php

declare(strict_types=1);

use GavTaylor\Sitemap\Support\RobotsTxtSync;
use GavTaylor\Sitemap\Support\RobotsTxtSyncOutcome;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->robotsPath = sys_get_temp_dir().'/laravel-sitemap-robots-sync-'.uniqid().'/robots.txt';
    File::makeDirectory(dirname($this->robotsPath), recursive: true);
});

afterEach(function () {
    File::deleteDirectory(dirname($this->robotsPath));
});

it('does nothing when robots.txt already references the sitemap', function () {
    file_put_contents($this->robotsPath, "User-agent: *\nSitemap: https://example.com/sitemap.xml\n");

    Log::shouldReceive('warning')->never();

    $outcome = (new RobotsTxtSync($this->robotsPath, 'https://example.com/sitemap.xml', canWrite: false))->check();

    expect($outcome)->toBe(RobotsTxtSyncOutcome::AlreadyLinked);
});

describe('when writing is not allowed (a real HTTP request)', function () {
    it('warns without creating a missing robots.txt', function () {
        Log::shouldReceive('warning')->once()->with(Mockery::pattern('/does not exist/'));

        $outcome = (new RobotsTxtSync($this->robotsPath, 'https://example.com/sitemap.xml', canWrite: false))->check();

        expect($outcome)->toBe(RobotsTxtSyncOutcome::Skipped)
            ->and(is_file($this->robotsPath))->toBeFalse();
    });

    it('warns without appending a missing sitemap line', function () {
        file_put_contents($this->robotsPath, "User-agent: *\nDisallow:\n");

        Log::shouldReceive('warning')->once()->with(Mockery::pattern('/sitemap:link-robots/'));

        $outcome = (new RobotsTxtSync($this->robotsPath, 'https://example.com/sitemap.xml', canWrite: false))->check();

        expect($outcome)->toBe(RobotsTxtSyncOutcome::Skipped)
            ->and(file_get_contents($this->robotsPath))->not->toContain('Sitemap:');
    });

    it('warns about a mismatched sitemap line without touching it', function () {
        file_put_contents($this->robotsPath, "User-agent: *\nSitemap: https://old-domain.example/sitemap.xml\n");

        Log::shouldReceive('warning')->once()->with(Mockery::pattern('/misconfiguration/'));

        $outcome = (new RobotsTxtSync($this->robotsPath, 'https://example.com/sitemap.xml', canWrite: false))->check();

        expect($outcome)->toBe(RobotsTxtSyncOutcome::Skipped)
            ->and(file_get_contents($this->robotsPath))->toContain('https://old-domain.example/sitemap.xml');
    });
});

describe('when writing is allowed (a console command)', function () {
    it('creates a missing robots.txt allowing all crawling', function () {
        Log::shouldReceive('warning')->never();

        $outcome = (new RobotsTxtSync($this->robotsPath, 'https://example.com/sitemap.xml', canWrite: true))->check();

        expect($outcome)->toBe(RobotsTxtSyncOutcome::Created);

        $contents = file_get_contents($this->robotsPath);

        expect($contents)->toContain('Disallow:')
            ->and($contents)->toContain('Sitemap: https://example.com/sitemap.xml')
            ->and($contents)->not->toContain('Disallow: /');
    });

    it('appends a missing sitemap line to an existing robots.txt', function () {
        file_put_contents($this->robotsPath, "User-agent: *\nDisallow: /admin\n");

        Log::shouldReceive('warning')->never();

        $outcome = (new RobotsTxtSync($this->robotsPath, 'https://example.com/sitemap.xml', canWrite: true))->check();

        expect($outcome)->toBe(RobotsTxtSyncOutcome::Appended);

        $contents = file_get_contents($this->robotsPath);

        expect($contents)->toContain('Disallow: /admin')
            ->and($contents)->toContain('Sitemap: https://example.com/sitemap.xml');
    });

    it('still only warns about a mismatched sitemap line, never overwriting it', function () {
        file_put_contents($this->robotsPath, "User-agent: *\nSitemap: https://old-domain.example/sitemap.xml\n");

        Log::shouldReceive('warning')->once()->with(Mockery::pattern('/misconfiguration/'));

        $outcome = (new RobotsTxtSync($this->robotsPath, 'https://example.com/sitemap.xml', canWrite: true))->check();

        expect($outcome)->toBe(RobotsTxtSyncOutcome::Skipped);

        $contents = file_get_contents($this->robotsPath);

        expect($contents)->toContain('https://old-domain.example/sitemap.xml')
            ->and($contents)->not->toContain('https://example.com/sitemap.xml');
    });

    it('warns instead of writing when the path genuinely is not writable', function () {
        file_put_contents($this->robotsPath, "User-agent: *\nDisallow:\n");
        chmod($this->robotsPath, 0444);

        Log::shouldReceive('warning')->once()->with(Mockery::pattern('/sitemap:link-robots/'));

        $outcome = (new RobotsTxtSync($this->robotsPath, 'https://example.com/sitemap.xml', canWrite: true))->check();

        expect($outcome)->toBe(RobotsTxtSyncOutcome::Skipped);

        chmod($this->robotsPath, 0644);
    });
});
