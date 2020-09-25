<?php
declare(strict_types = 1);
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\TranslatorInterface;

class ResponseSubscriber implements EventSubscriberInterface
{
    private $translator;

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => [['updateHeaders', 0]],
        ];
    }

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function updateHeaders(FilterResponseEvent $event)
    {
        $response = $event->getResponse();

        // Don't run on subrequests, such as when handling exceptions
        if (!$event->isMasterRequest()) {
            return;
        }

        // Always add the following headers to vary on, so that differences in
        // these headers are cached separately. Add these to any already
        // existing value rather than overwriting.
        $response->setVary(['X-CDN'], false);

        // X-UA-Compatible header choose what version of Internet Explorer the page should be rendered as.
        // Only affects IE8, 9 and 10
        $response->headers->set('X-UA-Compatible', 'IE=edge');

        $languageCode = $this->translator->trans('language_code');
        // Content-Language is used to describe the language(s) intended for the audience
        $response->headers->set('Content-Language', $languageCode);

        // "Fix" vary headers for Varnish. Despite multiple vary headers being RFC compliant
        // varnish does not like it. Join them into a single header here.
        $varyHeaders = $response->getVary();
        $varyHeadersString = join(',', $varyHeaders);
        $response->setVary([$varyHeadersString], true);

        $event->setResponse($response);
    }
}
