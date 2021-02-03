<?php
namespace Tests\App\Controller\FindByPid\EpisodeController;

use Symfony\Bundle\FrameworkBundle\Client;
use Tests\App\BaseWebTestCase;

class EpisodeControllerNoindexBrandsTest extends BaseWebTestCase
{
    /** @var Client */
    protected $client;

    public function setUp()
    {
        $this->loadFixtures([
            'ProgrammeEpisodes\EpisodesFixtures',
        ]);
        $this->client = static::createClient();
    }

    public function testDoesNotAddMetaNoIndexForUnlistedBrandEpisodes()
    {
        $this->client = static::createClient();
        $crawler = $this->client->request('GET', '/programmes/p3000002');

        $this->assertResponseStatusCode($this->client, 200);
        $this->assertFalse($this->isAddedMetaNoIndex($crawler), 'when not a no-index brand');
    }

    public function testAddsMetaNoIndexForListedBrandEpisodes()
    {
        $this->client = static::createClient();
        $crawler = $this->client->request('GET', '/programmes/p3n0s301');

        $this->assertResponseStatusCode($this->client, 200);
        $this->assertTrue($this->isAddedMetaNoIndex($crawler), 'when a no-index brand');
    }

    private function isAddedMetaNoIndex($crawler): bool
    {
        return ($crawler->filter('meta[name="robots"]')->count() > 0 && $crawler->filter('meta[name="robots"]')->first()->attr('content') === 'noindex');
    }
}
