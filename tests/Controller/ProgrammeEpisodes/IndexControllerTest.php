<?php
declare(strict_types=1);

namespace Tests\App\Controller\ProgrammeEpisodes;

use Symfony\Bundle\FrameworkBundle\Client;
use Tests\App\BaseWebTestCase;

/**
 * @IgnoreAnnotation("dataProvider")
 */
class IndexControllerTest extends BaseWebTestCase
{
    /** @var Client */
    protected $client;

    public function setUp()
    {
        $this->loadFixtures([
            'ProgrammeEpisodes\FranchiseFixture',
            'ProgrammeEpisodes\EpisodesFixtures',
        ]);
        $this->client = static::createClient();
    }

    /** @dataProvider indexControllerProvider */
    public function testIndexControllerRedirects(string $pid, string $expectedRedirect)
    {
        $this->client->request('GET', '/programmes/' . $pid . '/episodes');
        $this->assertRedirectTo($this->client, 302, $expectedRedirect);
    }

    public function indexControllerProvider(): array
    {
        return [
            'Programme Container with available episodes redirects to player' => ['b006q2x0', '/programmes/b006q2x0/episodes/player'],
            'Programme Container without available episodes redirects to guide' => ['b006pnjk', '/programmes/b006pnjk/episodes/guide'],
        ];
    }

    public function testIndexController404WithProgrammeItem()
    {
        $this->client->request('GET', '/programmes/p01l1z04/episodes');
        $this->assertResponseStatusCode($this->client, 404);
    }

    public function testRedirectIsCachedFor600Seconds()
    {
        $this->client->request('GET', '/programmes/b006q2x0/episodes');
        $this->assertHasRequiredResponseHeaders($this->client, 'max-age=600, public, stale-while-revalidate=30');
    }

    public function testEpisodesAZredirect()
    {
        $this->client->request('GET', '/programmes/b006q2x0/episodes/a-z');
        $this->assertRedirectTo($this->client, 301, 'http://localhost/programmes/b006q2x0');
    }
}
