<?php
declare(strict_types = 1);
namespace Tests\App\Branding;

// this test works with the local asset files in /assets (not S3 or web/assets)

use Tests\App\BaseWebTestCase;

class LogoAssetTest extends BaseWebTestCase
{
    protected $logos = null;
    private const ASSET_PATH = 'assets/images/logos';

    public function setUp()
    {
        $this->logos = $this->getContainer()->getParameter('branding')['logos'];
    }

    public function testPNGLogoAssetsExist()
    {
        $nids = $this->logos['filetypes']['png'];
        foreach ($nids as $nid) {
            $this->assertAssetExists($nid, 'png', static::ASSET_PATH);
        }
    }

    public function testSVGLogoAssetsExist()
    {
        $nids = $this->logos['filetypes']['svg'];
        foreach ($nids as $nid) {
            $this->assertAssetExists($nid, 'svg', static::ASSET_PATH);
        }
    }

    /**
     * checks whether there are extraneous files found in the assets folders
     * that have not been defined as logos
     */
    public function testUnknownAssetsExist()
    {
        return $this->markTestSkipped("unknown assets test skipped till release settles");

        $scan = function ($path) {
            return array_filter(
                scandir($path),
                function ($fn) {
                    return substr($fn, 0, 1) !== '.';
                }
            );
        };

        $svgPath = static::ASSET_PATH . '/svg';
        $pngPath1 = static::ASSET_PATH . '/64x36';
        $pngPath2 = static::ASSET_PATH . '/112x63';

        // 1. files
        $svgAssets = $scan($svgPath);
        $pngAssets1 = $scan($pngPath1);
        $pngAssets2 = $scan($pngPath2);

        $appendExtension = function ($nids, $ext) {
            return array_map(
                function ($nid) use ($ext) {
                    return "{$nid}.{$ext}";
                },
                $nids
            );
        };

        // 2. definitions from config
        $svgLogos = $appendExtension($this->logos['filetypes']['svg'], 'svg');
        $pngLogos = $appendExtension($this->logos['filetypes']['png'], 'png');

        // checks if the contents of two arrays match
        $assertSameContents = function ($arr1, $arr2, $label1 = '1', $label2 = '2') {
            $this->assertEmpty(
                array_diff($arr1, $arr2),
                "entries in '{$label1}' not found in '{$label2}'\n" . print_r(array_diff($arr1, $arr2), true)
            );
            $this->assertEmpty(
                array_diff($arr2, $arr1),
                "entries in '{$label2}' not found in '{$label1}'\n" . print_r(array_diff($arr2, $arr1), true)
            );
        };

        // 3. check files in 64x36 and 112x63 folders match
        $assertSameContents($pngAssets1, $pngAssets2, '64x36 files', '112x63 files');

        // 4. check config matches files
        $assertSameContents($svgAssets, $svgLogos, 'svg files', 'logo config');
        $assertSameContents($pngAssets1, $pngLogos, '64x36 files', 'logo config');
        $assertSameContents($pngAssets2, $pngLogos, '112x63 files', 'logo config');
    }

    private function assertAssetExists($nid, $type, $path)
    {
        switch ($type) {
            case 'png':
                $this->assertFileExists("{$path}/64x36/{$nid}.png");
                $this->assertFileExists("{$path}/112x63/{$nid}.png");
                break;
            case 'svg':
                $this->assertFileExists("{$path}/svg/{$nid}.svg");
                break;
            default:
                $this->fail("unknown type? {$type}");
        }
    }
}
