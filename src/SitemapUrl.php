<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap;

use DateTimeInterface;

final class SitemapUrl
{
    public function __construct(
        public readonly string $url,
        public readonly string $group,
        public readonly string $label,
        public readonly ?DateTimeInterface $lastmod = null,
        public readonly string $sitemap = 'default',
    ) {
        //
    }

    /**
     * @return array{url: string, group: string, label: string, lastmod: string|null, sitemap: string}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'group' => $this->group,
            'label' => $this->label,
            'lastmod' => $this->lastmod?->format(DateTimeInterface::ATOM),
            'sitemap' => $this->sitemap,
        ];
    }
}
