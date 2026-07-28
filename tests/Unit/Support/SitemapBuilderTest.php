<?php

use App\Support\SitemapBuilder;

it('builds a sitemap containing the dashboard url', function () {
    $sitemap = (new SitemapBuilder)->build();

    expect($sitemap->render())->toContain(route('start.index'));
})->group('support', 'sitemap');
