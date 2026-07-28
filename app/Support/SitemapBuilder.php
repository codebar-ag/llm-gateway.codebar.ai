<?php

namespace App\Support;

use App\Console\Commands\GenerateSitemapCommand;
use App\Http\Controllers\SitemapController;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Single source of truth for the sitemap, shared by
 * {@see SitemapController} and
 * {@see GenerateSitemapCommand}.
 */
final class SitemapBuilder
{
    public function build(): Sitemap
    {
        $sitemap = Sitemap::create()
            ->add(Url::create(route('start.index')));

        // Extension point: append public, indexable URLs here.
        // e.g. Post::query()->published()->each(fn (Post $post) => $sitemap->add($post->toUrl()));

        return $sitemap;
    }
}
