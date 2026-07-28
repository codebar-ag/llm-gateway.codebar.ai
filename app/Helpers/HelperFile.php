<?php

namespace App\Helpers;

use Illuminate\Support\Number;

class HelperFile
{
    public function formatBytes(int $size, int $precision = 0): string
    {
        return Number::fileSize($size, $precision);
    }

    public function nameWithDate(string $name): string
    {
        return now()->format('Ymd_').$name;
    }

    public function nameWithDateTime(string $name): string
    {
        return now()->format('YmdHm_').$name;
    }
}
