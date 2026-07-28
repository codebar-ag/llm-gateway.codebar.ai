<?php

namespace App\Security;

use Illuminate\Support\Facades\Config;
use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

class SecurityPolicyBasic implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::CONNECT, Config::array('csp.directive_sources.connect'))
            ->add(Directive::DEFAULT, Config::array('csp.directive_sources.default'))
            ->add(Directive::FORM_ACTION, Config::array('csp.directive_sources.form_action'))
            ->add(Directive::IMG, Config::array('csp.directive_sources.img'))
            ->add(Directive::MEDIA, Config::array('csp.directive_sources.media'))
            ->add(Directive::OBJECT, Config::array('csp.directive_sources.object'))
            ->add(Directive::FONT, Config::array('csp.directive_sources.font'))
            ->add(Directive::SCRIPT, Config::array('csp.directive_sources.script'))
            ->add(Directive::STYLE_ELEM, Config::array('csp.directive_sources.style_elem'))
            ->add(Directive::STYLE, Config::array('csp.directive_sources.style'));
    }
}
