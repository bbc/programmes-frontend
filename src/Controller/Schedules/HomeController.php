<?php
declare(strict_types = 1);
namespace App\Controller\Schedules;

use App\Controller\BaseController;
use App\Controller\Helpers\Breadcrumbs;
use BBC\ProgrammesPagesService\Domain\ApplicationTime;
use BBC\ProgrammesPagesService\Domain\Entity\Service;
use BBC\ProgrammesPagesService\Service\ServicesService;
use Cake\Chronos\Chronos;

class HomeController extends BaseController
{
    // Hide unwanted services and non-integrated language stations from listings
    private const BLACKLISTED_SERVICES = [
        'p00v5fbq' => true, // BBC WORLD NEWS
        'p00qvyk4' => true, // BBC Radio Events Stream 1
        'p00qvyjs' => true, // BBC Radio Events Stream 2
        'p03yncmk' => true, // BBC Persian TV
        'p02yxxwj' => true, // BBC World Service ANR
        'p02yxxfc' => true, // BBC World Service Core
        'p02y9sgt' => true, // BBC World Service News Internet
        'p02yxy62' => true, // BBC World Service US Public Radio
        'p00hwfhp' => true, // BBC WORLD NEWS Americas
        'p00fzlgd' => true, // Radio 10
        'p090dlbj' => true, // Radio Bradford
        'p092zvsg' => true, // Radio Sunderland
        'p092zvw8' => true, // Radio Wolverhampton

        // World Service
        'p05b8bdk' => true, // Amharic
        'p02yxkk8' => true, // Arabic Radio
        'p05hggf6' => true, // Arabic TV
        'p02yvd0g' => true, // Bangla
        'p02z1j40' => true, // Burmese
        'p02ycxdc' => true, // Cantonese
        'p02yxldk' => true, // Dari
        'p02yvctm' => true, // Hindi
        'p02yy2fq' => true, // Indonesia
        'p05b88kr' => true, // Korean Radio
        'p02ys1q2' => true, // Kyrgyz
        'p02ys3q5' => true, // Nepali
        'p05b89mh' => true, // Oromo
        'p02yvd1m' => true, // Pashto
        'p02yxlst' => true, // Persian
        'p02yxfsh' => true, // Russian
        'p02yq39l' => true, // Sinhala
        'p02ys2pm' => true, // Tamil
        'p05b8b67' => true, // Tigrinya
        'p02yxk0h' => true, // Urdu
        'p02yvcxr' => true, // Uzbek
    ];

    public function __invoke(ServicesService $servicesService, Breadcrumbs $breadcrumbs)
    {
        $this->setBrandingId('br-08368');
        $this->setAtiContentLabels('index-home', 'schedules-index');
        $this->setAtiContentId('', 'pips');

        $groups = [
            'TV' => [],
            'National Radio' => [],
            'Regional Radio' => [],
            'Local Radio' => [],
            'Other' => [],
            'Historic' => [],
        ];

        $services = $servicesService->getAllInNetworks();

        $now = ApplicationTime::getLocalTime();
        $earliestBroadcastDate = new Chronos('1920-01-01T00:00:00Z');
        $latestBroadcastDate = $now->endOfDecade();

        $diff = $earliestBroadcastDate->diff($latestBroadcastDate);
        $pointsPerDay = 100 / $diff->days;

        $earliestDecade = $earliestBroadcastDate->startOfDecade()->year;
        $latestDecade = $latestBroadcastDate->startOfDecade()->year;

        $decades = range($earliestDecade, $latestDecade, 10);

        foreach ($services as $service) {
            /** @var Service $service */
            // We only care about services with a startDate
            if (!$service->getStartDate()) {
                continue;
            }

            if (isset(self::BLACKLISTED_SERVICES[(string) $service->getPid()])) {
                continue;
            }

            $network = $service->getNetwork();

            if ($network->getOption('pid_override_url') && $network->getOption('pid_override_code')) {
                continue; // These pages redirect, no point linking to them
            }

            $groupKey = $this->groupKeyForService($service);

            $nid = (string) $network->getNid();
            if (!isset($groups[$groupKey][$nid])) {
                $groups[$groupKey][$nid] = [
                    'network' => $network,
                    'services' => [],
                ];
            }

            $groups[$groupKey][$nid]['services'][] = $this->createServiceItem(
                $service,
                $earliestBroadcastDate,
                $now,
                $pointsPerDay
            );
        }

        // Remove any groups that have no networks within them
        foreach ($groups as $key => $networks) {
            if (!$networks) {
                unset($groups[$key]);
            }
        }

        $this->breadcrumbs = $breadcrumbs
            ->forRoute('Schedules', 'schedules_home')
            ->toArray();

        return $this->renderWithChrome('schedules/home.html.twig', [
            'groups' => $groups,
            'decades' => $decades,
        ]);
    }

    private function createServiceItem(
        Service $service,
        Chronos $earliestBroadcastDate,
        Chronos $now,
        $pointsPerDay
    ): array {
        // We've already filtered out all services that don't have a startDate
        $diffFromStart = $earliestBroadcastDate->diff($service->getStartDate());
        $endDate = $service->getEndDate() ?? $now;
        $diffForLength = $service->getStartDate()->diff($endDate);

        return [
            'service' => $service,
            'isOngoing' => !$service->getEndDate(),
            'offset' => $diffFromStart->days * $pointsPerDay,
            'width' => $diffForLength->days * $pointsPerDay,
        ];
    }

    private function groupKeyForService(Service $service): string
    {
        $endDate = $service->getEndDate();
        if ($endDate && $endDate->isPast()) {
            return 'Historic';
        }

        $type = $service->getNetwork()->getType();
        if (in_array($type, ['TV', 'National Radio', 'Regional Radio', 'Local Radio'])) {
            return $type;
        }

        return 'Other';
    }
}
