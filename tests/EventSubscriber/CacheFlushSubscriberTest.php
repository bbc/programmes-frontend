<?php
declare(strict_types = 1);
namespace Tests\App\EventSubscriber;

use App\Branding\LogoVersionStrategy;
use App\EventSubscriber\CacheFlushSubscriber;
use BBC\ProgrammesCachingLibrary\Cache;
use BBC\ProgrammesCachingLibrary\CacheWithResilience;
use BBC\ProgrammesMorphLibrary\MorphClient;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class CacheFlushSubscriberTest extends TestCase
{
    public function testCacheFlushSubscriber()
    {
        $cache = $this->createMock(Cache::class);
        $cache->expects($this->once())->method('setFlushCacheItems')->with(true);

        $cacheWithResilience = $this->createMock(CacheWithResilience::class);
        $cacheWithResilience->expects($this->once())->method('setFlushCacheItems')->with(true);

        $morph = $this->createMock(MorphClient::class);
        $morph->expects($this->once())->method('setFlushCacheItems')->with(true);

        $logoVersionMock = $this->createMock(LogoVersionStrategy::class);
        $logoVersionMock->expects($this->once())->method('flushCache');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->will($this->returnValueMap([
            [Cache::class, $cache],
            [CacheWithResilience::class, $cacheWithResilience],
            [MorphClient::class, $morph],
            [LogoVersionStrategy::class, $logoVersionMock],
        ]));

        $request = new Request(['__flush_cache' => '']);
        $cacheFlushSubscriber = new CacheFlushSubscriber($container);
        $cacheFlushSubscriber->setupCacheFlush($this->event($request));
    }

    private function event(Request $request, bool $isMasterRequest = true)
    {
        return new GetResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            $isMasterRequest ? HttpKernelInterface::MASTER_REQUEST : HttpKernelInterface::SUB_REQUEST
        );
    }
}
