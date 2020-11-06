<?php

namespace Tests\App\LogoVersionStrategy;

use App\Branding\LogoVersionStrategy;
use Liip\FunctionalTestBundle\Test\WebTestCase;

class LogoVersionsTest extends WebTestCase
{
    private $logoVersionStrategy;

    public function setUp()
    {

        $this->logoVersionStrategy = $this->getContainer()->get(LogoVersionStrategy::class);
    }

    public function testLogoTypes()
    {
        $lc = $this->logoVersionStrategy->getLogoConfig();
        $this->assertTrue($lc->isSVGLogo('bbc_one'));
        $this->assertFalse($lc->isPNGLogo('bbc_one'));
        $this->assertTrue($lc->isPNGLogo('bbc_7'));
        $this->assertFalse($lc->isSVGLogo('bbc_7'));
    }

    public function testLogoPaths()
    {
        $map = [
            'images/logos/112x63/bbc.png' => 'images/logos/112x63/bbc-31337.png',
            'non/existent/path.ext' => null,
        ];

        $ll = $this->logoVersionStrategy;
        foreach ($map as $asset => $version) {
            $this->assertEquals($version, $ll->getVersion($asset));
        }
    }

    public function testLogoApplyVersion()
    {
        $base = $this->getContainer()->getParameter('app.logos.base_url');

        $map = [
            'images/logos/112x63/bbc.png' => "{$base}/images/logos/112x63/bbc-31337.png",
            'non/existent/path.ext' => "non/existent/path.ext",
        ];

        $ll = $this->logoVersionStrategy;
        foreach ($map as $asset => $version) {
            $this->assertEquals($version, $ll->applyVersion($asset));
        }
    }
}
