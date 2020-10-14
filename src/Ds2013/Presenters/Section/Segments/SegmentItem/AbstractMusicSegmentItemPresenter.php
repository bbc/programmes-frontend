<?php
declare(strict_types=1);

namespace App\Ds2013\Presenters\Section\Segments\SegmentItem;

use BBC\ProgrammesPagesService\Domain\Entity\CollapsedBroadcast;
use BBC\ProgrammesPagesService\Domain\Entity\Contribution;
use BBC\ProgrammesPagesService\Domain\Entity\Episode;
use BBC\ProgrammesPagesService\Domain\Entity\MusicSegment;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeItem;
use BBC\ProgrammesPagesService\Domain\Entity\SegmentEvent;

abstract class AbstractMusicSegmentItemPresenter extends AbstractSegmentItemPresenter
{
    const TIMING_PRE = 'pre';
    const TIMING_POST = 'post';
    const TIMING_DURING = 'during';
    const TIMING_OFF = 'off';

    /** @var Contribution[] */
    protected $otherContributions = [];

    /** @return Contribution[] */
    protected $primaryContributions = [];

    /** @var MusicSegment */
    protected $segment;

    /** @var Contribution[] */
    protected $featuredContributions = [];

    /** @var Contribution[] */
    protected $versusContributions = [];

    /** @var string */
    private $timingType;

    /** @var CollapsedBroadcast|null */
    private $collapsedBroadcast;

    /** @var ProgrammeItem */
    private $context;

    public function __construct(
        ProgrammeItem $context,
        SegmentEvent $segmentEvent,
        string $timingType,
        ?CollapsedBroadcast $collapsedBroadcast,
        array $options = []
    ) {
        parent::__construct($segmentEvent, $options);
        $this->timingType = $timingType;
        $this->collapsedBroadcast = $collapsedBroadcast;
        $this->context = $context;

        /** @var MusicSegment $segment */
        $segment = $this->segmentEvent->getSegment();
        $this->segment = $segment;

        $this->setupContributions();
    }

    /**
     * @return string
     */
    public function getPageType(): string
    {
        if ($this->context instanceof Episode) {
            return 'Episode';
        }

        return 'Clip';
    }

    public function getImageUrl(): string
    {
        if ($this->getPrimaryContribution() && $this->getPrimaryContribution()->getContributor()->getMusicBrainzId()) {
            return 'https://ichef.bbci.co.uk/music/images/artists/96x96/' . $this->getPrimaryContribution()->getContributor()->getMusicBrainzId() . '.jpg';
        }

        return 'https://ichef.bbci.co.uk/images/ic/96x96/p01c9cjb.png';
    }

    /** @return Contribution[] */
    public function getOtherContributions(): array
    {
        return $this->otherContributions;
    }

    /** @return Contribution[] */
    public function getFeaturedContributions(): array
    {
        return $this->featuredContributions;
    }

    /** @return Contribution[] */
    public function getVersusContributions(): array
    {
        return $this->versusContributions;
    }

    /**
     * @return MusicSegment
     */
    public function getSegment(): MusicSegment
    {
        return $this->segment;
    }

    public function getPrimaryContribution(): ?Contribution
    {
        $primaryContribution = reset($this->primaryContributions);

        return $primaryContribution ?: null;
    }

    /** @return Contribution[] */
    public function getPrimaryContributions(): array
    {
        return $this->primaryContributions;
    }

    public function hasRecordDetails(): bool
    {
        return $this->segment->getReleaseTitle() || $this->segment->getRecordLabel() || $this->segment->getCatalogueNumber() || $this->segment->getTrackNumber();
    }

    public function getTemplatePath(): string
    {
        return '@Ds2013/Presenters/Section/Segments/SegmentItem/music.html.twig';
    }

    public function getTemplateVariableName(): string
    {
        return 'music';
    }

    public function hasTiming(): bool
    {
        return in_array($this->timingType, [self::TIMING_POST, self::TIMING_PRE, self::TIMING_DURING]) &&
            !is_null($this->segmentEvent->getOffset());
    }

    public function getTiming(): ?string
    {
        if (!$this->hasTiming()) {
            return null;
        }

        // 24-hour formatted time of when the segment starts
        if ($this->timingType === self::TIMING_PRE) {
            return $this->collapsedBroadcast->getStartAt()->addSeconds($this->segmentEvent->getOffset())->format('H:i');
        }

        // minutes:hours into the programme
        if ($this->timingType === self::TIMING_POST) {
            $minutes = ($this->segmentEvent->getOffset() / 60);
            $hours = floor($minutes / 60);
            $minutes = $minutes % 60;
            return str_pad((string) $hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
        }

        // human-readable time since the end of the segment ('Now', '3 minutes ago', etc) for live things
        $segmentEndTime = $this->collapsedBroadcast->getStartAt()->addSeconds($this->segmentEvent->getOffset() + $this->segment->getDuration());

        if ($segmentEndTime->isFuture()) {
            return 'Now';
        }

        return $segmentEndTime->diffForHumans();
    }

    abstract protected function setupContributions(): void;

    private function getArtistWithContributions(): string
    {
        $artistWithContributions = '';
        if (!$this->getVersusContributions() && !$this->getPrimaryContributions()) {
            return $artistWithContributions;
        }

        if ($this->getPrimaryContributions()) {
            $artistWithContributions = $this->concatContributorNames(' & ', $this->getPrimaryContributions());
        }

        $artistWithContributions .= ($this->getVersusContributions() && $this->getPrimaryContributions()) ? ' vs ' : '';

        if ($this->getVersusContributions()) {
            $artistWithContributions .= $this->concatContributorNames(' & ', $this->getVersusContributions());
        }

        return $artistWithContributions;
    }

    private function getTrackTitleWithContributions(): string
    {
        if (!$this->segment->getTitle()) {
            return 'Untitled';
        }
        if (!$this->getFeaturedContributions()) {
            return $this->segment->getTitle();
        }
        return $this->segment->getTitle() . ' (feat. ' . $this->concatContributorNames(' & ', $this->getFeaturedContributions()) . ')';
    }

    /**
     * @param string $glue
     * @param Contribution[] $contributions
     * @return string
     */
    private function concatContributorNames(string $glue, array $contributions): string
    {
        $names = [];
        foreach ($contributions as $contribution) {
            $names[] = $contribution->getContributor()->getName();
        }
        return implode($glue, $names);
    }
}
