<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class HelperDevice
{
    public function isMobileDevice(): bool
    {
        $userAgent = request()->userAgent() ?? '';

        return Str::contains($userAgent, ['mobile', 'Mobile']);
    }
}
