<?php

namespace Tests\App\PagesServiceConfig;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Tests\App\BaseCliftonTestCase;
use BBC\ProgrammesPagesService\Data\ProgrammesDb\EntityRepository\CoreEntityRepository;

/**
 * @coversNone
 */
class EmbargoableEntitiesTest extends BaseCliftonTestCase
{
    public function testEmbargoedCoreEntitiesAreFilteredOut()
    {
        $this->loadFixtures(['EmbargoedProgrammeFixture']);
        /** @var Registry $registry */
        $registry = $this->getContainer()->get('doctrine');
        /** @var CoreEntityRepository $repo */
        $repo = $registry->getRepository('ProgrammesPagesService:CoreEntity');

        // We do not expect to see the pid 99999999 or 00000000 which belong to the embargoed programmes
        $expectedPids = ['b017j7vs', 'b01777fr', 'b017j5jw'];

        $entities = $repo->findAllWithParents(10, 0);
        $this->assertEquals($expectedPids, array_column($entities, 'pid'));

        $this->assertEquals(3, $repo->countAll());

        // the parent was embargoed, so should not be accessible
        $this->assertTrue(isset($entities[1]['parent']));
        $this->assertFalse(isset($entities[2]['parent']));
    }

    public function testVersionIsEmbargoedIfParentIsEmbargoed()
    {
        $this->loadFixtures(['EmbargoedProgrammeFixture']);
        /** @var Registry $registry */
        $registry = $this->getContainer()->get('doctrine');
        /** @var CoreEntityRepository $repo */
        $repo = $registry->getRepository('ProgrammesPagesService:Version');

        $entity = $repo->findByPid('v0000001');
        $this->assertNull($entity);
    }
}
