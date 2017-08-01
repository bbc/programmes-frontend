<?php
declare(strict_types = 1);

namespace App\Metrics\ProgrammesMetrics;

class ApiTimeMetric implements ProgrammesMetricInterface
{
    /** @var string */
    private $apiName;

    /** @var int */
    private $responseTimeMs;

    /** @var int */
    private $count;

    public function __construct(string $apiName, int $responseTimeMs, int $count = 1)
    {
        $this->apiName = $apiName;
        $this->responseTimeMs = $responseTimeMs;
        $this->count = $count;
    }

    /**
     * This function returns the cache keys to set and the
     * values to set them to.
     */
    public function getCacheKeyValuePairs(): array
    {
        return [
            $this->cacheKey('count') => $this->count,
            $this->cacheKey('time') => $this->responseTimeMs,
        ];
    }

    /**
     * Accepts the format returned by getCacheKeyValuePairs, but sets
     * the values in the class from values returned from the cache.
     */
    public function setValuesFromCacheKeyValuePairs(array $keyValuePairs): void
    {
        $this->count = $keyValuePairs[$this->cacheKey('count')];
        $this->responseTimeMs = $keyValuePairs[$this->cacheKey('time')];
    }

    public function getMetricData(): array
    {
        $dimensions = [
            [ 'Name' => 'api', 'Value' => $this->apiName ],
            [ 'Name' => 'response_type', 'Value' => 'All'],
        ];
        return [
            [
                'MetricName' => 'api_time',
                'Dimensions' => $dimensions,
                'Value' => $this->getAverageResponseTime(),
                'Unit' => 'Milliseconds',
            ],
            [
                'MetricName' => 'api_count',
                'Dimensions' => $dimensions,
                'Value' => $this->count,
                'Unit' => 'Count',
            ],
        ];
    }

    private function cacheKey(string $type)
    {
        return implode('#', ['api_time', $this->apiName, $type]);
    }

    private function getAverageResponseTime()
    {
        if ($this->count > 1) {
            return $this->responseTimeMs / $this->count;
        }
        return $this->responseTimeMs;
    }
}