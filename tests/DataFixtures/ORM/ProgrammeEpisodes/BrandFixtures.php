<?php
declare(strict_types=1);

namespace Tests\App\DataFixtures\ORM\ProgrammeEpisodes;

use BBC\ProgrammesPagesService\Data\ProgrammesDb\Entity\Brand;
use BBC\ProgrammesPagesService\Data\ProgrammesDb\Entity\Podcast;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Tests\App\DataFixtures\ORM\Categories\CategoriesFixtures;
use Tests\App\DataFixtures\ORM\MasterBrandsFixture;
use Tests\BBC\ProgrammesPagesService\DataFixtures\ORM\CategoriesFixture;

class BrandFixtures extends AbstractFixture implements DependentFixtureInterface
{
    /** @var ObjectManager $manager */
    private $manager;

    public function getDependencies()
    {
        return [
            MasterBrandsFixture::class,
            CategoriesFixtures::class,
        ];
    }

    public function load(ObjectManager $manager)
    {
        $this->manager = $manager;

        $brand = $this->buildBrand('b006q2x0', 'B1', ['C00045', 'C00080'], 2);

        $this->addReference('b006q2x0', $brand);

        $this->buildPodcast($brand);

        $b2 = $this->buildBrand('b006pnjk', 'B2', ['C00080']);
        $this->addReference('b006pnjk', $b2);

        $this->buildBrand('b004jt40', 'B3', ['C00070'], 1);
        $this->buildBrand('b004jt41', 'B4', ['C00070'], 0);

        $noSeoBrand = $this->buildBrand('b006pfjx', 'B3', []);
        $this->addReference('b006pfjx', $noSeoBrand);

        $this->manager->flush();
    }

    public function buildBrand($pid, $title, $categoryIds = [], $countEpisodes = 0, bool $isPodcastable = true, string $description = 'this is a short description')
    {
        $brand = new Brand($pid, $title);
        $brand->setAvailableEpisodesCount($countEpisodes);
        if ($countEpisodes > 0) {
            $brand->setStreamable(true);
        }
        $brand->setIsPodcastable($isPodcastable);
        $brand->setShortSynopsis($description);
        $brand->setMasterBrand($this->getReference('masterbrand_p1000001'));
        if ($categoryIds) {
            $categories = new ArrayCollection();
            foreach ($categoryIds as $categoryId) {
                $categories[] = $this->getReference($categoryId);
            }
            $brand->setCategories($categories);
        }

        $this->manager->persist($brand);

        return $brand;
    }

    private function buildPodcast($brand)
    {
        $podcast = new Podcast($brand, 'weekly', -1, true, false);

        $this->manager->persist($podcast);
    }
}
