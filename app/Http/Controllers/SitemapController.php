<?php

namespace App\Http\Controllers;

use App\Support\SitemapBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SitemapController
{
    public function __invoke(Request $request, SitemapBuilder $sitemapBuilder): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn (): string => $sitemapBuilder->build()->render());

        return response($xml, Response::HTTP_OK, ['Content-Type' => 'text/xml']);
    }
}
