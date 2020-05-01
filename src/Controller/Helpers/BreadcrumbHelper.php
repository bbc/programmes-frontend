<?php
declare(strict_types = 1);

namespace App\Controller\Helpers;

use App\ValueObject\Breadcrumb;
use App\Twig\UrlKeyExtension;
use BBC\ProgrammesPagesService\Domain\Entity\Category;
use BBC\ProgrammesPagesService\Domain\Entity\CoreEntity;
use BBC\ProgrammesPagesService\Domain\Entity\Genre;
use BBC\ProgrammesPagesService\Domain\Entity\Network;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BreadcrumbHelper
{
    /** @var UrlGeneratorInterface */
    private $router;

    /** @var UrlHelper */
    private $urlHelper;

    public function __construct(
        UrlGeneratorInterface $router,
        UrlHelper $urlHelper
    ) {
        $this->router = $router;
        $this->urlHelper = $urlHelper;
    }

    public function createBreadcrumbForRoute(string $title, string $routeName, array $parameters = []): Breadcrumb
    {
        return new Breadcrumb(
            $title,
            $this->router->generate($routeName, $parameters, UrlGeneratorInterface::ABSOLUTE_URL)
        );
    }

    public function getNetworkBreadcrumb(?Network $network): Breadcrumb
    {
        if ($network && !$network->isRadio()) {
            $urlKeyExtension = new UrlKeyExtension();
            $networkLink = $urlKeyExtension->networkLink($network);
            if ($networkLink !== '') {
                return new Breadcrumb(
                    $network->getName(),
                    $this->urlHelper->getAbsoluteUrl($networkLink)
                );
            }
        }

        return $this->createBreadcrumbForRoute('Programmes', 'home');
    }

    public function getCoreEntityAncestryBreadcrumbs(CoreEntity $coreEntity): array
    {
        $breadcrumbs = [];

        do {
            $breadcrumbs[] = $this->createBreadcrumbForRoute(
                $coreEntity->getTitle(),
                'find_by_pid',
                ['pid' => $coreEntity->getPid()]
            );
        } while ($coreEntity = $coreEntity->getParent());

        return array_reverse($breadcrumbs);
    }

    public function getCategoryAncestryBreadcrumbs(Category $category): array
    {
        if ($category instanceof Genre) {
            $breadcrumbs = [];

            do {
                $breadcrumbs[] = $this->createBreadcrumbForRoute(
                    $category->getTitle(),
                    'category_metadata',
                    [
                        'categoryType' => 'genres',
                        'categoryHierarchy' => $category->getUrlKeyHierarchy(),
                    ]
                );
            } while ($category = $category->getParent());

            return array_reverse($breadcrumbs);
        }

        return [
            $this->createBreadcrumbForRoute(
                $category->getTitle(),
                'category_metadata',
                [
                    'categoryType' => 'formats',
                    'categoryHierarchy' => $category->getUrlKeyHierarchy(),
                ]
            ),
        ];
    }
}
