<?php
declare(strict_types = 1);

namespace Tests\App\ExternalApi\Ada\Service;

use App\ExternalApi\Ada\Domain\AdaProgrammeItem;
use App\ExternalApi\Ada\Mapper\AdaProgrammeMapper;
use App\ExternalApi\Ada\Service\AdaProgrammeService;
use App\ExternalApi\Client\Factory\HttpApiClientFactory;
use BBC\ProgrammesPagesService\Domain\Entity\Programme;
use BBC\ProgrammesPagesService\Domain\ValueObject\Pid;
use BBC\ProgrammesPagesService\Service\CoreEntitiesService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use ReflectionClass;
use Tests\App\ExternalApi\BaseServiceTestCase;

class AdaProgrammeServiceTest extends BaseServiceTestCase
{
    public function setUp()
    {
        $this->setUpCache();
        $this->setUpLogger();
    }

    public function testNoRelatedItemsAreReturnedWhenNoRelatedItems()
    {
        $firstResponse = $this->createResponse([]);
        $secondResponse = $this->createResponse([]);
        $thirdResponse = $this->createResponse([]);
        $mapper = $this->createMock(AdaProgrammeMapper::class);
        $mapper->expects($this->never())->method('mapItem');
        $coreEntitiesService = $this->createMock(CoreEntitiesService::class);
        $coreEntitiesService->expects($this->never())->method('findByPids');

        $service = $this->service($this->client([$firstResponse, $secondResponse, $thirdResponse]), $coreEntitiesService, $mapper);

        $promise = $service->findSuggestedByProgrammeItem($this->mockProgramme(), 10);

        $this->assertInstanceOf(Promise::class, $promise);
        $result = $promise->wait();
        $this->assertSame([], $result);
    }

    public function testFindSuggestedByProgrammeItemWithDuplicates()
    {
        $firstResponse = $this->createResponse([
            [
                'pid' => 'b007rlb6',
                'type' => 'episode',
                'title' => 'AAAA',
                'class_count' => 0,
            ],
        ]);
        $secondResponse = $this->createResponse([
            [
                'pid' => 'b007rlb6',
                'type' => 'episode',
                'title' => 'AAAA',
                'class_count' => 0,
                'meaning' => 'same pid different body, should be removed',
            ],
        ]);
        $thirdResponse = $this->createResponse([
            [
                'pid' => 'b007rlb4',
                'type' => 'episode',
                'title' => 'BBBB',
                'class_count' => 0,
            ],
            [
                'pid' => 'b007rlb6',
                'type' => 'episode',
                'title' => 'AAAA',
                'class_count' => 0,
            ],
        ]);
        $mapper = $this->createMock(AdaProgrammeMapper::class);
        $mapper
            ->expects($this->exactly(2))
            ->method('mapItem')
            ->willReturn(
                $this->createMock(AdaProgrammeItem::class)
            );
        $coreEntitiesService = $this->createMock(CoreEntitiesService::class);
        $coreEntitiesService->expects($this->once())
            ->method('findByPids')
            ->with([new Pid('b007rlb6'), new Pid('b007rlb4')])
            ->willReturn([
                'b007rlb6' => $this->mockProgramme('b007rlb6'),
                'b007rlb4' => $this->mockProgramme('b007rlb4'),
            ]);

        $service = $this->service($this->client([$firstResponse, $secondResponse, $thirdResponse]), $coreEntitiesService, $mapper);

        $promise = $service->findSuggestedByProgrammeItem($this->mockProgramme(), 10);

        $this->assertInstanceOf(Promise::class, $promise);
        $result = $promise->wait();
        $this->assertInternalType("array", $result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(AdaProgrammeItem::class, $result);
    }

    public function testFindSuggestedByProgrammeItemWithoutDuplicates()
    {
        $firstResponse = $this->createResponse([
            [
                'pid' => 'b007rlb6',
                'type' => 'episode',
                'title' => 'BBBBB',
                'class_count' => 0,
            ],
        ]);
        $secondResponse = $this->createResponse([
            [
                'pid' => 'b007rlb5',
                'type' => 'episode',
                'title' => 'BBBB',
                'class_count' => 0,
            ],
        ]);
        $thirdResponse = $this->createResponse([
            [
                'pid' => 'b007rlb4',
                'type' => 'episode',
                'title' => 'BBB',
                'class_count' => 0,
            ],
        ]);
        $mapper = $this->createMock(AdaProgrammeMapper::class);
        $mapper->expects($this->exactly(3))->method('mapItem')->willReturn($this->createMock(AdaProgrammeItem::class));
        $coreEntitiesService = $this->createMock(CoreEntitiesService::class);
        $coreEntitiesService->expects($this->once())
            ->method('findByPids')
            ->with([new Pid('b007rlb6'), new Pid('b007rlb5'), new Pid('b007rlb4')])
            ->willReturn([
                'b007rlb6' => $this->mockProgramme('b007rlb6'),
                'b007rlb5' => $this->mockProgramme('b007rlb5'),
                'b007rlb4' => $this->mockProgramme('b007rlb4'),
            ]);

        $service = $this->service($this->client([$firstResponse, $secondResponse, $thirdResponse]), $coreEntitiesService, $mapper);

        $promise = $service->findSuggestedByProgrammeItem($this->mockProgramme(), 3);

        $this->assertInstanceOf(Promise::class, $promise);
        $result = $promise->wait();
        $this->assertInternalType("array", $result);
        $this->assertCount(3, $result);
        $this->assertContainsOnlyInstancesOf(AdaProgrammeItem::class, $result);
    }

    public function testInvalidResponseIsHandledAndNotCached()
    {
        $mapper = $this->createMock(AdaProgrammeMapper::class);
        $coreEntitiesService = $this->createMock(CoreEntitiesService::class);

        $dudResponse = new Response(200, [], '"foo":"Bar"');
        $service = $this->service(
            $this->client([$dudResponse, $dudResponse, $dudResponse]),
            $coreEntitiesService,
            $mapper
        );

        $result = $service->findSuggestedByProgrammeItem($this->mockProgramme(), 10)->wait(true);

        // Assert empty result is returned
        $this->assertEquals([], $result);

        // Assert nothing was saved in the cache
        $this->assertEmpty($this->validCacheValues());

        // Assert the error was logged
        $this->assertTrue($this->getLoggerHandler()->hasRecordThatMatches(
            '/Error parsing feed for "https:\/\/.*"\. Error was: Ada JSON response does not contain items element/',
            Logger::ERROR
        ));
    }

    public function test500ErrorsAreHandledAndNotCached()
    {
        $mapper = $this->createMock(AdaProgrammeMapper::class);
        $coreEntitiesService = $this->createMock(CoreEntitiesService::class);

        $dudResponse = new Response(500, [], '');
        $service = $this->service(
            $this->client([$dudResponse, $dudResponse, $dudResponse]),
            $coreEntitiesService,
            $mapper
        );

        $result = $service->findSuggestedByProgrammeItem($this->mockProgramme(), 10)->wait(true);

        // Assert empty result is returned
        $this->assertEquals([], $result);

        // Assert nothing was saved in the cache
        $this->assertEmpty($this->validCacheValues());
    }

    public function test404ErrorsAreHandledAndCached()
    {
        $mapper = $this->createMock(AdaProgrammeMapper::class);
        $coreEntitiesService = $this->createMock(CoreEntitiesService::class);

        $dudResponse = new Response(404, [], '');
        $service = $this->service(
            $this->client([$dudResponse, $dudResponse, $dudResponse]),
            $coreEntitiesService,
            $mapper
        );

        $result = $service->findSuggestedByProgrammeItem($this->mockProgramme(), 10)->wait(true);

        // Assert empty result is returned
        $this->assertEquals([], $result);

        // Assert an empty result was stored in the cache
        $validCacheValues = $this->validCacheValues();
        $this->assertCount(1, $validCacheValues);
        $this->assertContains([], $validCacheValues);
    }

    public function testCorrectLimiting()
    {
//        Pass in 4 non-duplicate programmes and only expect 3 to survive...
        $firstResponse = $this->createResponse([
            [
                'pid' => 'b007rlb6',
                'type' => 'episode',
                'title' => 'BBBBB',
                'class_count' => 0,
            ],
        ], 1, 3);
        $secondResponse = $this->createResponse([
            [
                'pid' => 'b007rlb5',
                'type' => 'episode',
                'title' => 'BBBB',
                'class_count' => 0,
            ],
        ], 1, 3);
        $thirdResponse = $this->createResponse([
            [
                'pid' => 'b007rlb7',
                'type' => 'episode',
                'title' => 'BBB',
                'class_count' => 0,
            ],
            [
                'pid' => 'b007rlb8',
                'type' => 'episode',
                'title' => 'CCC',
                'class_count' => 0,
            ],
        ], 1, 3);
        $mapper = $this->createMock(AdaProgrammeMapper::class);
        $mapper->expects($this->exactly(3))->method('mapItem')->willReturn($this->createMock(AdaProgrammeItem::class));
        $coreEntitiesService = $this->createMock(CoreEntitiesService::class);
        $coreEntitiesService->expects($this->once())
            ->method('findByPids')
            ->with([new Pid('b007rlb6'), new Pid('b007rlb5'), new Pid('b007rlb7')])
            ->willReturn([
                'b007rlb6' => $this->mockProgramme('b007rlb6'),
                'b007rlb5' => $this->mockProgramme('b007rlb5'),
                'b007rlb7' => $this->mockProgramme('b007rlb7'),
            ]);

        $service = $this->service($this->client([$firstResponse, $secondResponse, $thirdResponse]), $coreEntitiesService, $mapper);

        $promise = $service->findSuggestedByProgrammeItem($this->mockProgramme(), 3);

        $result = $promise->wait();

        $this->assertCount(3, $result);
    }

    /**
     * @expectedException \App\ExternalApi\Exception\MultiParseException
     */
    public function testParseExceptionWithNoData()
    {
        $response = new Response(200, []);

        $mapper = $this->createMock(AdaProgrammeMapper::class);
        $coreEntitiesService = $this->createMock(CoreEntitiesService::class);

        $fakeResponses = [
            'relatedByTag' => $response,
            'relatedByBrand' => $response,
            'relatedByCategory' => $response,
        ];
        $this->invokeMethod($this->service($this->client([]), $coreEntitiesService, $mapper), 'parseAggregateResponses', [$fakeResponses, 3]);
    }

    private function service(ClientInterface $client, CoreEntitiesService $coreEntitiesService, AdaProgrammeMapper $mapper): AdaProgrammeService
    {
        return new AdaProgrammeService(
            new HttpApiClientFactory($client, $this->cache, $this->cacheWithResilience, $this->logger),
            'https://api.example.com/test',
            $mapper,
            $coreEntitiesService
        );
    }

    private function mockProgramme($pid = 'b0000001'): Programme
    {
        $mockProgramme = $this->createMock(Programme::class);
        $mockProgramme->method('getTleo')->will($this->returnSelf());
        $mockProgramme->method('getPid')->willReturn(new Pid($pid));

        return $mockProgramme;
    }

    private function createResponse(array  $items, int $page = 1, int $limit = 5): Response
    {
        return new Response(200, [], json_encode(['page' => $page, 'page_size' => $limit, 'items' => $items]));
    }

    private function invokeMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
