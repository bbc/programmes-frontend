<?php
namespace Tests\App\Ds2013\Presenters\Utilities\SMP;

use App\Builders\ClipBuilder;
use App\Builders\EpisodeBuilder;
use App\Builders\SegmentEventBuilder;
use App\Builders\VersionBuilder;
use App\Ds2013\Presenters\Utilities\SMP\SmpPresenter;
use App\DsShared\Helpers\GuidanceWarningHelper;
use App\DsShared\Helpers\SmpPlaylistHelper;
use App\DsShared\Helpers\StreamableHelper;
use App\ValueObject\CosmosInfo;
use BBC\ProgrammesPagesService\Domain\Enumeration\MediaTypeEnum;
use BBC\ProgrammesPagesService\Domain\ValueObject\Pid;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Controller\Helpers\ProducerVariableHelper;
use App\Controller\Helpers\DestinationVariableHelper;

class SmpPresenterTest extends TestCase
{
    /**
     * This test has to be the first one in this TestCase class.
     * We are testing the value of a static property whose value is
     * increased by one on each class instantiation. Starting from 1.
     * If this test method isn't the first. Then there's a X number
     * of class instantiation ran in other methods and we can't predict
     * the initial/final value.
     *
     * @dataProvider getSmpConfigIncreasesPlayoutIdOnSubsequentCallsDataProvider
     */
    public function testGetSmpConfigIncreasesPlayoutIdOnSubsequentCalls($expectedPlayoutId)
    {
        $smpConfig = $this->presenter()->getSmpConfig();
        $this->assertEquals($expectedPlayoutId, $smpConfig['container']);
    }

    public function getSmpConfigIncreasesPlayoutIdOnSubsequentCallsDataProvider(): array
    {
        return [
            ['#playout-st0000011'],
            ['#playout-st0000012'],
            ['#playout-st0000013'],
        ];
    }

    public function testGetSmpConfig()
    {
        $smpConfig = $this->presenter()->getSmpConfig();

        $this->assertEquals('st000001', $smpConfig['pid']);
    }

    public function testSmpSettingsAutoplay()
    {
        $this->assertTrue($this->presenter()->getSmpConfig()['smpSettings']['autoplay']);
        $this->assertFalse($this->presenter(true, 'audio_video', false)->getSmpConfig()['smpSettings']['autoplay']);
    }

    public function testSmpSettingsUI()
    {
        $this->assertEquals([
            'controls' => ['enabled' => true, 'always' => false],
            'fullscreen' => ['enabled' => true],
        ], $this->presenter(true, MediaTypeEnum::VIDEO)->getSmpConfig()['smpSettings']['ui']);

        $this->assertEquals([
            'controls' => ['enabled' => true, 'always' => true],
            'fullscreen' => ['enabled' => false],
        ], $this->presenter(true, MediaTypeEnum::AUDIO)->getSmpConfig()['smpSettings']['ui']);
    }

    public function testStatsObject()
    {
        $this->assertEquals([
            'appName' => 'programmes',
            'appType' => 'responsive',
            'brandPID' => null,
            'seriesPID' => null,
            'clipPID' => 'st000001',
            'episodePID' => null,
            'destination' => 'ps_programmes_test',
            'producer' => 'BBC',
        ], $this->presenter()->getSmpConfig()['smpSettings']['statsObject']);
    }

    private function presenter(bool $useClip = true, $mediaType = 'audio_video', $autoplay = true): SmpPresenter
    {
        $stubRouter = $this->createConfiguredMock(UrlGeneratorInterface::class, [
            'generate' => 'stubbed/url/from/router',
        ]);

        if ($useClip) {
            $programme = ClipBuilder::any()->with([
                'pid' => new Pid('st000001'),
                'mediaType' => $mediaType,
            ])->build();
        } else {
            $programme = EpisodeBuilder::any()->with([
                'pid' => new Pid('st000001'),
                'mediaType' => $mediaType,
            ])->build();
        }

        $options = [];
        if ($autoplay === false) {
            $options = ['autoplay' => false];
        }

        return new SmpPresenter(
            $programme,
            VersionBuilder::any()->with(['pid' => new Pid('st000002')])->build(),
            [SegmentEventBuilder::any()->build(), SegmentEventBuilder::any()->build()],
            '',
            new SmpPlaylistHelper($this->createMock(GuidanceWarningHelper::class)),
            $stubRouter,
            $this->createConfiguredMock(CosmosInfo::class, ['getAppEnvironment' => 'sandbox']),
            $this->createMock(StreamableHelper::class),
            $this->createConfiguredMock(ProducerVariableHelper::class, ['calculateProducerVariable' => 'BBC']),
            $this->createConfiguredMock(DestinationVariableHelper::class, ['getDestinationFromContext' => 'ps_programmes_test']),
            $options
        );
    }
}
