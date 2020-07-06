<?php
declare(strict_types = 1);

namespace App\Ds2013\Presenters\Utilities\SMP;

use App\Controller\Helpers\ProducerVariableHelper;
use App\Controller\Helpers\DestinationVariableHelper;
use App\Ds2013\Presenter;
use App\DsShared\Helpers\SmpPlaylistHelper;
use App\DsShared\Helpers\StreamableHelper;
use App\ValueObject\CosmosInfo;
use BBC\ProgrammesPagesService\Domain\Entity\Clip;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeItem;
use BBC\ProgrammesPagesService\Domain\Entity\Version;
use BBC\ProgrammesPagesService\Domain\Enumeration\MediaTypeEnum;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SmpPresenter extends Presenter
{
    const SMP_APP_NAME = 'programmes';

    private static $smpInstanceCounter = 0;

    protected $options = [
        'sizes' => [
            0 => 640,
            695 => 976,
        ],
        'srcsets' => [80, 160, 320, 480, 640, 768, 896, 976, 1008],
        'default_width' => 640,
        'uas' => true,
        'autoplay' => true,
        'audio_to_playspace' => true,
    ];

    /** @var ProgrammeItem */
    private $programmeItem;

    /** @var Version */
    private $streamableVersion;

    /** @var array */
    private $segmentEvents;

    /** @var SmpPlaylistHelper */
    private $smpPlaylistHelper;

    /** @var string */
    private $analyticsCounterName;

    /** @var UrlGeneratorInterface */
    private $router;

    /** @var CosmosInfo */
    private $cosmosInfo;

    private $streamableHelper;

    /** @var string */
    private $containerId;

    /** @var ProducerVariableHelper */
    private $producerVariableHelper;

    /** @var DestinationVariableHelper */
    private $destinationVariableHelper;

    /** @var string */
    private $appEnvironment;

    public function __construct(
        ProgrammeItem $programmeItem,
        ?Version $streamableVersion,
        array $segmentEvents,
        ?string $analyticsCounterName,
        SmpPlaylistHelper $smpPlaylistHelper,
        UrlGeneratorInterface $router,
        CosmosInfo $cosmosInfo,
        StreamableHelper $streamableHelper,
        ProducerVariableHelper $producerVariableHelper,
        DestinationVariableHelper $destinationVariableHelper,
        array $options = []
    ) {
        parent::__construct($options);
        $this->programmeItem = $programmeItem;
        $this->streamableVersion = $streamableVersion;
        $this->segmentEvents = $segmentEvents;
        $this->smpPlaylistHelper = $smpPlaylistHelper;
        $this->analyticsCounterName = $analyticsCounterName;
        $this->router = $router;
        $this->cosmosInfo = $cosmosInfo;
        $this->streamableHelper = $streamableHelper;
        self::$smpInstanceCounter++;
        $this->containerId = 'playout-' . (string) $this->programmeItem->getPid() . self::$smpInstanceCounter;
        $this->producerVariableHelper = $producerVariableHelper;
        $this->destinationVariableHelper = $destinationVariableHelper;
        $this->appEnvironment = $cosmosInfo->getAppEnvironment();
    }

    public function getProgrammeItem(): ProgrammeItem
    {
        return $this->programmeItem;
    }

    public function getContainerId()
    {
        return $this->containerId;
    }

    /**
     * @return string[]
     */
    public function getSmpConfig(): array
    {
        $smpPlaylist = $this->smpPlaylistHelper->getSmpPlaylist(
            $this->programmeItem,
            $this->streamableVersion
        );

        $seriesPid = null;
        $episodePid = null;
        foreach ($this->programmeItem->getAncestry() as $parent) {
            if ($parent->getType() == 'episode') {
                $episodePid = (string) $parent->getPid();
            } elseif ($parent->getType() == 'series') {
                $seriesPid = (string) $parent->getPid();
            }
        }

        $smpConfig = [
            'container' => '#' . $this->getContainerId(),
            'pid' => (string) $this->programmeItem->getPid(),
            'smpSettings' => [
                'autoplay' => $this->options['autoplay'],
                'ui' => [
                    'controls' => [
                        'enabled' => true,
                        'always' => $this->programmeItem->getMediaType() ==  MediaTypeEnum::AUDIO ? true: false,
                    ],
                    'fullscreen' => [
                        'enabled' => $this->programmeItem->getMediaType() ==  MediaTypeEnum::AUDIO ? false: true,
                    ],
                ],
                'playlistObject' => $smpPlaylist,
                'externalEmbedUrl' => $this->router->generate('programme_player', ['pid' => (string) $this->programmeItem->getPid()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            'markers' => $this->smpPlaylistHelper->getMarkers($this->segmentEvents, $this->programmeItem),
        ];

        if (!empty($this->analyticsCounterName)) {
            $smpConfig['smpSettings']['counterName'] = $this->analyticsCounterName;
        }

        $brand = null;
        if ($this->programmeItem->getTleo()->isTlec()) {
            $brand = $this->programmeItem->getTleo();
        }

            $smpConfig['smpSettings']['statsObject'] = [

                //Vulcan stats
                'destination' => $this->destinationVariableHelper->getDestinationFromContext($this->programmeItem, $this->appEnvironment),
                'brandPID' => $brand ? (string) $brand->getPid() : null,
                'appName' => self::SMP_APP_NAME,
                'seriesPID' => $seriesPid,
                'appType' => 'responsive',
                'clipPID' => (string) $this->programmeItem->getPid(),
                'producer' => $this->producerVariableHelper->calculateProducerVariable($this->programmeItem),
                'episodePID' => $episodePid,
            ];

            return $smpConfig;
    }

    public function getFactoryOptions(): array
    {
        return [
            'uasConfig' => $this->options['uas'] ? $this->getUasConfig() : null,
        ];
    }

    public function shouldStreamViaPlayspace(): bool
    {
        if ($this->options['audio_to_playspace'] && $this->streamableHelper->shouldStreamViaPlayspace($this->programmeItem)) {
            return true;
        }
        return false;
    }

    public function hasStreamableVersion(): bool
    {
        return !empty($this->streamableVersion);
    }

    /**
     * @return string[]
     */
    private function getUasConfig(): array
    {
        $cosmosEnv = $this->cosmosInfo->getAppEnvironment();
        $uasEnv = 'live';
        $apiKey = 'rt5uf8v9aol56';  // used as-is in frontend on advice of UAS

        if ($cosmosEnv !== 'live') {
            // V2 set "test" environment even for sandbox.
            $uasEnv = 'test';
            $apiKey = 'bapd63mcqopnp';
        }

        return [
            'apiKey' => $apiKey,
            'env' => $uasEnv,
            'pid' => (string) $this->programmeItem->getPid(),
            'versionPid' => (string) $this->streamableVersion->getPid(),
            'resourceDomain' => $this->programmeItem->isTv() ? 'tv' : 'radio',
            'resourceType' => $this->programmeItem->getType(),
        ];
    }
}
