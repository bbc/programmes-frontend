<?php
declare(strict_types=1);

namespace Tests\App\Ds2013\Presenters\Domain\BroadcastEvent;

use App\Builders\CollapsedBroadcastBuilder;
use App\Builders\EpisodeBuilder;
use App\Builders\NetworkBuilder;
use App\Builders\ServiceBuilder;
use App\Ds2013\Presenters\Domain\BroadcastEvent\BroadcastEventPresenter;
use App\DsShared\Helpers\BroadcastNetworksHelper;
use App\DsShared\Helpers\LiveBroadcastHelper;
use App\DsShared\Helpers\LocalisedDaysAndMonthsHelper;
use BBC\ProgrammesPagesService\Domain\Entity\CollapsedBroadcast;
use BBC\ProgrammesPagesService\Domain\Entity\Network;
use BBC\ProgrammesPagesService\Domain\Entity\Service;
use BBC\ProgrammesPagesService\Domain\Enumeration\NetworkMediumEnum;
use BBC\ProgrammesPagesService\Domain\ValueObject\Nid;
use BBC\ProgrammesPagesService\Domain\ValueObject\Sid;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Symfony\Component\Translation\TranslatorInterface;
use Tests\App\BaseTemplateTestCase;

class BroadcastEventPresenterTest extends BaseTemplateTestCase
{
    private $mockCollapsedBroadcast;
    private $mockBroadcastNetworksHelper;
    private $mockLocalisedDaysAndMonthsHelper;

    public function setUp()
    {
        $this->mockCollapsedBroadcast = $this->createMock(CollapsedBroadcast::class);
        $this->mockBroadcastNetworksHelper = new BroadcastNetworksHelper($this->createMock(TranslatorInterface::class));
        $this->mockLocalisedDaysAndMonthsHelper = $this->createMock(LocalisedDaysAndMonthsHelper::class);
    }

    public function servicesTypeProvider()
    {
        return [
            [$this->getListServicesDoingLiveBroadcasts(), true],
            [$this->getListServicesNotDoingLiveBroadcasts(), false],
        ];
    }

    /**
     * [ watch from the start ]. Know when a watchable broadcast should own a simulcast url
     */
    public function testGivenWatchableAndEpisodeIsTv()
    {
        $routerMock = $this->createMock(UrlGeneratorInterface::class);
        $routerMock
            ->expects($this->once())
            ->method('generate')
            ->with('iplayer_live', ['sid' => 'bbcone', 'area' => 'scotland', 'rewindTo' => 'current'])
            ->willReturn('any string');

        $cBroadcast = $this->buildLiveBroadcastWith($this->getListServicesDoingLiveBroadcasts(), 'tv');

        $this->buildPresenterForBroadcast($cBroadcast, $routerMock)->getRewindUrl();
    }

    public function testGivenWatchableAndEpisodeIsRadio()
    {
        $routerMock = $this->createMock(UrlGeneratorInterface::class);
        $routerMock
            ->expects($this->never())
            ->method('generate');

        $cBroadcast = $this->buildLiveBroadcastWith($this->getListServicesDoingLiveBroadcasts(), 'radio');

        $this->buildPresenterForBroadcast($cBroadcast, $routerMock)->getRewindUrl();
    }

    /**
     * [ network breakdown ]. If CBroadcasts has networks, then we can get a first network
     */
    public function testGetFirstNetworkWhenExistNetworks()
    {
        // setup
        $dummy1 = $this->createMock(LocalisedDaysAndMonthsHelper::class);
        $dummy2 = $this->createMock(LiveBroadcastHelper::class);
        $dummy3 = $this->createMock(UrlGeneratorInterface::class);

        list($network1, $network2, $cBroadcasts) = $this->buildCBroadcastsWithNetworksAndServices();
        $cBroadcastEventPresenter = new BroadcastEventPresenter(
            $cBroadcasts,
            $this->mockBroadcastNetworksHelper,
            $dummy1,
            $dummy2,
            $dummy3
        );

        // exercise
        $firstNetwork = $cBroadcastEventPresenter->getMainBroadcastNetwork();

        $this->assertEquals($network1, $firstNetwork);
    }

    /**
     * [ network breakdown ]. If CBroadcasts has no networks, then we get null
     */
    public function testGetFirstNetworkWhenDoesntExistNetworks()
    {
        // setup
        $dummy1 = $this->createMock(LocalisedDaysAndMonthsHelper::class);
        $dummy2 = $this->createMock(LiveBroadcastHelper::class);
        $dummy3 = $this->createMock(UrlGeneratorInterface::class);

        $cBroadcasts = CollapsedBroadcastBuilder::any()->with(['services' => [
            ServiceBuilder::any()->build(),
            ServiceBuilder::any()->build(),
            ],
        ])->build();

        $cBroadcastEventPresenter = new BroadcastEventPresenter(
            $cBroadcasts,
            $this->mockBroadcastNetworksHelper,
            $dummy1,
            $dummy2,
            $dummy3
        );

        // exercise
        $firstNetwork = $cBroadcastEventPresenter->getMainBroadcastNetwork();

        $this->assertNull($firstNetwork);
    }

    public function testBroadcastNetworkLinks()
    {
        $routeCollectionBuilder = new RouteCollectionBuilder();
        $routeCollectionBuilder->add('/{networkUrlKey}', '', 'network');
        $router = new UrlGenerator($routeCollectionBuilder->build(), new RequestContext());

        // Radio network should not link to NHP because radio hasn't
        [, , $broadcast] = $this->buildCBroadcastsWithSpecificNetwork('bbc_radio_one', 'radio1', 'radio 1', NetworkMediumEnum::RADIO);
        $presenter = $this->buildPresenterForBroadcast($broadcast, $router);
        $c = $this->presenterCrawler($presenter);
        $this->assertEquals(0, $c->filter('a')->count(), 'network should not be linked');

        // TV network should link to NHP
        [, , $broadcast] = $this->buildCBroadcastsWithSpecificNetwork('bbc_one', 'one', 'bbc one 1', NetworkMediumEnum::TV);
        $presenter = $this->buildPresenterForBroadcast($broadcast, $router);
        $c = $this->presenterCrawler($presenter);
        $this->assertEquals(2, $c->filter('a')->count(), 'network should be linked');
        $this->assertEquals(1, $c->filter('a>img')->count(), 'network logo should be linked');
    }

    /**
     * Build watchable broadcast to exercise simulcast feature.
     *
     * @param Service[] $services
     * @param string $episodeType
     */
    private function buildLiveBroadcastWith(array $services, string $episodeType = 'any'): CollapsedBroadcast
    {
        $episode = null;
        if ($episodeType == 'tv') {
            $episode = EpisodeBuilder::anyTVEpisode()->build();
        } elseif ($episodeType == 'radio') {
            $episode = EpisodeBuilder::anyRadioEpisode()->build();
        } else {
            $episode = EpisodeBuilder::any()->build();
        }

        return CollapsedBroadcastBuilder::anyLive()->with([
            'programmeItem' => $episode,
            'isBlanked' => false,
            'services' => $services,
        ])->build();
    }

    private function buildPresenterForBroadcast(CollapsedBroadcast $cBroadcast, $routerMock): BroadcastEventPresenter
    {
        return new BroadcastEventPresenter(
            $cBroadcast,
            $this->mockBroadcastNetworksHelper,
            $this->mockLocalisedDaysAndMonthsHelper,
            new LiveBroadcastHelper($routerMock),
            $routerMock
        );
    }

    /**
     * @return Service[]
     */
    private function getListServicesDoingLiveBroadcasts(): array
    {
        return [
            ServiceBuilder::any()->with(['sid' => new Sid('bbc_two_wales_digital')])->build(),
            ServiceBuilder::any()->with(['sid' => new Sid('bbc_one_scotland')])->build(),
        ];
    }

    /**
     * @return Service[]
     */
    private function getListServicesNotDoingLiveBroadcasts(): array
    {
        return [
            ServiceBuilder::any()->with(['sid' => new Sid('bbc_network_without_live_broadcast1')])->build(),
            ServiceBuilder::any()->with(['sid' => new Sid('bbc_network_without_live_broadcast2')])->build(),
        ];
    }

    private function buildCBroadcastsWithSpecificNetwork($nidStr, $urlKey, $name, $medium)
    {
        $method = ($medium === NetworkMediumEnum::RADIO) ? 'anyRadioService' : 'anyTVService';
        $network = NetworkBuilder::any()->with([
            'services' => [
                ServiceBuilder::$method()->build(),
                ServiceBuilder::$method()->build(),
            ],
            'medium' => $medium,
            'name' => $name,
            'urlKey' => $urlKey,
            'nid' => new Nid($nidStr),
        ])->build();
        return $this->buildCBroadcastsWithNetworksAndServices($network);
    }

    private function buildCBroadcastsWithNetworksAndServices($specificNetwork = null): array
    {
        $network1 = $specificNetwork ?: NetworkBuilder::any()->with(['services' => [
            ServiceBuilder::anyTVService()->build(),
            ServiceBuilder::anyTVService()->build(),
        ],
        ])->build();

        $network2 = NetworkBuilder::any()->with(['services' => [
            ServiceBuilder::anyTVService()->build(),
            ServiceBuilder::anyTVService()->build(),
        ],
        ])->build();

        $cBroadcasts = CollapsedBroadcastBuilder::any()->with(['services' => [
            ServiceBuilder::any()->with(['network' => $network1])->build(),
            ServiceBuilder::any()->with(['network' => $network2])->build(),
        ],
        ])->build();

        return [$network1, $network2, $cBroadcasts];
    }
}
