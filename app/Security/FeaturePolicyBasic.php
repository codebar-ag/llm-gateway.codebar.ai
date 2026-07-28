<?php

namespace App\Security;

use CodebarAg\LaravelFeaturePolicy\Directive;
use CodebarAg\LaravelFeaturePolicy\Policies\Policy;
use CodebarAg\LaravelFeaturePolicy\Value;

class FeaturePolicyBasic extends Policy
{
    public function configure(): void
    {
        $this->addDirective(Directive::GEOLOCATION, Value::SELF)
            ->addDirective(Directive::FULLSCREEN, Value::SELF);
    }
}
