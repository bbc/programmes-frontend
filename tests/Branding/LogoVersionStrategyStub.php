<?php

namespace Tests\App\Branding;

use App\Branding\LogoConfig;
use App\Branding\LogoVersionStrategy;

class LogoVersionStrategyStub extends LogoVersionStrategy
{
    private $conf;

    protected function loadLogoConfig($forceRefresh = false): LogoConfig
    {
        return $this->conf ??
            $this->conf = LogoConfig::fromJSON(
                file_get_contents(__DIR__ . '/../DataFixtures/JSON/rev-logos.unittest.json')
            );
    }
}
