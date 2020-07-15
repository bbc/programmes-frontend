<?php
declare(strict_types = 1);
namespace Tests\App;

use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Liip\FunctionalTestBundle\Test\WebTestCase;

abstract class BaseWebTestCase extends WebTestCase
{
    const FIXTURES_PATH = 'Tests\App\DataFixtures\ORM\\';

    public function assertResponseStatusCode($client, $expectedCode, string $assertNotifyText = '')
    {
        $actualCode = $client->getResponse()->getStatusCode();
        $this->assertEquals($expectedCode, $actualCode, sprintf(
            'Failed asserting that the response status code "%s" matches expected "%s". %s',
            $actualCode,
            $expectedCode,
            $assertNotifyText
        ));
    }

    public function assertRedirectTo($client, $code, $expectedLocation)
    {
        $this->assertResponseStatusCode($client, $code);
        $this->assertEquals($expectedLocation, $client->getResponse()->headers->get('location'));
    }

    public function assertHasRequiredResponseHeaders($client, $cacheControl = 'max-age=120, public', $contentLanguage = null)
    {
        $this->assertEquals($cacheControl, $client->getResponse()->headers->get('Cache-Control'));
        $this->assertArraySubset(['X-CDN'], $client->getResponse()->getVary());
        $this->assertEquals('IE=edge', $client->getResponse()->headers->get('X-UA-Compatible'));
        $this->assertEquals('programmes-frontend', $client->getResponse()->headers->get('X-Webapp'));
        $this->assertEquals('stale-while-revalidate=30', $client->getResponse()->headers->get('X-Cache-Control'));
        if (isset($contentLanguage)) {
            $this->assertEquals($contentLanguage, $client->getResponse()->headers->get('Content-Language'));
        } else {
            $this->assertNotEmpty($client->getResponse()->headers->get('Content-Language'));
        }
    }

    /**
     * The crawler can't do the ajax request to check the labels so as a way around in the OrbitStubClient.php
     * we inject this values inside the HTML, this only gets injected when running unitest. This function extracts those values
     *
     * @param \Symfony\Component\DomCrawler\Crawler $crawler
     * @return array
     */
    public function extractIstatsLabels($crawler)
    {
        $labels = [];
        $extractedValues = $crawler->filter("orbit-template-params")->extract(['data-values']);
        $labelsObject = json_decode($extractedValues[0]);
        foreach ($labelsObject->analyticsLabels as $item) {
            $labels[$item->key] = urldecode($item->value);
        }
        return $labels;
    }

    protected function loadFixtures(array $fixtureNames = [], bool $append = false, ?string $omName = null, string $registryName = 'doctrine', ?int $purgeMode = null): ?AbstractExecutor
    {
        $classNames = array();
        foreach ($fixtureNames as $fixtureName) {
            $className = self::FIXTURES_PATH . $fixtureName;
            array_push($classNames, $className);
        }
        return parent::loadFixtures($classNames, $append, $omName, $registryName, $purgeMode);
    }
}
