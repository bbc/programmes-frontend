<?php

namespace App\ExternalApi\IdtQuiz\Service;

use App\ExternalApi\Client\Factory\HttpApiClientFactory;
use GuzzleHttp\Promise\PromiseInterface;
use Closure;
use BBC\ProgrammesCachingLibrary\CacheInterface;

class IdtQuizService
{
    /** @var HttpApiClientFactory */
    private $clientFactory;

    /** @var string */
    private $smallproxEndpoint;

    public function __construct(
        HttpApiClientFactory $clientFactory,
        string $smallproxEndpoint
    ) {
        $this->clientFactory = $clientFactory;
        $this->smallproxEndpoint = $smallproxEndpoint;
    }

    public function getQuizContentPromise(string $quizId): PromiseInterface
    {
        $cacheKey = $this->clientFactory->keyHelper(__CLASS__, __FUNCTION__, $quizId);
        $client = $this->clientFactory->getHttpApiMultiClient(
            $cacheKey,
            [$this->getQuizUrl($quizId)],
            Closure::fromCallable([$this, 'parseResponse']),
            [],
            '',
            CacheInterface::NORMAL,
            CacheInterface::NONE,
            [
                'timeout' => 10,
            ]
        );

        return $client->makeCachedPromise();
    }

    /**
     * DATCAP-136: returning deprecated content warning as IDT quizzes are no longer available
     */
    public function getDeprecatedContentWarning()
    {
        return '<p>Sorry, this quiz is no longer available.</p>';
    }

    private function parseResponse(array $responses): string
    {
        if (count($responses) <= 0) {
            return '';
        }

        return $responses[0]->getBody()->getContents();
    }

    private function getQuizUrl(string $quizId): string
    {
        return sprintf(
            '%s/indepthtoolkit/quizzes/%s/app',
            $this->smallproxEndpoint,
            urlencode($quizId)
        );
    }
}
