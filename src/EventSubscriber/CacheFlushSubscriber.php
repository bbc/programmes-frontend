<?php
declare(strict_types = 1);

namespace App\EventSubscriber;

use App\Branding\LogoVersionStrategy;
use BBC\BrandingClient\BrandingClient;
use BBC\BrandingClient\OrbitClient;
use BBC\ProgrammesCachingLibrary\Cache;
use BBC\ProgrammesCachingLibrary\CacheWithResilience;
use BBC\ProgrammesMorphLibrary\MorphClient;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CacheFlushSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    /** @var ContainerInterface */
    private $container;

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [['setupCacheFlush', 512]],
        ];
    }

    public static function getSubscribedServices()
    {
        return [LogoVersionStrategy::class, BrandingClient::class, OrbitClient::class, Cache::class, CacheWithResilience::class, MorphClient::class];
    }

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function setupCacheFlush(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if ($event->getRequest()->query->has('__flush_cache')) {
            $cache = $this->container->get(Cache::class);
            $cache->setFlushCacheItems(true);

            $cacheWithResilience = $this->container->get(CacheWithResilience::class);
            $cacheWithResilience->setFlushCacheItems(true);

            $morphCache = $this->container->get(MorphClient::class);
            $morphCache->setFlushCacheItems(true);

            $logosVersions = $this->container->get(LogoVersionStrategy::class);
            $logosVersions->flushCache();
        }
    }
}
