<?php
declare(strict_types=1);

namespace Tests\App\DsAmen\Presenters\Domain\CoreEntity\CollapsedBroadcast;

use App\Builders\CollapsedBroadcastBuilder;
use App\Builders\EpisodeBuilder;
use App\Builders\MasterBrandBuilder;
use App\Builders\NetworkBuilder;
use App\Builders\ServiceBuilder;
use App\DsAmen\Presenters\Domain\CoreEntity\Base\SubPresenter\BaseBodyPresenter;
use App\DsAmen\Presenters\Domain\CoreEntity\CollapsedBroadcast\CollapsedBroadcastPresenter;
use App\DsAmen\Presenters\Domain\CoreEntity\CollapsedBroadcast\SubPresenter\CtaPresenter;
use App\DsAmen\Presenters\Domain\CoreEntity\CollapsedBroadcast\SubPresenter\DetailsPresenter;
use App\DsAmen\Presenters\Domain\CoreEntity\CollapsedBroadcast\SubPresenter\LiveCtaPresenter;
use App\DsAmen\Presenters\Domain\CoreEntity\Shared\SubPresenter\BodyPresenter;
use App\DsAmen\Presenters\Domain\CoreEntity\Shared\SubPresenter\ImagePresenter;
use App\DsAmen\Presenters\Domain\CoreEntity\Shared\SubPresenter\StreamableCtaPresenter;
use App\DsAmen\Presenters\Domain\CoreEntity\Shared\SubPresenter\TitlePresenter;
use App\DsShared\Helpers\BroadcastNetworksHelper;
use App\DsShared\Factory\HelperFactory;
use App\DsShared\Helpers\LiveBroadcastHelper;
use BBC\ProgrammesPagesService\Domain\Entity\CollapsedBroadcast;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeItem;
use BBC\ProgrammesPagesService\Domain\Enumeration\MediaTypeEnum;
use BBC\ProgrammesPagesService\Domain\Enumeration\NetworkMediumEnum;
use BBC\ProgrammesPagesService\Domain\ValueObject\Nid;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Translation\TranslatorInterface;
use Tests\App\BaseTemplateTestCase;
use PHPUnit_Framework_MockObject_MockObject;

class CollapsedBroadcastPresenterTest extends BaseTemplateTestCase
{
    /** @var UrlGenerator|PHPUnit_Framework_MockObject_MockObject */
    private $mockRouter;

    /** @var HelperFactory|PHPUnit_Framework_MockObject_MockObject */
    private $mockHelperFactory;

    /** @var BroadcastNetworksHelper  */
    private $mockBroadcastNetworksHelper;

    /** @var CollapsedBroadcast|PHPUnit_Framework_MockObject_MockObject */
    private $mockCollapsedBroadcast;

    /** @var ProgrammeItem|PHPUnit_Framework_MockObject_MockObject */
    private $mockProgrammeItem;

    public function setUp()
    {
        $this->mockRouter = $this->createMock(UrlGenerator::class);
        $this->mockCollapsedBroadcast = $this->createMockCollapsedBroadcast();

        $this->mockBroadcastNetworksHelper = new BroadcastNetworksHelper($this->createMock(TranslatorInterface::class));

        $this->mockHelperFactory  = $this->createMock(HelperFactory::class);
        $this->mockHelperFactory
            ->method('getBroadcastNetworksHelper')
            ->willReturn($this->mockBroadcastNetworksHelper);
    }

    public function testGetBodyPresenterReturnsInstanceOfSharedBodyPresenter(): void
    {
        $cbPresenter = new CollapsedBroadcastPresenter(
            $this->mockCollapsedBroadcast,
            $this->mockRouter,
            self::$translator,
            $this->mockHelperFactory
        );
        $this->assertInstanceOf(BodyPresenter::class, $cbPresenter->getBodyPresenter());
    }

    public function testGetCtaPresenterReturnsStreamableWhenStreamable(): void
    {
        $this->setPlayableAndLiveExpectations(true, false);

        $cbPresenter = new CollapsedBroadcastPresenter(
            $this->mockCollapsedBroadcast,
            $this->mockRouter,
            self::$translator,
            $this->mockHelperFactory
        );
        $this->assertInstanceOf(StreamableCtaPresenter::class, $cbPresenter->getCtaPresenter());
    }

    public function testGetCtaPresenterReturnsLiveWhenLive(): void
    {
        $this->setPlayableAndLiveExpectations(false, true);

        $cbPresenter = new CollapsedBroadcastPresenter(
            $this->mockCollapsedBroadcast,
            $this->mockRouter,
            self::$translator,
            $this->mockHelperFactory
        );
        $this->assertInstanceOf(LiveCtaPresenter::class, $cbPresenter->getCtaPresenter());
    }

    public function testGetCtaPresenterReturnsNullWhenNotStreamableOrLive(): void
    {
        $this->setPlayableAndLiveExpectations(false, false);

        $cbPresenter = new CollapsedBroadcastPresenter(
            $this->mockCollapsedBroadcast,
            $this->mockRouter,
            self::$translator,
            $this->mockHelperFactory
        );
        $this->assertNull($cbPresenter->getCtaPresenter());
    }

    public function testGetDetailsPresenterReturnsInstanceOfCollapsedBroadcastDetailsPresenter(): void
    {
        $cbPresenter = new CollapsedBroadcastPresenter(
            $this->mockCollapsedBroadcast,
            $this->mockRouter,
            self::$translator,
            $this->mockHelperFactory
        );
        $this->assertInstanceOf(DetailsPresenter::class, $cbPresenter->getDetailsPresenter());
    }

    public function testDetailsPresenterLinks()
    {
        $getDetailsPresenter = function ($broadcast): DetailsPresenter {
            return (
                new CollapsedBroadcastPresenter(
                    $broadcast,
                    $this->mockRouter,
                    self::$translator,
                    $this->mockHelperFactory,
                    ['show_image' => true]
                )
            )->getDetailsPresenter();
        };

        // radio should not link to NHP
        $broadcast = $this->buildCBroadcastWithNetwork('bbc_radio_one', 'radio1', 'radio 1', NetworkMediumEnum::RADIO);
        $c = $this->presenterCrawler($getDetailsPresenter($broadcast));
        $this->assertEquals(0, $c->filter('a')->count(), 'should have 0 links');

        // TV should have NHP link
        $broadcast = $this->buildCBroadcastWithNetwork('bbc_one', 'bbcone', 'bbc 1', NetworkMediumEnum::TV);
        $c = $this->presenterCrawler($getDetailsPresenter($broadcast));
        $this->assertEquals(1, $c->filter('a')->count(), 'should have 1 link');
    }

    public function testBodyPresenterLinks()
    {
        $getBodyPresenter = function ($broadcast): BaseBodyPresenter {
            return (
                new CollapsedBroadcastPresenter(
                    $broadcast,
                    $this->mockRouter,
                    self::$translator,
                    $this->mockHelperFactory,
                    [
                        'show_image' => true,
                        'body_options' => [
                            'show_synopsis' => true,
                            'show_masterbrand' => true,
                        ],
                    ]
                )
            )->getBodyPresenter();
        };

        // radio should not link to NHP
        $broadcast = $this->buildCBroadcastWithNetwork('bbc_radio_one', 'radio1', 'radio 1', NetworkMediumEnum::RADIO);
        $c = $this->presenterCrawler($getBodyPresenter($broadcast));
        $this->assertEquals(0, $c->filter('a')->count(), 'should have no NHP link');

        // TV should have NHP link
        $broadcast = $this->buildCBroadcastWithNetwork('bbc_one', 'bbcone', 'bbc 1', NetworkMediumEnum::TV);
        $c = $this->presenterCrawler($getBodyPresenter($broadcast));
        $this->assertEquals(1, $c->filter('a')->count(), 'should have 1 link');
    }

    public function testGetImagePresenterReturnsInstanceOfSharedImagePresenter(): void
    {
        $cbPresenter = new CollapsedBroadcastPresenter(
            $this->mockCollapsedBroadcast,
            $this->mockRouter,
            self::$translator,
            $this->mockHelperFactory
        );
        $this->assertInstanceOf(ImagePresenter::class, $cbPresenter->getImagePresenter());
    }

    public function testGetTitlePresenterReturnsInstanceOfSharedTitlePresenter(): void
    {
        $cbPresenter = new CollapsedBroadcastPresenter(
            $this->mockCollapsedBroadcast,
            $this->mockRouter,
            self::$translator,
            $this->mockHelperFactory
        );
        $this->assertInstanceOf(TitlePresenter::class, $cbPresenter->getTitlePresenter());
    }

    /** @dataProvider showStandaloneCtaProvider */
    public function testShowStandaloneCta(bool $isOnAir, bool $hasPlayableDestination, array $options, bool $expected): void
    {
        $this->setPlayableAndLiveExpectations($hasPlayableDestination, $isOnAir);

        $cbPresenter = new CollapsedBroadcastPresenter(
            $this->mockCollapsedBroadcast,
            $this->mockRouter,
            self::$translator,
            $this->mockHelperFactory,
            $options
        );

        $this->assertEquals($expected, $cbPresenter->showStandaloneCta());
    }

    public function showStandaloneCtaProvider(): array
    {
        return [
            'Show Standalone CTA when on air and not showing image' => [true, false, ['show_image' => false], true],
            'Show Standalone CTA when streamable and not showing image' => [false, true, ['show_image' => false], true],
            'Show Standalone CTA when, streamable, on air and not showing image' => [true, true, ['show_image' => false], true],
            'Do not show Standalone CTA when showing image' => [true, true, ['show_image' => true], false],
        ];
    }

    /** @dataProvider showWatchFromStartCtaProvider */
    public function testShowWatchFromStartCta(bool $isOnAir, bool $isRadio, bool $expected): void
    {
        $this->setPlayableAndLiveExpectations(false, $isOnAir);

        $this->mockProgrammeItem->expects($this->atLeastOnce())
            ->method('isRadio')
            ->willReturn($isRadio);

        $cbPresenter = new CollapsedBroadcastPresenter(
            $this->mockCollapsedBroadcast,
            $this->mockRouter,
            self::$translator,
            $this->mockHelperFactory
        );

        $this->assertEquals($expected, $cbPresenter->showWatchFromStartCta());
    }

    public function showWatchFromStartCtaProvider(): array
    {
        return [
            'Show Watch from start CTA when on air and not Radio' => [true, false, true],
            'Do not show Watch from start CTA when not on air' => [false, false, false],
            'Do not show Watch from start CTA when programme is from radio' => [false, true, false],
        ];
    }

    /**
     * [ CTA duration ]. CollapsedBroadcastPresenter configure properly CtaPresenter when programme is TV
     *
     * @dataProvider showImageOptionProvider
     */
    public function testShowDurationIsTrueForTVProgrammes(bool $givenShowImageOption)
    {
        $ctaPresenter = $this->buildCollapsedBroadcastPresenter(
            EpisodeBuilder::anyTVEpisode()->with(['isStreamable' => true, 'mediaType' => MediaTypeEnum::VIDEO])->build(),
            $givenShowImageOption
        )->getCtaPresenter();

        $this->assertTrue($ctaPresenter->getOption('show_duration'));
    }

    /**
     * [ CTA duration ]. CollapsedBroadcastPresenter configure properly CtaPresenter when programme is RADIO
     *
     * @dataProvider showImageOptionProvider
     */
    public function testShowDurationIsFalseForRadioProgrammes(bool $givenShowImageOption)
    {
        $ctaPresenter = $this->buildCollapsedBroadcastPresenter(
            EpisodeBuilder::anyRadioEpisode()->with(['isStreamable' => true, 'mediaType' => MediaTypeEnum::AUDIO])->build(),
            $givenShowImageOption
        )->getCtaPresenter();

        $this->assertEquals($givenShowImageOption, $ctaPresenter->getOption('show_duration'));
    }

    public function showImageOptionProvider()
    {
        return [
            'show_image option: TRUE' =>  [true],
            'show_image option: FALSE' => [false],
        ];
    }

    /**
     * helpers
     */
    private function buildCollapsedBroadcastPresenter(ProgrammeItem $episode, bool $givenShowImageOption) :CollapsedBroadcastPresenter
    {
        $dummy1 = $this->mockRouter;
        $dummy2 = self::$translator;
        $dummy3 = $this->mockHelperFactory;

        return new CollapsedBroadcastPresenter(
            CollapsedBroadcastBuilder::any()->with(['programmeItem' => $episode])->build(),
            $dummy1,
            $dummy2,
            $dummy3,
            ['show_image' => $givenShowImageOption]
        );
    }

    private function createMockCollapsedBroadcast()
    {
        $cb = $this->createMock(CollapsedBroadcast::class);
        return $cb;
    }

    private function setPlayableAndLiveExpectations(bool $hasPlayableDestination, bool $isLive)
    {
        $this->mockProgrammeItem = $this->createMock(ProgrammeItem::class);

        $this->mockProgrammeItem->expects($this->any())
            ->method('hasPlayableDestination')
            ->willReturn($hasPlayableDestination);

        $this->mockCollapsedBroadcast->expects($this->atLeastOnce())
            ->method('getProgrammeItem')
            ->willReturn($this->mockProgrammeItem);

        $mockLiveBroadcastHelper = $this->createMock(LiveBroadcastHelper::class);
        $mockLiveBroadcastHelper->expects($this->any())
            ->method('isWatchableLive')
            ->with($this->mockCollapsedBroadcast, false)
            ->willReturn($isLive);

        $this->mockHelperFactory->expects($this->any())
            ->method('getLiveBroadcastHelper')
            ->willReturn($mockLiveBroadcastHelper);
    }

    private function buildCBroadcastWithNetwork($nidStr, $urlKey, $name, $medium)
    {
        $method = ($medium === NetworkMediumEnum::RADIO) ? 'anyRadioService' : 'anyTVService';
        $network1 = NetworkBuilder::any()
            ->with([
                'services' => [
                    ServiceBuilder::$method()->build(),
                    ServiceBuilder::$method()->build(),
                ],
                'medium' => $medium,
                'name' => $name,
                'urlKey' => $urlKey,
                'nid' => new Nid($nidStr),
            ])
            ->build();

        return CollapsedBroadcastBuilder::any()
            ->with([
                'services' => [
                    ServiceBuilder::any()->with(['network' => $network1])->build(),
                ],
                'programmeItem' => EpisodeBuilder::any()
                    ->with([
                        'masterBrand' => MasterBrandBuilder::any()->with(['network' => $network1])->build(),
                    ])
                    ->build(),
            ])
            ->build();
    }
}
