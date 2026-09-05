<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap;

use DateTimeInterface;

final class SitemapUrl
{
    public function __construct(
        public readonly string $url,
        public readonly string $group,
        public readonly ?DateTimeInterface $lastmod = null,
    ) {
        //
    }

    /**
     * @return array{url: string, group: string, lastmod: string|null}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'group' => $this->group,
            'lastmod' => $this->lastmod?->format(DateTimeInterface::ATOM),
        ];
    }
}
