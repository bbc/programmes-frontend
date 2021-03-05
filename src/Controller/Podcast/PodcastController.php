<?php
declare(strict_types=1);

namespace App\Controller\Podcast;

use App\Controller\BaseController;
use App\Controller\Helpers\Breadcrumbs;
use App\DsShared\Utilities\Paginator\PaginatorPresenter;
use BBC\ProgrammesPagesService\Domain\Entity\Collection;
use BBC\ProgrammesPagesService\Domain\Entity\CoreEntity;
use BBC\ProgrammesPagesService\Domain\Entity\Programme;
use App\Controller\Helpers\StructuredDataHelper;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Service\PodcastsService;
use BBC\ProgrammesPagesService\Service\ProgrammesAggregationService;
use BBC\ProgrammesPagesService\Service\PromotionsService;
use BBC\ProgrammesPagesService\Service\VersionsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Podcast full page. Future implementation.
 *
 * When a user click in podcast panel, it takes you to this full page.
 */
class PodcastController extends BaseController
{
    public function __invoke(
        CoreEntity $coreEntity,
        PodcastsService $podcastsService,
        ProgrammesAggregationService $programmesAggregationService,
        PromotionsService $promotionsService,
        VersionsService $versionsService,
        StructuredDataHelper $structuredDataHelper,
        UrlGeneratorInterface $router,
        Breadcrumbs $breadcrumbs
    ) {
        if ((!$coreEntity instanceof Collection) && !$coreEntity->isTleo()) {
            return $this->cachedRedirectToRoute('programme_podcast_episodes_download', ['pid' => $coreEntity->getTleo()->getPid()], 301);
        }

        if (!$coreEntity instanceof ProgrammeContainer && !$coreEntity instanceof Collection) {
            throw new NotFoundHttpException(sprintf('Core Entity with PID "%s" is not a programme or collection', $coreEntity->getPid()));
        }

        $this->setAtiContentLabels('downloads', 'guide-podcasts');
        $this->setContextAndPreloadBranding($coreEntity);
        $this->setAtiContentId((string) $coreEntity->getPid(), 'pips');

        $this->overridenDescription = 'Podcast downloads for ' . $coreEntity->getTitle();
        $podcast = $podcastsService->findByCoreEntity($coreEntity);
        $page = $this->getPage();
        $limit = 30;

        $programme = null;
        if ($coreEntity instanceof Collection) {
            $programme = $coreEntity->getParent();
            $downloadableVersions = $versionsService->findDownloadableForGroupsDescendantEpisodes($coreEntity, $limit, $page);
            $downloadableEpisodesCount = $versionsService->countDownloadableForGroupsDescendantEpisodes($coreEntity);
        } else {
            $programme = $coreEntity;
            $downloadableVersions = $versionsService->findDownloadableForProgrammesDescendantEpisodes($coreEntity, $limit, $page);
            $downloadableEpisodesCount = $versionsService->countDownloadableForProgrammesDescendantEpisodes($coreEntity);
        }

        if (!$coreEntity->isPodcastable() && $downloadableEpisodesCount == 0) {
            throw new NotFoundHttpException('No downloadable episodes for this programme');
        }

        $paginator = null;

        if ($downloadableEpisodesCount > $limit) {
            $paginator = new PaginatorPresenter($page, $limit, $downloadableEpisodesCount);
        }

        $promotions = $promotionsService->findAllActivePromotionsByEntityGroupedByType($coreEntity);
        $genre = null;
        if ($programme) {
            $genres = $programme->getGenres();
            $genre = reset($genres);
        }
        if ($genre) {
            $genre = $genre->getTopLevel();
        }

        $schema = $this->getSchema($structuredDataHelper, $programme, $downloadableVersions, $coreEntity);

        switch ($coreEntity->getType()) {
            case 'collection':
                $soundsSubscribeUrl = $router->generate('sounds_collection', [
                    'collectionBrandPid' => $coreEntity->getTleo()->getPid(),
                ], UrlGeneratorInterface::ABSOLUTE_URL);
                break;
            default:
                $soundsSubscribeUrl = $router->generate('sounds_brand', [
                    'brandPid' => $coreEntity->getPid(),
                ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $this->breadcrumbs = $breadcrumbs
            ->forNetwork($coreEntity->getNetwork())
            ->forEntityAncestry($coreEntity)
            ->forRoute('Downloads', 'programme_podcast_episodes_download', ['pid' => $coreEntity->getPid()])
            ->toArray();

        return $this->renderWithChrome('podcast/podcast.html.twig', [
            'schema' => $schema,
            'programme' => $programme,
            'entity' => $coreEntity,
            'podcast' => $podcast,
            'downloadableVersions' => $downloadableVersions,
            'paginatorPresenter' => $paginator,
            'promotions' => $promotions,
            'genre' => $genre,
            'soundsSubscribeUrl' => $soundsSubscribeUrl,
        ]);
    }

    private function getSchema(StructuredDataHelper $structuredDataHelper, ?Programme $programme, array $availableEpisodes, CoreEntity $coreEntity): array
    {
        if ($coreEntity instanceof Collection) {
            $schemaContext = $structuredDataHelper->getSchemaForCollection($coreEntity, $programme);
        } else {
            $schemaContext = $structuredDataHelper->getSchemaForProgrammeContainerAndParents($programme);
        }
        foreach ($availableEpisodes as $episode) {
            $episodeSchema = $structuredDataHelper->getSchemaForEpisode($episode->getProgrammeItem(), false);
            $episodeSchema['publication'] = $structuredDataHelper->getSchemaForOnDemand($episode->getProgrammeItem());
            $schemaContext['hasPart'][] = $episodeSchema;
        }

        return $structuredDataHelper->prepare($schemaContext);
    }
}
