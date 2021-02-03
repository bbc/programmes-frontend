<?php
declare(strict_types=1);

namespace Tests\App\DataFixtures\ORM\ProgrammeEpisodes;

use BBC\ProgrammesPagesService\Data\ProgrammesDb\Entity\Episode;
use BBC\ProgrammesPagesService\Data\ProgrammesDb\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Data\ProgrammesDb\Entity\Version;
use BBC\ProgrammesPagesService\Data\ProgrammesDb\Entity\VersionType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class EpisodesFixtures extends AbstractFixture implements DependentFixtureInterface
{
    /** @var ObjectManager $manager */
    private $manager;

    /**
     * This method must return an array of fixtures classes
     * on which the implementing class depends on
     *
     * @return array
     */
    public function getDependencies()
    {
        return [
            SeriesFixtures::class,
        ];
    }

    /**
     * Create next tree:
     *      - Bra1
     *          - Ep1
     *          - Ser1
     *              - Ep1
     *              - Ep2
     *          - Ser2
     *              - Ser1 (nested series)
     *                  - Ep1
     *                  - Ep2
     */
    public function load(ObjectManager $manager)
    {
        $this->manager = $manager;

        // Episodes not nested on series but on brands
        $this->addReference(
            'p3000000',
            $this->buildEpisode('p3000000', 'B1-E1', $this->getReference('b006q2x0'), ['download_mediaset_1', 'download_mediaset_2'], ['project_space' => 'progs-radio4and4extra'])
        );

        // Episodes contained on simple series
        $this->addReference(
            'p3000001',
            $this->buildEpisode('p3000001', 'B1-S1-E1', $this->getReference('b0000sr1'), [], ['project_space' => 'progs-radio4and4extra'])
        );

        $this->addReference(
            'p3000002',
            $this->buildEpisode('p3000002', 'B1-S1-E2', $this->getReference('b0000sr3'), ['download_mediaset_1', 'download_mediaset_2'])
        );

        // Episodes in nested Series
        $this->addReference(
            'p3000003',
            $this->buildEpisode('p3000003', 'B1-S2-S1-E1', $this->getReference('b000sr21'))
        );

        $this->addReference(
            'p3000004',
            $this->buildEpisode('p3000004', 'B1-S2-S1-E2', $this->getReference('b000sr21'))
        );

        // Episodes in non-indexed brand
        $this->addReference(
            'p3n0s301',
            $this->buildEpisode('p3n0s301', 'No SEO brand episode', $this->getReference('b006pfjx'))
        );

        $this->addReference(
            'b013pqnm',
            $this->buildEpisode('b013pqnm', 'B1-S2-S1-E3', $this->getReference('b000sr21'), [], ['recipes_enabled' => true])
        );

        $episode = new Episode('p01l1z04', 'The Day of the Doctor');
        $this->manager->persist($episode);

        $this->manager->flush();

        /** @var Episode $episode1 */
        $episode1 = $this->getReference('p3000000');
        /** @var Episode $episode2 */
        $episode2 = $this->getReference('p3000002');

        $versionTypePodcast = new VersionType('Podcast', 'Podcast');

        $this->manager->persist($versionTypePodcast);

        $this->addReference('version_type_podcast', $versionTypePodcast);

        $this->buildVersion('p4000001', $episode1);
        $this->buildVersion('p4000002', $episode2);

        $episode2->setDownloadableVersion($this->getReference('p4000002'));
        $episode1->setDownloadableVersion($this->getReference('p4000001'));
        $this->manager->persist($episode1);
        $this->manager->persist($episode2);

        $this->manager->flush();
    }

    private function buildEpisode(
        string $pid,
        string $title,
        ProgrammeContainer $series,
        array $downloadableMediaSets = [],
        array $programmeOptions = []
    ): Episode {
        $episode = new Episode($pid, $title);
        $episode->setParent($series);

        if (!empty($downloadableMediaSets)) {
            $episode->setDownloadableMediaSets($downloadableMediaSets);
        }
        $episode->setOptions($programmeOptions);

        $this->manager->persist($episode);

        return $episode;
    }

    private function buildVersion(
        string $pid,
        Episode $programmeItem
    ) {
        $version = new Version($pid, $programmeItem);
        $version->setDownloadable(true);
        $version->setVersionTypes(new ArrayCollection([
                $this->getReference('version_type_podcast'),
            ]
        ));

        $this->manager->persist($version);

        $this->addReference($pid, $version);
    }
}
