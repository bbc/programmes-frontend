<?php

namespace Tests\App\Controller\JSON;

use Tests\App\BaseCliftonTestCase;

/**
 * @covers \App\Controller\JSON\FindByPidController
 */
class FindByPidControllerTest extends BaseCliftonTestCase
{
    public function testFindByPidActionWithBrand()
    {
        $this->loadFixtures(['EastendersFixture']);

        $client = static::createClient();
        $client->request('GET', '/programmes/b006m86d.json');

        $this->assertResponseStatusCode($client, 200);

        $jsonContent = $this->getDecodedJsonContent($client);

        $this->assertArrayHasKey('programme', $jsonContent);
        $this->assertEquals('b006m86d', $jsonContent['programme']['pid']);
        $this->assertEquals('brand', $jsonContent['programme']['type']);

        // Parent tree
        $this->assertArrayNotHasKey('parent', $jsonContent['programme']);

        // Ownership
        $this->assertArrayHasKey('ownership', $jsonContent['programme']);
        $this->assertEquals('bbc_one', $jsonContent['programme']['ownership']['service']['id']);

        // Siblings
        $this->assertArrayNotHasKey('peers', $jsonContent['programme']);

        // Related links
        $this->assertCount(2, $jsonContent['programme']['links']);
        $this->assertEquals('RL1', $jsonContent['programme']['links'][0]['title']);
    }

    public function testFindByPidActionWithSeries()
    {
        $this->loadFixtures(['EastendersFixture']);

        $client = static::createClient();
        $client->request('GET', '/programmes/b006m86f.json');

        $this->assertResponseStatusCode($client, 200);

        $jsonContent = $this->getDecodedJsonContent($client);

        $this->assertArrayHasKey('programme', $jsonContent);
        $this->assertEquals('b006m86f', $jsonContent['programme']['pid']);
        $this->assertEquals('series', $jsonContent['programme']['type']);

        // Parent tree
        $this->assertEquals('b006m86d', $jsonContent['programme']['parent']['programme']['pid']);
        $this->assertArrayNotHasKey('parent', $jsonContent['programme']['parent']);

        // Ownership does inherit
        $this->assertArrayHasKey('ownership', $jsonContent['programme']);
        $this->assertEquals('bbc_one', $jsonContent['programme']['parent']['programme']['ownership']['service']['id']);

        // Siblings
        $this->assertNull($jsonContent['programme']['peers']['next']);
        $this->assertNull($jsonContent['programme']['peers']['previous']);

        // Related links
        $this->assertSame([], $jsonContent['programme']['links']);

        // Siblings
        $this->assertNull($jsonContent['programme']['peers']['next']);
        $this->assertNull($jsonContent['programme']['peers']['previous']);
    }

    public function testFindByPidActionWithEpisode()
    {
        $this->loadFixtures(['EastendersFixture']);

        $client = static::createClient();
        $client->request('GET', '/programmes/b06khpp1.json');

        $this->assertResponseStatusCode($client, 200);

        $jsonContent = $this->getDecodedJsonContent($client);

        $this->assertArrayHasKey('programme', $jsonContent);
        $this->assertEquals('b06khpp1', $jsonContent['programme']['pid']);
        $this->assertEquals('episode', $jsonContent['programme']['type']);

        // Parent tree
        $this->assertEquals('b006m86f', $jsonContent['programme']['parent']['programme']['pid']);
        $this->assertEquals('b006m86d', $jsonContent['programme']['parent']['programme']['parent']['programme']['pid']);
        $this->assertArrayNotHasKey('parent', $jsonContent['programme']['parent']['programme']['parent']);

        // Ownership does inherit
        $this->assertArrayHasKey('ownership', $jsonContent['programme']);
        $this->assertEquals('bbc_one', $jsonContent['programme']['parent']['programme']['parent']['programme']['ownership']['service']['id']);


        // Related links
        $this->assertSame([], $jsonContent['programme']['links']);

        //Siblings
        $this->assertNotNull($jsonContent['programme']['peers']['previous']);
        $this->assertEquals('b06khpp0', $jsonContent['programme']['peers']['previous']['pid']);
        $this->assertNotNull($jsonContent['programme']['peers']['next']);
        $this->assertEquals('b06khpp2', $jsonContent['programme']['peers']['next']['pid']);

        // Versions
        $this->assertSame([], $jsonContent['programme']['versions']);
    }

    public function testFindByPidActionWithClip()
    {
        $this->loadFixtures(['EastendersFixture']);

        $client = static::createClient();
        $client->request('GET', '/programmes/b06khpq0.json');

        $this->assertResponseStatusCode($client, 200);

        $jsonContent = $this->getDecodedJsonContent($client);

        $this->assertArrayHasKey('programme', $jsonContent);
        $this->assertEquals('b06khpq0', $jsonContent['programme']['pid']);
        $this->assertEquals('clip', $jsonContent['programme']['type']);

        // Parent tree
        $this->assertEquals('b06khpp0', $jsonContent['programme']['parent']['programme']['pid']);
        $this->assertEquals('b006m86f', $jsonContent['programme']['parent']['programme']['parent']['programme']['pid']);
        $this->assertEquals('b006m86d', $jsonContent['programme']['parent']['programme']['parent']['programme']['parent']['programme']['pid']);
        $this->assertArrayNotHasKey('parent', $jsonContent['programme']['parent']['programme']['parent']['programme']['parent']);

        // Ownership does inherit
        $this->assertArrayHasKey('ownership', $jsonContent['programme']);
        $this->assertEquals('bbc_one', $jsonContent['programme']['parent']['programme']['parent']['programme']['parent']['programme']['ownership']['service']['id']);


        // Related links
        $this->assertSame([], $jsonContent['programme']['links']);

        // Siblings
        $this->assertNull($jsonContent['programme']['peers']['next']);
        $this->assertNull($jsonContent['programme']['peers']['previous']);

        // Versions
        $this->assertCount(2, $jsonContent['programme']['versions']);
        $this->assertEquals('b06khpr0', $jsonContent['programme']['versions'][0]['pid']);
    }

    public function testFindByPidMusicSegment()
    {
        $this->loadFixtures(['SegmentEventsFixture']);

        $client = static::createClient();
        $client->request('GET', '/programmes/p002d8dd.json');

        $this->assertResponseStatusCode($client, 200);

        $jsonContent = $this->getDecodedJsonContent($client);

        $this->assertArrayHasKey('segment', $jsonContent);
        $this->assertEquals('p002d8dd', $jsonContent['segment']['pid']);
        $this->assertEquals('music', $jsonContent['segment']['type']);

        //Segment Events
        $this->assertArrayHasKey('segment_events', $jsonContent['segment']);
        $this->assertEquals('sv000001', $jsonContent['segment']['segment_events'][0]['pid']);
        $this->assertArrayHasKey('version', $jsonContent['segment']['segment_events'][0]);
        $this->assertEquals('v0000001', $jsonContent['segment']['segment_events'][0]['version']['pid']);

        //Parent Tree
        $this->assertArrayHasKey('parent', $jsonContent['segment']['segment_events'][0]['version']['programme']);
        $this->assertEquals('b00swyx1', $jsonContent['segment']['segment_events'][0]['version']['programme']['parent']['programme']['pid']);

        $this->assertArrayHasKey('parent', $jsonContent['segment']['segment_events'][0]['version']['programme']['parent']['programme']);
        $this->assertEquals('b010t19z', $jsonContent['segment']['segment_events'][0]['version']['programme']['parent']['programme']['parent']['programme']['pid']);

        $this->assertArrayNotHasKey('parent', $jsonContent['segment']['segment_events'][0]['version']['programme']['parent']['programme']['parent']['programme']);

        //Primary Contributor
        $this->assertArrayHasKey('primary_contributor', $jsonContent['segment']);
        $this->assertEquals('cntrbtr1', $jsonContent['segment']['primary_contributor']['pid']);

        //Contributions
        $this->assertArrayHasKey('contributions', $jsonContent['segment']);
        $this->assertEquals(1, count($jsonContent['segment']['contributions']));
        $this->assertEquals('cntrbtr1', $jsonContent['segment']['contributions'][0]['pid']);
    }

    public function testFindByPidSegment()
    {
        $this->loadFixtures(['SegmentEventsFixture']);

        $client = static::createClient();
        $client->request('GET', '/programmes/p00wx0df.json');

        $this->assertResponseStatusCode($client, 200);

        $jsonContent = $this->getDecodedJsonContent($client);

        $this->assertArrayHasKey('segment', $jsonContent);
        $this->assertEquals('p00wx0df', $jsonContent['segment']['pid']);
        $this->assertEquals('speech', $jsonContent['segment']['type']);

        //Segment Events
        $this->assertArrayHasKey('segment_events', $jsonContent['segment']);
        $this->assertEquals('sv000002', $jsonContent['segment']['segment_events'][0]['pid']);
        $this->assertArrayHasKey('version', $jsonContent['segment']['segment_events'][0]);
        $this->assertEquals('v0000002', $jsonContent['segment']['segment_events'][0]['version']['pid']);
        $this->assertEquals('b00syxx6', $jsonContent['segment']['segment_events'][0]['version']['programme']['pid']);

        //Parent Tree
        $this->assertArrayHasKey('parent', $jsonContent['segment']['segment_events'][0]['version']['programme']);
        $this->assertEquals('b00swyx1', $jsonContent['segment']['segment_events'][0]['version']['programme']['parent']['programme']['pid']);

        $this->assertArrayHasKey('parent', $jsonContent['segment']['segment_events'][0]['version']['programme']['parent']['programme']);
        $this->assertEquals('b010t19z', $jsonContent['segment']['segment_events'][0]['version']['programme']['parent']['programme']['parent']['programme']['pid']);

        $this->assertArrayNotHasKey('parent', $jsonContent['segment']['segment_events'][0]['version']['programme']['parent']['programme']['parent']['programme']);

        //Primary Contributor
        $this->assertArrayNotHasKey('primary_contributor', $jsonContent['segment']);

        //Contributions
        $this->assertArrayHasKey('contributions', $jsonContent['segment']);
        $this->assertEquals(0, count($jsonContent['segment']['contributions']));
    }

    public function testFindByPidSegmentEvent()
    {
        $this->loadFixtures(['SegmentEventsFixture']);

        $client = static::createClient();
        $client->request('GET', '/programmes/sv000001.json');

        $this->assertResponseStatusCode($client, 200);

        $jsonContent = $this->getDecodedJsonContent($client);

        $this->assertArrayHasKey('segment_event', $jsonContent);
        $this->assertEquals('sv000001', $jsonContent['segment_event']['pid']);

        //Segment
        $this->assertArrayHasKey('segment', $jsonContent['segment_event']);
        $this->assertEquals('p002d8dd', $jsonContent['segment_event']['segment']['pid']);
        $this->assertArrayHasKey('type', $jsonContent['segment_event']['segment']);
        $this->assertEquals('MusicSegment', $jsonContent['segment_event']['segment']['type']);

        //Segment Events for Segment
        $this->assertArrayHasKey('segment_events', $jsonContent['segment_event']['segment']);
        $this->assertEquals(1, count($jsonContent['segment_event']['segment']['segment_events']));
        $this->assertEquals('sv000001', $jsonContent['segment_event']['segment']['segment_events'][0]['pid']);


        //Segment Primary Contributor
        $this->assertArrayHasKey('primary_contributor', $jsonContent['segment_event']['segment']);
        $this->assertEquals('cntrbtr1', $jsonContent['segment_event']['segment']['primary_contributor']['pid']);

        //Segment Contributions
        $this->assertArrayHasKey('contributions', $jsonContent['segment_event']['segment']);
        $this->assertEquals(1, count($jsonContent['segment_event']['segment']['contributions']));
        $this->assertEquals('cntrbtr1', $jsonContent['segment_event']['segment']['contributions'][0]['pid']);
    }

    public function testFindByPidVersion()
    {
        $this->loadFixtures(['EastendersFixture']);

        $client = static::createClient();
        $client->request('GET', '/programmes/b06khpr0.json');

        $jsonContent = $this->getDecodedJsonContent($client);

        $this->assertArrayHasKey('version', $jsonContent);
        $this->assertEquals('b06khpr0', $jsonContent['version']['pid']);
        $this->assertEquals(null, $jsonContent['version']['duration']);

        // Parent Tree
        $this->assertArrayHasKey('parent', $jsonContent['version']);
        $this->assertArrayHasKey('pid', $jsonContent['version']['parent']['programme']);
        $this->assertArrayHasKey('pid', $jsonContent['version']['parent']['programme']);
        $this->assertEquals('b06khpq0', $jsonContent['version']['parent']['programme']['pid']);

        // Types
        $this->assertArrayHasKey('types', $jsonContent['version']);

        // Contributors
        $this->assertArrayHasKey('contributors', $jsonContent['version']);

        // Segment Events
        $this->assertArrayHasKey('segment_events', $jsonContent['version']);

        // Broadcasts
        $this->assertArrayHasKey('broadcasts', $jsonContent['version']);

        // Availabilities
        $this->assertArrayHasKey('availabilities', $jsonContent['version']);

        $this->assertResponseStatusCode($client, 200);
    }

    public function testFindByPidActionWithEmptyResult()
    {
        $this->loadFixtures([]);

        $client = static::createClient();
        $client->request('GET', '/programmes/qqqqqqqq.json');

        $this->assertResponseStatusCode($client, 404);
    }
}
