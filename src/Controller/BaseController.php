<?php
declare(strict_types = 1);

namespace App\Controller;

use App\Branding\BrandingPlaceholderResolver;
use App\Controller\Helpers\StructuredDataHelper;
use App\Cosmos\Dials;
use App\Ds2013\Factory\PresenterFactory;
use App\DsShared\Factory\HelperFactory;
use App\ValueObject\AnalyticsCounterName;
use App\ValueObject\AtiAnalyticsLabels;
use App\ValueObject\Breadcrumb;
use App\ValueObject\CosmosInfo;
use App\ValueObject\MetaContext;
use BBC\BrandingClient\Branding;
use BBC\BrandingClient\BrandingClient;
use BBC\BrandingClient\BrandingException;
use BBC\BrandingClient\OrbitClient;
use BBC\ProgrammesPagesService\Domain\ApplicationTime;
use BBC\ProgrammesPagesService\Domain\Entity\CoreEntity;
use BBC\ProgrammesPagesService\Domain\Entity\Image;
use BBC\ProgrammesPagesService\Domain\Entity\Network;
use BBC\ProgrammesPagesService\Domain\Entity\Programme;
use BBC\ProgrammesPagesService\Domain\Entity\Service;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;

abstract class BaseController extends AbstractController
{
    protected $context;

    private $brandingId = 'br-00001';

    /**
     * Used in case a page requests a BrandingID that does not exist.
     * This may be changed depending upon the $context
     */
    private $fallbackBrandingId = 'br-00001';

    /** @var PromiseInterface */
    private $brandingPromise;

    /** @var Branding */
    private $branding;

    private $response;

    private $atistatsExtraLabels = [];

    private $atiContentId = "urn:bbc:pips";

    /** @var string */
    private $atiContentType;

    /** @var string */
    private $atiChapterOne;

    /** @var string */
    private $atiOverriddenEntityTitle;

    private $isInternational = false;

    protected $canonicalUrl;

    /** @var string */
    private $orbitSearchScope;

    /** @var bool */
    protected $metaNoIndex;

    /** @var ?string */
    protected $overridenDescription;

    /** @var ?Image */
    protected $overridenImage;

    /** @var Breadcrumb[] */
    protected $breadcrumbs = [];

    public static function getSubscribedServices()
    {
        return array_merge(parent::getSubscribedServices(), [
            'logger' => LoggerInterface::class,
            BrandingClient::class,
            BrandingPlaceholderResolver::class,
            OrbitClient::class,
            TranslatorInterface::class,
            CosmosInfo::class,
            Dials::class,
            PresenterFactory::class,
            HelperFactory::class,
            StructuredDataHelper::class,
        ]);
    }

    public function __construct()
    {
        $this->response = new Response();
        // It is required to set the cache-control header when creating the response object otherwise Symfony
        // will create and set its value to "no-cache, private" by default
        $this->response()->setPublic()->setMaxAge(120);
        // The page can only be displayed in a frame on the same origin as the page itself.
        $this->response()->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Blocks a request if the requested type is different from the MIME type
        $this->response()->headers->set('X-Content-Type-Options', 'nosniff');
    }

    protected function getCanonicalUrl(): string
    {
        if (!isset($this->canonicalUrl)) {
            $requestAttributes = $this->container->get('request_stack')->getMasterRequest()->attributes;
            $this->canonicalUrl = $this->generateUrl(
                $requestAttributes->get('_route'),
                $requestAttributes->get('_route_params'),
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }
        return $this->canonicalUrl;
    }

    protected function getPage(): int
    {
        $pageString = $this->request()->query->get(
            'page',
            '1'
        );

        if (ctype_digit($pageString)) {
            $page = (int) $pageString;
            // Have a controlled upper-bound to stop people putting in clearly
            // absurdly large page sizes
            if ($page >= 1 && $page <= 9999) {
                return $page;
            }
        }

        throw $this->createNotFoundException('Page parameter must be a number between 1 and 9999');
    }

    protected function overrideBrandingOption(string $key, $value): void
    {
        // International services could do a second request that renders a version of the page without
        // chrome (WithoutChrome) that version doesn't have branding
        if (isset($this->branding)) {
            $this->branding->overrideOption($key, $value);
        }
    }

    protected function setBrandingId(string $brandingId)
    {
        $this->brandingId = $brandingId;
    }

    protected function setContext($context)
    {
        $this->context = $context;

        // br-00002 is the default 'Programme Variant' - use that when we're
        // displaying programme/group/service pages.
        $network = null;
        if ($context instanceof CoreEntity) {
            $this->setBrandingId($context->getOption('branding_id'));
            $this->fallbackBrandingId = 'br-00002';
            $network = $context->getNetwork();
        } elseif ($context instanceof Network) {
            $this->setBrandingId($context->getOption('branding_id'));
            $this->fallbackBrandingId = 'br-00002';
            $network = $context;
        } elseif ($context instanceof Service) {
            $this->setBrandingId($context->getNetwork()->getOption('branding_id'));
            $this->fallbackBrandingId = 'br-00002';
            $network = $context->getNetwork();
        }
        $this->orbitSearchScope = $this->calculateOrbitSearchScope($network);
    }


    protected function setContextAndPreloadBranding($context)
    {
        $this->setContext($context);
        $this->getBrandingPromise();
    }

    /**
     * This function check if the context is international and if this is the case sets the time zone to UTC and
     * set $this->isInternational to true so the twig template can render the required JavaScript
     *
     * @param mixed $context
     * @throws Exception
     */
    protected function setInternationalStatusAndTimezoneFromContext($context): void
    {
        $network = null;
        if ($context instanceof CoreEntity || $context instanceof Service) {
            $network = $context->getNetwork();
        } elseif ($context instanceof Network) {
            $network = $context;
        } else {
            throw new Exception('isInternational method is not implemented by the provided context');
        }
        if ($network) {
            $this->isInternational = $network->isInternational();
        }
        if ($this->isInternational) {
            // "International" services are UTC, all others are Europe/London (the default)
            ApplicationTime::setLocalTimeZone('UTC');
        }
    }

    protected function response(): Response
    {
        return $this->response;
    }

    protected function resolvePromises(array $promises): array
    {
        if (isset($this->brandingPromise)) {
            $promises['branding'] = $this->brandingPromise;
        }
        $unwrapped = \GuzzleHttp\Promise\unwrap($promises);
        if (isset($unwrapped['branding'])) {
            $this->branding = $unwrapped['branding'];
            unset($unwrapped['branding']);
        }
        return $unwrapped;
    }

    protected function renderWithChrome(string $view, array $parameters = [])
    {
        $context = $this->context;
        $this->preRender();
        $cosmosInfo = $this->container->get(CosmosInfo::class);

        // SMP still need this for monitoring and analytics
        $this->createAnalyticsCounterNameFromContext();
        $atiAnalyticsLabelsValues = new AtiAnalyticsLabels(
            $this->container->get(HelperFactory::class)->getProducerVariableHelper(),
            $this->container->get(HelperFactory::class)->getDestinationVariableHelper(),
            $context,
            $cosmosInfo,
            $this->atistatsExtraLabels,
            $this->atiContentType,
            $this->atiChapterOne,
            $this->atiContentId,
            $this->atiOverriddenEntityTitle
        );

        if ($context instanceof CoreEntity) {
            $tleo = $context->getTleo();
            $atiAnalyticsLabelsValues->setStreamingAvailability(
                ($tleo instanceof Programme) && $tleo->isStreamable()
            );
        }

        $orb = $this->container->get(OrbitClient::class)->getContent([
            'variant' => $this->branding->getOrbitVariant(),
            'language' => $this->branding->getLanguage(),
        ], [
            'page' => $atiAnalyticsLabelsValues->orbLabels(),
            'searchScope' => $this->orbitSearchScope,
            'skipLinkTarget' => 'programmes-content',
        ]);

        $structuredDataHelper = $this->container->get(StructuredDataHelper::class);

        $parameters = array_merge([
            'appVersion' => $this->container->get(CosmosInfo::class)->getAppVersion(),
            'orb' => $orb,
            'meta_context' => $this->createMetaContextFromContext(),
            'branding' => $this->branding,
            'with_chrome' => true,
            'is_international' => $this->isInternational,
        ], $parameters);

        if (count($this->breadcrumbs) > 0) {
            $parameters['breadcrumbs'] = $structuredDataHelper->prepare(
                $structuredDataHelper->getSchemaForBreadcrumbs($this->breadcrumbs)
            );
        }

        return $this->render($view, $parameters, $this->response);
    }

    protected function renderWithoutChrome(string $view, array $parameters = [])
    {
        $this->preRender();
        $parameters['with_chrome'] = false;
        return $this->render($view, $parameters, $this->response);
    }

    protected function createMetaContextFromContext(): MetaContext
    {
        return new MetaContext(
            $this->context,
            $this->getCanonicalUrl(),
            $this->getMetaNoIndex(),
            $this->overridenDescription,
            $this->overridenImage
        );
    }

    protected function createAnalyticsCounterNameFromContext(): AnalyticsCounterName
    {
        $analyticsCounterName = new AnalyticsCounterName($this->context, $this->request()->getPathInfo());
        $this->container->get(PresenterFactory::class)->setAnalyticsCounterName($analyticsCounterName);
        return $analyticsCounterName;
    }


    /**
     * Returns a RedirectResponse to the given URL.
     * This picks up the default cache configuration of $this->response that was
     * set in the constructor, unlike ->redirect()
     *
     * @param string $url    The URL to redirect to
     * @param int    $status The status code to use for the Response
     * @param int    $cacheLifetime Seconds the response should be cached for
     *
     * @return RedirectResponse
     */
    protected function cachedRedirect($url, $status = 302, int $cacheLifetime = null): RedirectResponse
    {
        if ($cacheLifetime !== null) {
            $this->response()->setPublic()->setMaxAge($cacheLifetime);
        }
        $headers = $this->response->headers->all();
        return new RedirectResponse($url, $status, $headers);
    }

    /**
     * Returns a RedirectResponse to the given route with the given parameters.
     *  * This picks up the default cache configuration of $this->response that was
     * set in the constructor, unlike ->redirect()
     *
     * @param string $route The name of the route
     * @param array $parameters An array of parameters
     * @param int $status The status code to use for the Response
     * @param int|null $lifetime The varnish cache lifetime
     *
     * @return RedirectResponse
     */
    protected function cachedRedirectToRoute($route, array $parameters = array(), $status = 302, int $lifetime = null): RedirectResponse
    {
        return $this->cachedRedirect($this->generateUrl($route, $parameters), $status, $lifetime);
    }

    protected function addAtiStatsExtraLabels(array $labels): void
    {
        $this->atistatsExtraLabels = array_replace($this->atistatsExtraLabels, $labels);
    }

    protected function setAtiContentId(?string $identifier, string $authority = 'pips'): void
    {
        if ($identifier) {
            $identifier = ':' . $identifier;
        }
        $this->atiContentId = 'urn:bbc:' . $authority . $identifier;
    }

    protected function setAtiContentLabels(string $type, string $chapter): void
    {
        $this->atiContentType = $type;
        $this->atiChapterOne = $chapter;
    }

    protected function setAtiOverriddenEntityTitle(string $title): void
    {
        $this->atiOverriddenEntityTitle = $title;
    }

    protected function request(): Request
    {
        return $this->container->get('request_stack')->getCurrentRequest();
    }

    protected function preRender()
    {
        if (!$this->branding) {
            $this->branding = $this->getBrandingPromise()->wait(true);
        }
    }

    private function getBrandingPromise(): PromiseInterface
    {
        if (!isset($this->brandingPromise)) {
            $brandingClient = $this->container->get(BrandingClient::class);
            $previewId = $this->request()->query->get($brandingClient::PREVIEW_PARAM, null);
            $usePreview = !is_null($previewId);


            $this->logger()->info(
                'Using BrandingID "{0}"' . ($usePreview ? ', but overridden previewing theme version "{1}"' : ''),
                $usePreview ? [$this->brandingId, $previewId] : [$this->brandingId]
            );

            $brandingPromise = $brandingClient->getContentAsync(
                $this->brandingId,
                $previewId
            );

            $this->brandingPromise = $brandingPromise->then(
                \Closure::fromCallable([$this, 'fulfilledBrandingPromise']),
                function ($reason) use ($usePreview, $previewId) {
                    return $this->rejectedBrandingPromise($reason, $usePreview, $previewId);
                }
            );
        }
        return $this->brandingPromise;
    }

    private function fulfilledBrandingPromise(Branding $branding)
    {
        // We only need to change the translation language if it is different
        // to the language the translation extension was initially created with
        $locale = $branding->getLocale();
        $translator = $this->container->get(TranslatorInterface::class);

        $translator->setLocale($locale);

        // Resolve branding placeholders
        if ($this->context) {
            $branding = $this->container->get(BrandingPlaceholderResolver::class)->resolve(
                $branding,
                $this->context
            );
        }

        return $branding;
    }

    private function rejectedBrandingPromise($reason, $usePreview, $previewId)
    {
        if ($reason instanceof BrandingException) {
            // Could not find that branding id (or preview id), someone probably
            // mistyped it. Use a default branding instead of blowing up.
            $this->logger()->warning(
                'Requested BrandingID "{0}"' . ($usePreview ? ', but overridden previewing theme version "{2}"' : '') .
                ' but it was not found. Using "{1}" as a fallback',
                $usePreview ? [$this->brandingId, $this->fallbackBrandingId, $previewId] : [$this->brandingId, $this->fallbackBrandingId]
            );

            $this->setBrandingId($this->fallbackBrandingId);
            $brandingClient = $this->container->get(BrandingClient::class);
            $branding = $brandingClient->getContent($this->brandingId, null);
            return $this->fulfilledBrandingPromise($branding);
        }
        if ($reason instanceof Exception) {
            throw $reason;
        }
        throw new RuntimeException("An unknown error occurred fetching branding");
    }

    private function logger(): LoggerInterface
    {
        return $this->container->get('logger');
    }

    private function getMetaNoIndex(): bool
    {
        if (!isset($this->metaNoIndex)) {
            $this->metaNoIndex = false;
        }

        return $this->metaNoIndex;
    }

    private function calculateOrbitSearchScope(?Network $network): string
    {
        if (!$network) {
            return '';
        };
        $serviceSearchScopes = [
            'cbbc' => 'cbbc',
            'cbeebies' => 'cbeebies',
            'bbc_radio_cymru' => 'cymru',
            'bbc_radio_cymru_mwy' => 'cymru',
        ];
        $nid = (string) $network->getNid();
        if (array_key_exists($nid, $serviceSearchScopes)) {
            return $serviceSearchScopes[$nid];
        }
        if ($network->isRadio()) {
            return 'sounds';
        }
        return '';
    }
}
