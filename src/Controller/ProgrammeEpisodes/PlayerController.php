<?php
declare(strict_types = 1);

namespace App\Controller\ProgrammeEpisodes;

use App\Controller\Helpers\Breadcrumbs;
use App\Controller\Helpers\StructuredDataHelper;
use App\Ds2013\Factory\PresenterFactory;
use App\DsShared\Utilities\Paginator\PaginatorPresenter;
use BBC\ProgrammesPagesService\Domain\Entity\Episode;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Service\CollapsedBroadcastsService;
use BBC\ProgrammesPagesService\Service\ProgrammesAggregationService;

class PlayerController extends BaseProgrammeEpisodesController
{
    public function __invoke(
        CollapsedBroadcastsService $collapsedBroadcastService,
        PresenterFactory $presenterFactory,
        ProgrammeContainer $programme,
        ProgrammesAggregationService $programmeAggregationService,
        StructuredDataHelper $structuredDataHelper,
        Breadcrumbs $breadcrumbs
    ) {
        $this->setContextAndPreloadBranding($programme);
        $this->setInternationalStatusAndTimezoneFromContext($programme);
        $this->setAtiContentLabels('list-tleo', 'guide-available');
        $this->setAtiContentId((string) $programme->getPid(), 'pips');

        $page = $this->getPage();
        $limit = 10;

        $availableEpisodes = $programmeAggregationService->findStreamableOnDemandEpisodes(
            $programme,
            $limit,
            $page
        );

        // If you visit an out-of-bounds page then throw a 404. Page one should
        // always be a 200 so search engines don't drop their reference to the
        // page while a programme is off-air
        if (!$availableEpisodes && $page !== 1) {
            throw $this->createNotFoundException('Page does not exist');
        }

        $availableEpisodesCount = $programme->getAvailableEpisodesCount();

        $paginator = null;
        if ($availableEpisodesCount > $limit) {
            $paginator = new PaginatorPresenter($page, $limit, $availableEpisodesCount);
        }

        $subNavPresenter = $this->getSubNavPresenter($collapsedBroadcastService, $programme, $presenterFactory);
        $schema = $this->getSchema($structuredDataHelper, $programme, $availableEpisodes);

        $this->overridenDescription = 'Available episodes of ' . $programme->getTitle();

        $opts = ['pid' => $programme->getPid()];
        $this->breadcrumbs = $breadcrumbs
            ->forNetwork($programme->getNetwork())
            ->forEntityAncestry($programme)
            ->forRoute('Episodes', 'programme_episodes', $opts)
            ->forRoute('Available', 'programme_episodes_player', $opts)
            ->toArray();

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

        return $this->renderWithChrome('programme_episodes/player.html.twig', [
            'programme' => $programme,
            'availableEpisodes' => $availableEpisodes,
            'paginatorPresenter' => $paginator,
            'subNavPresenter' => $subNavPresenter,
            'schema' => $schema,
        ]);
    }

    /**
     * @param StructuredDataHelper $structuredDataHelper
     * @param ProgrammeContainer $programmeContainer
     * @param Episode[] $availableEpisodes
     * @return array
     */
    private function getSchema(StructuredDataHelper $structuredDataHelper, ProgrammeContainer $programmeContainer, array $availableEpisodes): array
    {
        $schemaContext = $structuredDataHelper->getSchemaForProgrammeContainerAndParents($programmeContainer);

        foreach ($availableEpisodes as $episode) {
            $episodeSchema = $structuredDataHelper->getSchemaForEpisode($episode, false);
            $episodeSchema['publication'] = $structuredDataHelper->getSchemaForOnDemand($episode);
            $schemaContext['episode'][] = $episodeSchema;
        }

        return $structuredDataHelper->prepare($schemaContext);
    }
}
