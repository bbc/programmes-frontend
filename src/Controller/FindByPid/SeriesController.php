<?php
declare(strict_types = 1);
namespace App\Controller\FindByPid;

use App\Controller\Helpers\Breadcrumbs;
use App\Controller\Helpers\StructuredDataHelper;
use App\DsAmen\Factory\PresenterFactory;
use App\DsShared\Factory\HelperFactory;
use App\ExternalApi\Ada\Service\AdaClassService;
use App\ExternalApi\Electron\Service\ElectronService;
use App\ExternalApi\LxPromo\Service\LxPromoService;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Service\CollapsedBroadcastsService;
use BBC\ProgrammesPagesService\Service\ImagesService;
use BBC\ProgrammesPagesService\Service\ProgrammesAggregationService;
use BBC\ProgrammesPagesService\Service\ProgrammesService;
use BBC\ProgrammesPagesService\Service\PromotionsService;
use BBC\ProgrammesPagesService\Service\RelatedLinksService;
use Symfony\Component\HttpFoundation\Request;

class SeriesController extends BaseProgrammeContainerController
{
    public function __invoke(
        PresenterFactory $presenterFactory,
        Request $request,
        ProgrammeContainer $programme,
        ProgrammesService $programmesService,
        PromotionsService $promotionsService,
        CollapsedBroadcastsService $collapsedBroadcastsService,
        ProgrammesAggregationService $aggregationService,
        ImagesService $imagesService,
        ElectronService $electronService,
        AdaClassService $adaClassService,
        HelperFactory $helperFactory,
        RelatedLinksService $relatedLinksService,
        LxPromoService $lxPromoService,
        StructuredDataHelper $structuredDataHelper,
        Breadcrumbs $breadcrumbs
    ) {
        $this->setAtiContentLabels('series', 'series');
        $noIndexBrands = [
            'b006pfjx', // North West Tonight
            'b007t9y1', // Match of the Day
            'p00yzlr0', // Line of Duty
            'p070npjv', // Fleabag
            'b006v5y2', // Saturday Kitchen
            'b006mgyl', // BBC News
            'm000lxp1', // Powering Britain
            'b08s3bgz',  // Impossible
        ];
        if (in_array($programme->getTleo()->getPid(), $noIndexBrands)) {
            $this->metaNoIndex = true;
        }
        return parent::__invoke(
            $presenterFactory,
            $request,
            $programme,
            $programmesService,
            $promotionsService,
            $collapsedBroadcastsService,
            $aggregationService,
            $imagesService,
            $electronService,
            $adaClassService,
            $helperFactory,
            $relatedLinksService,
            $lxPromoService,
            $structuredDataHelper,
            $breadcrumbs
        );
    }

    protected function hasPriorityPromotion(
        ProgrammeContainer $programme,
        array $promotions,
        bool $shouldDisplayMiniMap
    ): bool {
        return false;
    }

    protected function shouldDisplayLxPromo(ProgrammeContainer $programme): bool
    {
        return false;
    }

    protected function shouldDisplayMiniMap(Request $request, ProgrammeContainer $programme, bool $isVotePriority, bool $hasLxPromo): bool
    {
        return false;
    }

    protected function shouldDisplayPriorityText(): bool
    {
        return false;
    }

    protected function shouldDisplayVote(): bool
    {
        return false;
    }
}
