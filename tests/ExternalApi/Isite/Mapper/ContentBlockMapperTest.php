<?php
declare(strict_types = 1);

namespace Tests\App\ExternalApi\Isite\Mapper;

use App\Controller\Helpers\IsiteKeyHelper;
use App\ExternalApi\Client\Factory\HttpApiClientFactory;
use App\ExternalApi\IdtQuiz\Service\IdtQuizService;
use App\ExternalApi\Riddle\Service\RiddleService;
use App\ExternalApi\Isite\Domain\ContentBlock\Faq;
use App\ExternalApi\Isite\Domain\ContentBlock\Promotions;
use App\ExternalApi\Isite\Domain\ContentBlock\Prose;
use App\ExternalApi\Isite\Domain\ContentBlock\Quiz;
use App\ExternalApi\Isite\Domain\ContentBlock\Table;
use App\ExternalApi\Isite\Domain\ContentBlock\Telescope;
use App\ExternalApi\Isite\Mapper\ContentBlockMapper;
use App\ExternalApi\Isite\Mapper\MapperFactory;
use BBC\ProgrammesPagesService\Service\CoreEntitiesService;
use BBC\ProgrammesPagesService\Service\ProgrammesService;
use BBC\ProgrammesPagesService\Service\VersionsService;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Tests\App\ReflectionHelper;

class ContentBlockMapperTest extends TestCase
{
    /** @var ContentBlockMapper */
    private $mapper;

    private $idtQuizService;

    public function setUp()
    {
        $this->idtQuizService = $this->getMockBuilder(IdtQuizService::class)
            ->setMethods(['getQuizContentPromise'])
            ->disableOriginalConstructor()
            ->getMock();

        $keyHelper = new IsiteKeyHelper();
        $ces = $this->createMock(CoreEntitiesService::class);
        $ps = $this->createMock(ProgrammesService::class);
        $vs = $this->createMock(VersionsService::class);
        $rs = $this->createMock(RiddleService::class);
        $logger = $this->createMock(LoggerInterface::class);
        $this->mapper = new ContentBlockMapper(
            new MapperFactory(
                $keyHelper,
                $ces,
                $this->idtQuizService,
                $ps,
                $vs,
                $rs,
                $logger
            ),
            $keyHelper,
            $ces,
            $this->idtQuizService,
            $ps,
            $vs,
            $rs,
            $logger
        );
    }

    /**
     * @dataProvider getTestMappingQuizObjectData
     */
    public function testMappingQuizObject($xml, $quizId, $htmlContent)
    {

        /** @var Quiz $block */
        $block = $this->mapper->getDomainModel($xml);
        $this->assertInstanceOf(Quiz::class, $block, 'Check returns Quiz class');
        $this->assertEquals($htmlContent, $block->getHtmlContent(), 'Check has right HTML content');
        $this->assertEquals($quizId, $block->getQuizId(), 'Checks has right Quiz ID');
    }

    public function getTestMappingQuizObjectData()
    {
        return [
            'data_in_quiz.xml' => [
                'xml-element' => new SimpleXMLElement(file_get_contents(__DIR__ . '/quiz.xml')),
                'quiz-id' => '1234',
                'html-content' => '<a class="idt-quiz-link" href="/indepthtoolkit/quizzes/1234/app" target="_blank" rel="noopener">Start quiz</a>',
            ],
        ];
    }

    public function testMappingFaqObject()
    {
        $xml = new SimpleXMLElement(file_get_contents(__DIR__ . '/faq.xml'));

        $block = $this->mapper->getDomainModel($xml);

        $this->assertInstanceOf(Faq::class, $block);
        $this->assertEquals('This is a FAQ content box', $block->getTitle());
        $this->assertEquals('This is an optional intro', $block->getIntro());
        $this->assertEquals(
            [
                ['question' => 'What is the population of London?', 'answer' => '<p>8,825,000</p>'],
                ['question' => 'What is the population of Paris?', 'answer' => '<p>2,244,000</p>'],
                ['question' => 'What is the population of Buenos Aires?', 'answer' => '<p>2,891,000</p>'],
            ],
            $block->getQuestions()
        );
    }

    public function testMappingTableObject()
    {
        $xml = new SimpleXMLElement(file_get_contents(__DIR__ . '/table.xml'));

        $block = $this->mapper->getDomainModel($xml);

        $this->assertInstanceOf(Table::class, $block);
        $this->assertEquals('This table has 2 columns and 2 rows, but I\'ve only populated the 1st column of each row', $block->getTitle());
        $this->assertEquals(['Country', 'Capital'], $block->getHeadings());
        $this->assertEquals([['Italy', ''], ['Rome', '']], $block->getRows());
    }

    public function testMappingPromotionObject()
    {
        $xml = new SimpleXMLElement(file_get_contents(__DIR__ . '/promotions.xml'));

        $block = $this->mapper->getDomainModel($xml);

        $this->assertInstanceOf(Promotions::class, $block);
        $this->assertEquals('test title', $block->getTitle());
        $this->assertEquals('list', $block->getLayout());
        $expectedPromotions[0] = [
            'promotionTitle' => 'This is a promo displayed as list',
            'url' => 'https://www.bbc.co.uk',
            'promotedItemId' => 'p01lcqgs',
            'shortSynopsis' => 'This is a short synopsis',
        ];
        $this->assertEquals($expectedPromotions, $block->getPromotions());
    }

    public function testMappingProseObject()
    {
        $xml = new SimpleXMLElement(file_get_contents(__DIR__ . '/prose.xml'));

        $block = $this->mapper->getDomainModel($xml);

        $this->assertInstanceOf(Prose::class, $block);
        $this->assertEquals('This is a prose content box', $block->getTitle());
        $this->assertEquals('<p>Lorem ipsum <strong>dolor sit amet</strong></p>', $block->getProse());
        $this->assertEquals('p019x81g', $block->getImage());
        $this->assertEquals('This is an optional image caption', $block->getImageCaption());
        $this->assertEquals('Only two things are infinite', $block->getQuote());
        $this->assertEquals('Albert Einstein', $block->getQuoteAttribution());
        $this->assertEquals('right', $block->getMediaPosition());
    }

    public function testMappingTelescopeObject()
    {
        $xml = new SimpleXMLElement(file_get_contents(__DIR__ . '/telescope.xml'));

        $block = $this->mapper->getDomainModel($xml);

        $this->assertInstanceOf(Telescope::class, $block);
        $this->assertEquals('This is a Telescope vote squeezed in here:', $block->getTitle());
        $this->assertEquals('abcd012', $block->getVoteId());
        $this->assertTrue($block->isUkOnly());
    }
}
