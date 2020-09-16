<?php

namespace App\Twig;

use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use function in_array;

class NetworkLogoExtension extends AbstractExtension
{
    /**
     * @var Packages
     */
    private $packages;

    /**
     * @var ParameterBagInterface
     */
    private $params;

    public function __construct(
        Packages $packages,
        ParameterBagInterface $params
    ) {
        $this->packages = $packages;
        $this->params = $params;
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

    public function getNetworkLogo(string $nid, string $size): string
    {
        if ($size != '64x36') {
            // Only two sizes supported, default to 112x63
            $size = '112x63';
        }

        $path = ['images/', 'logos/'];
        $logo = ($this->isAllowedLogo($nid)) ? $nid : 'bbc';

        if ($this->isSvgLogo($nid)) {
            array_push($path, 'svg/', $logo, '.svg');
        } else {
            array_push($path, $size, '/', $logo, '.png');
        }

        return $this->packages->getUrl(implode('', $path));
    }

    private function getParameter($param)
    {
        return $this->params->get($param);
    }

    private function isSvgLogo($nid): bool
    {
        $branding = $this->getParameter('branding');
        return in_array($nid, $branding['logos']['filetypes']['svg']);
    }

    private function isAllowedLogo($nid): bool
    {
        $branding = $this->getParameter('branding');
        $filetypes = $branding['logos']['filetypes'];
        return in_array($nid, $filetypes['png']) || in_array($nid, $filetypes['svg']);
    }
}
