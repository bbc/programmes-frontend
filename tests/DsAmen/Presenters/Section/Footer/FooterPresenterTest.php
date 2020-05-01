<?php
declare(strict_types = 1);

namespace Tests\App\DsAmen\Presenters\Section\Footer;

use App\DsAmen\Presenters\Section\Footer\FooterPresenter;
use BBC\ProgrammesPagesService\Domain\Entity\Format;
use BBC\ProgrammesPagesService\Domain\Entity\Genre;
use BBC\ProgrammesPagesService\Domain\Entity\Image;
use BBC\ProgrammesPagesService\Domain\Entity\Network;
use BBC\ProgrammesPagesService\Domain\Entity\Programme;
use BBC\ProgrammesPagesService\Domain\Enumeration\NetworkMediumEnum;
use BBC\ProgrammesPagesService\Domain\ValueObject\Nid;
use Tests\App\BaseTemplateTestCase;
use PHPUnit_Framework_MockObject_MockObject;

class FooterPresenterTest extends BaseTemplateTestCase
{
    /** @var Programme|PHPUnit_Framework_MockObject_MockObject */
    private $mockProgramme;

    /** @var Programme|PHPUnit_Framework_MockObject_MockObject */
    private $mockRadioProgramme;

    public function setUp()
    {
        $mockProgramme = function ($nid, $networkName, $netUrlKey, $medium) {
            $navLinks = [['title' => 'Schedule', 'url' => 'path/to/schedule']];
            $image = $this->createMock(Image::class);
            $image->method('getUrl')->with(112, 'n')->willReturn('image/url.png');
            $net = $this->createMock(Network::class);
            $net->method('getUrlKey')->willReturn($netUrlKey);
            $net->method('getName')->willReturn($networkName);
            $net->method('getNid')->willReturn(new Nid($nid));
            $net->method('getImage')->willReturn($image);
            $net->method('getMedium')->willReturn($medium);
            $net->method('isRadio')->willReturn($medium === NetworkMediumEnum::RADIO);
            $net->method('getOption')->with('navigation_links')->willReturn($navLinks);
            $programme = $this->createMock(Programme::class);
            $programme->method('getNetwork')->willReturn($net);
            return $programme;
        };
        $this->mockProgramme = $mockProgramme('bbc_one', 'BBC One', 'bbcone', NetworkMediumEnum::TV);
        $this->mockRadioProgramme = $mockProgramme('bbc_radio_four', 'BBC Radio Four', 'radio4', NetworkMediumEnum::RADIO);
    }

    public function testRetrieveNetworkData()
    {
        $footerPresenter = new FooterPresenter($this->mockProgramme, []);

        $this->assertEquals('BBC One', $footerPresenter->getNetworkName());
        $this->assertEquals('bbcone', $footerPresenter->getNetworkUrlKey());
        $this->assertEquals('bbc_one', $footerPresenter->getNid());
        $this->assertEquals('image/url.png', $footerPresenter->getNetworkImageUrl());
        $this->assertEquals([['title' => 'Schedule', 'url' => 'path/to/schedule']], $footerPresenter->getNavigationLinks());
    }

    public function testRetrieveProgrammeGenres()
    {
        $parentGenre = new Genre([0], 'parent_id', 'Parent Title', 'parent_url_key', null);
        $genre = new Genre([0, 1], 'id', 'Title', 'url_key', $parentGenre);
        $someOtherGenre = new Genre([2], 'other_id', 'Other Title', 'other_url_key', null);

        $this->mockProgramme->method('getGenres')->willReturn([$genre, $someOtherGenre]);

        $footerPresenter = new FooterPresenter($this->mockProgramme, []);
        $this->assertEquals([$someOtherGenre, $genre], $footerPresenter->getGenres());
    }

    public function testRetrieveProgrammeFormats()
    {
        $format = new Format([3], 'format_id', 'format_title', 'format_url_key');
        $otherFormat = new Format([4], 'other_format_id', 'other_format_title', 'other_format_url_key');

        $this->mockProgramme->method('getFormats')->willReturn([$format, $otherFormat]);

        $footerPresenter = new FooterPresenter($this->mockProgramme, []);
        $this->assertEquals([$format, $otherFormat], $footerPresenter->getFormats());
    }

    public function testNHPLinkLogic()
    {
        $c = $this->presenterCrawler(new FooterPresenter($this->mockProgramme));
        $this->assertEquals(2, $c->filter('a[href="/bbcone"]')->count(), 'TV should have 2 NHP links');
        $this->assertEquals(1, $c->filter('a img')->count(), 'footer should have logo link');

        $c = $this->presenterCrawler(new FooterPresenter($this->mockRadioProgramme));
        $this->assertEquals(0, $c->filter('a[href="/radio4"]')->count(), 'Radio should have 0 NHP links');
        $this->assertEquals(1, $c->filter('a:not([href="/radio4"])')->count(), 'footer should have one other link');
        $this->assertEquals(1, $c->filter('img')->count(), 'footer should have logo');
    }
}
