<?php

namespace App\Branding;

use App\ValueObject\CosmosInfo;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use App\Branding\LogoConfig;

class LogoVersionStrategy implements VersionStrategyInterface
{
    /**
     * @var LogoConfig
     */
    public $logoConfig;

    private $client;
    private $logger;
    private $cache;

    private $s3Base;
    private $s3ManifestPathFormat;
    private $cosmosInfo;
    private $cacheTTL;

    // if the cache has been cleared in this request, then do not quantize cache-busting
    private $cacheCleared = false;

    private const FORMAT = '%s/%s';

    public function __construct(
        Client $httpClient,
        AbstractAdapter $cache,
        LoggerInterface $logger,
        string $s3Base,
        string $s3ManifestPathFormat,
        CosmosInfo $cosmosInfo,
        int $cacheTTL = 0
    ) {
        $this->client = $httpClient;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->cacheTTL = $cacheTTL;    // need this to cache-bust Akamai/S3
        $this->cosmosInfo = $cosmosInfo;
        $this->s3Base = $s3Base;
        $this->s3ManifestPathFormat = $s3ManifestPathFormat;
    }

    /**
     * returns from manifest the value for this key
     * @param string $path
     * @return string
     */
    public function getVersion($path): string
    {
        return $this->getLogoConfig()->getPath($path) ?? '';
    }

    /**
     * returns the formatted url for this asset file/path
     * @param string $path
     * @return string
     */
    public function applyVersion($path): string
    {
        $version = $this->getVersion($path);
        return $version ? $this->url($version) : $path;
    }

    /**
     * Get the logo manifest itself.
     * @return LogoConfig
     */
    public function getLogoConfig(): LogoConfig
    {
        return $this->logoConfig ?: $this->loadLogoConfig();
    }

    public function flushCache(): bool
    {
        return $this->cacheCleared = $this->cache->clear();
    }

    protected function loadLogoConfig($forceRefresh = false): LogoConfig
    {
        $env = $this->cosmosInfo->getAppEnvironment();
        $manifestPath = str_replace('$env$', $env, $this->s3ManifestPathFormat);
        $path = $this->url($manifestPath);

        $cacheKey = md5($path) . '.s3';
        try {
            if ($forceRefresh) {
                $this->cache->delete($cacheKey);
            }

            $json = $this->cache->get($cacheKey, function (ItemInterface $item) use ($path, $forceRefresh) {
                $ttl = $this->cacheTTL;
                $item->expiresAfter($ttl);
                $mt = microtime(true);
                if (!($this->cacheCleared || $forceRefresh)) {
                    $mt = floor($mt / $ttl) * $ttl;
                }
                $path .= is_null(parse_url($path, PHP_URL_QUERY)) ? "?_t_={$mt}" : "&_t_={$mt}";
                try {
                    $response = $this->client->get($path, ['headers' => ['Accept' => 'application/json']]);
                    $json = $response->getBody()->getContents();
                    $this->cacheCleared = false;
                } catch (\Exception $e) {
                    $json = null;
                    $this->logger->error("could not fetch {$path} " . $e->getMessage());
                }

                return $json;
            });
            $this->logoConfig = LogoConfig::fromJSON($json);
        } catch (\Exception $e) {
            $this->logger->error("error deserialising " . $e->getMessage());
        }

        return $this->logoConfig;
    }

    private function url(string $path): string
    {
        return sprintf(self::FORMAT, $this->s3Base, $path);
    }
}
