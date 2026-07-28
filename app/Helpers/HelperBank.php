<?php

namespace App\Helpers;

/**
 * Helpers here use a Helper* class-name prefix (not the *Helper suffix enforced for most other App namespaces
 * in tests/Core/ClassnamesTest.php).
 *
 * Starter utilities: nothing outside tests/Helper references them yet; use them when a feature needs them.
 */
class HelperBank
{
    public function formatIban(?string $iban): ?string
    {
        return $iban ?
            trim(chunk_split($iban, 4, ' '))
            : null;
    }
}
