<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap;

use Illuminate\Support\Str;

/**
 * Reads the optional named-sitemap config. With nothing configured, every
 * URL belongs to the single default sitemap and behaviour is unchanged.
 * `default` is reserved for URLs that match none of the configured names.
 */
final class Sitemaps
{
    /**
     * @return array<string, array{xml_path: string, route_names: list<string>}>
     */
    public static function named(): array
    {
        /** @var array<mixed, mixed> $configured */
        $configured = config('sitemap.sitemaps', []);

        $named = [];

        foreach ($configured as $name => $definition) {
            if (! is_string($name) || $name === '' || $name === 'default' || ! is_array($definition)) {
                continue;
            }

            $path = $definition['xml_path'] ?? null;

            if (! is_string($path) || $path === '') {
                continue;
            }

            $patterns = [];

            foreach ((array) ($definition['route_names'] ?? []) as $pattern) {
                if (is_string($pattern) && $pattern !== '') {
                    $patterns[] = $pattern;
                }
            }

            if ($patterns === []) {
                continue;
            }

            $named[$name] = [
                'xml_path' => $path,
                'route_names' => $patterns,
            ];
        }

        return $named;
    }

    /**
     * The sitemap a route's URLs belong to. The first configured sitemap
     * whose route_names pattern matches wins; everything else is `default`.
     */
    public static function nameFor(?string $routeName): string
    {
        if ($routeName === null || $routeName === '') {
            return 'default';
        }

        foreach (self::named() as $name => $definition) {
            if (Str::is($definition['route_names'], $routeName)) {
                return $name;
            }
        }

        return 'default';
    }
}
