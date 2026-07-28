<?php

namespace App\Console\Commands;

use App\Support\SitemapBuilder;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap.';

    public function handle(SitemapBuilder $sitemapBuilder): int
    {
        $sitemapBuilder->build()->writeToFile(public_path('sitemap.xml'));

        return self::SUCCESS;
    }
}
