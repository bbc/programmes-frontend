<?php
declare(strict_types=1);

namespace App\ExternalApi\Ada\Service;

use App\ExternalApi\Ada\Domain\AdaClass;
use App\ExternalApi\Ada\Mapper\AdaProgrammeMapper;
use App\ExternalApi\Client\Factory\HttpApiClientFactory;
use App\ExternalApi\Exception\MultiParseException;
use BBC\ProgrammesCachingLibrary\CacheInterface;
use BBC\ProgrammesPagesService\Domain\Entity\Programme;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Domain\ValueObject\Pid;
use BBC\ProgrammesPagesService\Service\CoreEntitiesService;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;

class AdaProgrammeService
{
    private const PROGRAMME_LIMIT = 30;

    /** @var HttpApiClientFactory */
    private $clientFactory;

    /** @var string */
    private $baseUrl;

    /** @var AdaProgrammeMapper */
    private $mapper;

    /** @var CoreEntitiesService */
    private $coreEntitiesService;

    public function __construct(
        HttpApiClientFactory $clientFactory,
        string $baseUrl,
        AdaProgrammeMapper $mapper,
        CoreEntitiesService $coreEntitiesService
    ) {
        $this->clientFactory = $clientFactory;
        $this->baseUrl = $baseUrl;
        $this->mapper = $mapper;
        $this->coreEntitiesService = $coreEntitiesService;
    }

    public function findProgrammeItemsByClass(
        AdaClass $adaClass,
        string $mediaType,
        int $page,
        ?ProgrammeContainer $programmeContainer
    ): PromiseInterface {
        $id = $adaClass->getId();
        $pid = $programmeContainer !== null ? $programmeContainer->getPid() : null;

        return $this->clientFactory->getHttpApiMultiClient(
            $this->clientFactory->keyHelper(__CLASS__, __FUNCTION__, $id, $mediaType, $page, $pid),
            [
                $this->baseUrl .
                '/programme_items?class=' .
                urlencode($id) .
                ($pid !== null ? '&programme=' . $pid : '') .
                ($mediaType !== '' ? '&media_type=' . $mediaType : '') .
                '&page=' .
                $page .
                '&page_size=' .
                self::PROGRAMME_LIMIT .
                '&order=title&direction=ascending',
            ],
            Closure::fromCallable([$this, 'parseResponse']),
            [],
            [],
            CacheInterface::NORMAL,
            CacheInterface::SHORT,
            [
                'timeout' => 15,
            ],
            true
        )->makeCachedPromise();
    }

    public function findSuggestedByProgrammeItem(Programme $programme, int $limit = 3): PromiseInterface
    {
        $cacheKey = $this->clientFactory->keyHelper(__CLASS__, __FUNCTION__, (string) $programme->getPid(), $limit);

        $urls = [
            'relatedByTag' => $this->requestUrlForRelatedProgrammeItems(
                $programme->getPid(),
                'tag',
                null,
                1
            ),
            'relatedByBrand' => $this->requestUrlForRelatedProgrammeItems(
                $programme->getPid(),
                null,
                $programme->getTleo()->getPid(),
                1
            ),
            'relatedByCategory' => $this->requestUrlForRelatedProgrammeItems(
                $programme->getPid(),
                null,
                null,
                5
            ),
        ];

        $client = $this->clientFactory->getHttpApiMultiClient(
            $cacheKey,
            $urls,
            Closure::fromCallable([$this, 'parseAggregateResponses']),
            [$limit],
            [],
            CacheInterface::NORMAL,
            CacheInterface::SHORT,
            [
                'timeout' => 10,
            ]
        );

        return $client->makeCachedPromise();
    }

    private function parseAggregateResponses(array $responses, int $limit): array
    {
        $results = [];
        foreach ($responses as $resultKey => $response) {
            if (!$response instanceof Response) {
                throw new MultiParseException($resultKey, "Ada callback received non-response object!");
            }
            $data = json_decode($response->getBody()->getContents(), true);
            if (!isset($data['items'])) {
                throw new MultiParseException($resultKey, "Ada JSON response does not contain items element");
            }
            $results[$resultKey] = $data['items'];
        }

        $programmes = array_merge(
            $results['relatedByTag'],
            $results['relatedByBrand'],
            $results['relatedByCategory']
        );
        if (empty($programmes)) {
            return [];
        }

        $uniqueProgrammes = array_reduce(
            $programmes,
            function ($carry, $item) {
                if (!is_array($carry)) {
                    $carry = [];
                }
                if (isset($item['pid'])) {
                    $carry[$item['pid']] = $item;
                }
                return $carry;
            }
        );
        $uniqueProgrammes = array_values($uniqueProgrammes);
        $uniqueProgrammes = array_slice($uniqueProgrammes, 0, $limit);

        if (empty($uniqueProgrammes)) {
            return [];
        }

        $pids = [];
        foreach ($uniqueProgrammes as $uniqueProgramme) {
            $pids[] = new Pid($uniqueProgramme['pid']);
        }

        $programmeItems = $this->coreEntitiesService->findByPids($pids);
        $relatedProgrammes = [];
        foreach ($uniqueProgrammes as $item) {
            if (isset($programmeItems[$item['pid']])) {
                $relatedProgrammes[] = $this->mapper->mapItem($programmeItems[$item['pid']], $item);
            }
        }

        return $relatedProgrammes;
    }

    private function parseResponse(array $responses): array
    {
        $data = json_decode($responses[0]->getBody()->getContents(), true);
        if (!isset($data['items'])) {
            throw new MultiParseException(0, "Ada JSON response does not contain items element");
        }

        $items = $data['items'];

        $programmes = $this->coreEntitiesService->findByPids(array_map(static function ($item) {
            return new Pid($item['pid']);
        }, $items), 'Programme');

        $adaProgrammeItems = [];
        foreach ($items as $item) {
            if (isset($programmes[$item['pid']])) {
                $adaProgrammeItems[] = $this->mapper->mapItem($programmes[$item['pid']], $item);
            }
        }

        return $adaProgrammeItems;
    }

    private function requestUrlForRelatedProgrammeItems(Pid $pid, ?string $scope = null, ?Pid $countContextPid = null, int $limit = 10, int $page = 1)
    {
        $order = 'random';
        $orderDirection = null;
        $threshold = 2;

        $params = http_build_query(
            [
                'page' => $page,
                'page_size' => $limit,
                'scope' => $scope,
                'count_context' => $countContextPid,
                'threshold' => $threshold,
                'order' => $order,
            ]
        );
        $url =  $this->baseUrl . '/programme_items/' . $pid . '/related?' . $params;
        return $url;
    }
}
