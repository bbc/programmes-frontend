<?php
declare(strict_types = 1);

namespace Tests\App\Ds2013\Presenters\Section\Segments\SegmentItem;

use App\Builders\CollapsedBroadcastBuilder;
use App\Builders\ContributionBuilder;
use App\Builders\ContributorBuilder;
use App\Builders\MusicSegmentBuilder;
use App\Builders\SegmentBuilder;
use App\Builders\SegmentEventBuilder;
use App\Ds2013\Presenters\Section\Segments\SegmentItem\AbstractMusicSegmentItemPresenter;
use BBC\ProgrammesPagesService\Domain\Entity\Clip;
use BBC\ProgrammesPagesService\Domain\Entity\CollapsedBroadcast;
use BBC\ProgrammesPagesService\Domain\Entity\Episode;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeItem;
use BBC\ProgrammesPagesService\Domain\Entity\Segment;
use BBC\ProgrammesPagesService\Domain\Entity\SegmentEvent;
use Cake\Chronos\Chronos;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AbstractMusicSegmentItemPresenterTest extends TestCase
{
    /** @var ProgrammeItem */
    private $mockContext;

    public function setUp()
    {
        $this->mockContext = $this->getMockBuilder(Episode::class)->disableOriginalConstructor()->getMock();
    }

    public function tearDown(): void
    {
        Chronos::setTestNow();
    }

    /** @dataProvider hasTimingDataProvider */
    public function testHasTiming(bool $expected, ?int $offset, string $timingType)
    {
        $segmentEvent = SegmentEventBuilder::any()->with(['offset' => $offset])->build();
        $stub = $this->getMockForAbstractClass(
            AbstractMusicSegmentItemPresenter::class,
            [
                $this->mockContext,
                $segmentEvent,
                $timingType,
                null,
            ]
        );

        $this->assertSame($expected, $stub->hasTiming());
    }

    public function hasTimingDataProvider(): array
    {
        return [
            'no-offset-unacceptable-timing-type' => [false, null, 'random'],
            'no-offset-acceptable-timing-type' => [false, null, AbstractMusicSegmentItemPresenter::TIMING_POST],
            'offset-unacceptable-timing-type' => [false, 1, AbstractMusicSegmentItemPresenter::TIMING_OFF],
            'offset-acceptable-timing-type' => [true, 1, AbstractMusicSegmentItemPresenter::TIMING_POST],
            'no-offset-post-timing-type' => [true, 1, AbstractMusicSegmentItemPresenter::TIMING_POST],
            'no-offset-pre-timing-type' => [true, 1, AbstractMusicSegmentItemPresenter::TIMING_PRE],
            'no-offset-during-timing-type' => [true, 1, AbstractMusicSegmentItemPresenter::TIMING_DURING],
        ];
    }

    /** @dataProvider getTestPageTypeData */
    public function testPageType($contextClass, $expectedPageType)
    {
        $mockContext = $this->getMockBuilder($contextClass)->disableOriginalConstructor()->getMock();
        $segmentEvent = SegmentEventBuilder::any()->build();
        $presenter = $this->getPresenter(
            $mockContext,
            $segmentEvent,
            'anything',
            null,
            false
        );
        $this->assertEquals($expectedPageType, $presenter->getPageType());
    }

    public function getTestPageTypeData()
    {
        return [
            'episode' => [Episode::class, 'Episode'],
            'clip' => [Clip::class, 'Clip'],
        ];
    }

    public function testTiming()
    {
        $segmentEvent = SegmentEventBuilder::any()->build();
        $stub = $this->getPresenter(
            $this->mockContext,
            $segmentEvent,
            'anything',
            null,
            false
        );

        $this->assertNull($stub->getTiming());
    }

    public function testTimingPre()
    {
        $start = Chronos::now()->setTime(8, 0, 0);
        $collapsedBroadcast = CollapsedBroadcastBuilder::any()->with(['startAt' => $start])->build();
        $segmentEvent = SegmentEventBuilder::any()->with(['offset' => 359])->build();
        $timingType = AbstractMusicSegmentItemPresenter::TIMING_PRE;
        $stub = $this->getPresenter($this->mockContext, $segmentEvent, $timingType, $collapsedBroadcast);

        $this->assertSame('08:05', $stub->getTiming());
    }

    public function testTimingPost()
    {
        $segmentEvent = SegmentEventBuilder::any()->with(['offset' => 3959])->build();
        $timingType = AbstractMusicSegmentItemPresenter::TIMING_POST;
        $stub = $this->getPresenter($this->mockContext, $segmentEvent, $timingType, null);

        $this->assertSame('01:05', $stub->getTiming());
    }

    public function testTimingDuringInTheFuture()
    {
        $start = Chronos::now()->setTime(8, 0, 0);
        Chronos::setTestNow($start->setTime(8, 12, 0));
        $collapsedBroadcast = CollapsedBroadcastBuilder::any()->with(['startAt' => $start])->build();
        $segment = MusicSegmentBuilder::any()->with(['duration' => 300])->build();
        $segmentEvent = SegmentEventBuilder::any()->with(['offset' => 600, 'segment' => $segment])->build();
        $timingType = AbstractMusicSegmentItemPresenter::TIMING_DURING;
        $stub = $this->getPresenter($this->mockContext, $segmentEvent, $timingType, $collapsedBroadcast);

        $this->assertSame('Now', $stub->getTiming());
    }

    public function testTimingDuring()
    {
        $start = Chronos::now()->setTime(8, 0, 0);
        Chronos::setTestNow($start->setTime(8, 19, 0));
        $collapsedBroadcast = CollapsedBroadcastBuilder::any()->with(['startAt' => $start])->build();
        $segment = MusicSegmentBuilder::any()->with(['duration' => 300])->build();
        $segmentEvent = SegmentEventBuilder::any()->with(['offset' => 600, 'segment' => $segment])->build();
        $timingType = AbstractMusicSegmentItemPresenter::TIMING_DURING;
        $stub = $this->getPresenter($this->mockContext, $segmentEvent, $timingType, $collapsedBroadcast);

        $this->assertSame('4 minutes ago', $stub->getTiming());
    }

    public function testDefaultImage()
    {
        $segmentEvent = SegmentEventBuilder::any()->build();

        $stub = $this->getPresenter($this->mockContext, $segmentEvent, 'anything', null);
        $stub->expects($this->any())
            ->method('getPrimaryContribution')
            ->willReturn(null);

        $this->assertSame('https://ichef.bbci.co.uk/images/ic/96x96/p01c9cjb.png', $stub->getImagePlaceholderUrl());
    }

    /**
     * @param ProgrammeItem $context
     * @param SegmentEvent $segmentEvent
     * @param string $timingType
     * @param CollapsedBroadcast|null $collapsedBroadcast
     * @param bool $hasTiming
     * @return AbstractMusicSegmentItemPresenter|MockObject
     */
    private function getPresenter(
        ProgrammeItem $context,
        SegmentEvent $segmentEvent,
        string $timingType,
        ?CollapsedBroadcast $collapsedBroadcast,
        bool $hasTiming = true
    ) {
        $stub = $this->getMockForAbstractClass(
            AbstractMusicSegmentItemPresenter::class,
            [
                $context,
                $segmentEvent,
                $timingType,
                $collapsedBroadcast,
            ],
            '',
            true,
            true,
            true,
            [
                'hasTiming',
                'getPrimaryContribution',
            ]
        );
        $stub->expects($this->any())
            ->method('hasTiming')
            ->willReturn($hasTiming);

        return $stub;
    }
}
