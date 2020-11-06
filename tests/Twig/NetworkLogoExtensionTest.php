<?php

namespace Tests\App\Twig;

use Liip\FunctionalTestBundle\Test\WebTestCase;

class NetworkLogoExtensionTest extends WebTestCase
{
    private $netFn;
    private $base;

    public function setUp()
    {
        $container = $this->getContainer();
        $this->base = $container->getParameter('app.logos.base_url');
        $twig = $container->get('twig');
        $this->netFn = $twig->getFunction('get_network_logo')->getCallable();
    }

    public function testNetworkLogos()
    {
        $fn = $this->netFn;
        $base = $this->base;

        $url = $fn('bbc_one', '112x63');   // should ignore size for svg logo and default to service variant
        $this->assertEquals($base . '/images/logos/svg/bbc_one/service-13d7290601.svg', $url);

        $url = $fn('bbc_one', '112x63', 'masthead');   // should return specific variant for SVG logo
        $this->assertEquals($base . '/images/logos/svg/bbc_one/masthead-4859e89e19.svg', $url);

        $url = $fn('bbc_none', '112x63');  // should return default bbc logo
        $this->assertEquals($base . '/images/logos/112x63/bbc-31337.png', $url);

        $url = $fn('bbc_none', '64x36');  // should return default bbc logo
        $this->assertEquals($base . '/images/logos/64x36/bbc-1337.png', $url);

        $url = $fn('bbc_7', '64x36', 'service');   // the element shouldnt matter for this PNG logo
        $this->assertEquals($base . '/images/logos/64x36/bbc_7-65c6638d9c.png', $url);
    }
}
