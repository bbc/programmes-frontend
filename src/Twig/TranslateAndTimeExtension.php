<?php
declare(strict_types = 1);
namespace App\Twig;

use App\DsShared\Factory\HelperFactory;
use App\Translate\TranslatableTrait;
use BBC\ProgrammesPagesService\Domain\ApplicationTime;
use BBC\ProgrammesPagesService\Domain\ValueObject\PartialDate;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Symfony\Component\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The local time functions make use of Translate fairly heavily.
 * Hence grouping local time and translation together.
 */
class TranslateAndTimeExtension extends AbstractExtension
{
    use TranslatableTrait;

    private $helperFactory;

    public function __construct(TranslatorInterface $translator, HelperFactory $helperFactory)
    {
        $this->helperFactory = $helperFactory;
        $this->translator = $translator;
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('ucwords', 'ucwords'),
            new TwigFilter('local_date_intl', [$this, 'localDateIntlWrapper']),
            new TwigFilter('local_date', [$this, 'localDate']),
            new TwigFilter('time_zone_note', [$this, 'timeZoneNote'], [
                'is_safe' => ['html'],
            ]),
            new TwigFilter('local_partial_date', [$this, 'localPartialDate']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('tr', [$this, 'trWrapper']),
            new TwigFunction('localised_days_and_months', [$this, 'localisedDaysAndMonths']),
        ];
    }

    public function trWrapper(
        string $key,
        $substitutions = [],
        $numPlurals = null,
        ?string $domain = null
    ): string {
        return $this->tr($key, $substitutions, $numPlurals, $domain);
    }

    public function localDateIntlWrapper(DateTimeInterface $dateTime, string $format): string
    {
        return $this->localDateIntl($dateTime, $format);
    }

    public function timeZoneNote(DateTimeInterface $dateTime): string
    {
        $tz = ApplicationTime::getLocalTimeZone();
        if ($tz->getName() === 'Europe/London') {
            // Displayed times are assumed to be UK unless otherwise stated
            return '';
        }
        $text = $this->tr('gmt');
        if ($tz->getName() !== 'UTC') {
            $dateTime = $this->toTimeZone($dateTime, new DateTimeZone('UTC'));
            $offset = $tz->getOffset($dateTime);
            $sign = ($offset > 0) ? '+' : '-';
            // Offset is set to positive to make the calculation easier (floor vs ceil etc)
            $offset = abs($offset);
            $hours = floor($offset / 3600);
            $minutes = floor(($offset - ($hours * 3600)) / 60);
            $text .= sprintf('%s%02d:%02d', $sign, $hours, $minutes);
        }

        return '<span class="timezone--note">' . $text . '</span>';
    }

    /**
     * This is much simpler than localDateIntl. It doesn't use INTL. It just formats
     * for the local timezone.
     */
    public function localDate(DateTimeInterface $dateTime, string $format)
    {
        $dateTime = $this->toTimeZone($dateTime, ApplicationTime::getLocalTimeZone());
        return $dateTime->format($format);
    }

    public function localisedDaysAndMonths()
    {
        return $this->helperFactory->getLocalisedDaysAndMonthsHelper()->localisedDaysAndMonths();
    }

    public function localPartialDate(
        PartialDate $partialDate,
        string $fullFormat,
        string $yearMonthFormat,
        string $yearFormat
    ): string {
        $dateTime = $partialDate->asDateTime();

        $hasMonth = $partialDate->hasMonth();
        if ($partialDate->hasDay() && $hasMonth) {
            $attribute = $this->localDate($dateTime, 'Y-m-d');
            $text = $this->localDateIntlWrapper($dateTime, $fullFormat);
        } else if ($hasMonth) {
            $attribute = $this->localDate($dateTime, 'Y-m');
            $text = $this->localDateIntlWrapper($dateTime, $yearMonthFormat);
        } else {
            $attribute = $this->localDate($dateTime, 'Y');
            $text = $this->localDateIntlWrapper($dateTime, $yearFormat);
        }

        $attribute = htmlspecialchars($attribute);
        $text = htmlspecialchars($text);

        return '<time datetime="' . $attribute . '">' . $text . '</time>';
    }

    private function toTimeZone(DateTimeInterface $dateTime, DateTimeZone $timeZone): DateTimeInterface
    {
        if ($dateTime->getTimezone()->getName() !== $timeZone->getName()) {
            if (!$dateTime instanceof DateTimeImmutable) {
                $dateTime = clone $dateTime;
            }
            $dateTime = $dateTime->setTimezone($timeZone);
        }
        return $dateTime;
    }
}
