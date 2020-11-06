<?php

namespace App\Twig;

use App\Branding\LogoVersionStrategy;
use App\Branding\LogoConfig;
use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NetworkLogoExtension extends AbstractExtension
{
    /**
     * @var Packages
     */
    private $packages;

    /**
     * @var LogoVersionStrategy
     */
    private $logoVersionStrategy;

    public function __construct(
        Packages $packages,
        LogoVersionStrategy $logoVersionStrategy
    ) {
        $this->packages = $packages;
        $this->logoVersionStrategy = $logoVersionStrategy;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_network_logo', [$this, 'getNetworkLogo']),
        ];
    }

    public function getNetworkLogo(string $nid, string $size, $element = 'service'): string
    {
        if ($size != '64x36') {
            // Only two sizes supported, default to 112x63
            $size = '112x63';
        }

        $path = ['images/', 'logos/'];
        $logoNid = $this->isAllowedLogo($nid) ? $nid : 'bbc';

        $cfg = $this->getLogoConfig();
        if ($cfg->isSVGLogo($nid)) {
            array_push($path, 'svg/', $logoNid, '/', $element, '.svg');
        } else {
            array_push($path, $size, '/', $logoNid, '.png');
        }

        return $this->packages->getUrl(implode('', $path), 'logos');
    }

    private function isAllowedLogo($nid): bool
    {
        $cfg = $this->getLogoConfig();
        return $cfg->isSVGLogo($nid) || $cfg->isPNGLogo($nid);
    }

    private function getLogoConfig(): LogoConfig
    {
        return $this->logoVersionStrategy->getLogoConfig();
    }
}
