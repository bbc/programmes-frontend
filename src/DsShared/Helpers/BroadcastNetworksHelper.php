<?php
declare(strict_types = 1);

namespace App\DsShared\Helpers;

use App\ValueObject\BroadcastNetworkBreakdown;
use BBC\ProgrammesPagesService\Domain\Entity\CollapsedBroadcast;
use BBC\ProgrammesPagesService\Domain\Entity\Service;
use BBC\ProgrammesPagesService\Domain\Exception\DataNotFetchedException;
use Symfony\Component\Translation\TranslatorInterface;

class BroadcastNetworksHelper
{
    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param CollapsedBroadcast $collapsedBroadcast
     * @return BroadcastNetworkBreakdown[]
     *                  An ordered array of BroadcastNetworkBreadowns. This object has the processed named for
     *                  the network (prefixed with the & or , (or not)), the processed names of the services of
     *                  that network joined by & or and qualified with 'only' or 'except' (or not) and the network
     *                  object itself. If there are more than 5 networks or services, these get replaced by a string saying
     *                  'and X more...' where X is the number of networks or services minus 5
     * @throws DataNotFetchedException
     */
    public function getNetworksAndServicesDetails(CollapsedBroadcast $collapsedBroadcast): array
    {
        $networkBreakdowns = $this->getCollapsedBroadcastNetworkBreakdown($collapsedBroadcast);

        $networkNames = $this->buildNetworkNames($networkBreakdowns);
        $servicesNames = $this->buildServicesNames($networkBreakdowns);

        $breakdown = [];
        for ($i = 0; $i < count($networkNames) && $i < 5; $i++) {
            $breakdown[] = new BroadcastNetworkBreakdown(
                $networkNames[$i],
                $servicesNames[$i],
                $networkBreakdowns[$i]['network']
            );
        }

        // If there are 6 elements in networkNames, we're showing the 'and X more...' message for the networks. So,
        // we only use the first 5 services names and append an empty string at the end of the services names array
        if (count($networkNames) === 6) {
            $breakdown[] = new BroadcastNetworkBreakdown(
                $networkNames[5],
                '',
                null
            );
        }

        return $breakdown;
    }

    /**
     * @param array[] $networkBreakdowns
     * @return string[]
     */
    private function buildNetworkNames(array $networkBreakdowns): array
    {
        $networkNames = [];

        foreach ($networkBreakdowns as $networkBreakdown) {
            $networkNames[] = $networkBreakdown['network']->getName();
        }

        return $this->truncateList($networkNames);
    }

    /**
     * @param array[] $networkBreakdowns
     * @return string[]
     */
    private function buildServicesNames(array $networkBreakdowns): array
    {
        $serviceNames = [];

        foreach ($networkBreakdowns as $networkBreakdown) {
            $broadcastOnServices = $networkBreakdown['on_services'];
            $notBroadcastOnServices = $networkBreakdown['not_on_services'];

            if (empty($notBroadcastOnServices) || empty($broadcastOnServices)) {
                // If the broadcast is present in all services, we don't need to qualify it
                // Also, if the broadcast isn't present in any service, we removed it because
                // it has the same name as the network. So, we just use the network name, without qualifiers
                $serviceNames[] = '';
            } elseif (count($broadcastOnServices) === 1) {
                // If the broadcast happens only in one service just use the name without the 'only' qualifier
                $serviceNames[] = $broadcastOnServices[0]->getShortName();
            } else {
                // Always use the smallest number of services possible
                if (count($notBroadcastOnServices) < count($broadcastOnServices)) {
                    $services = $notBroadcastOnServices;
                    $translation = 'broadcast_except';
                } else {
                    $services = $broadcastOnServices;
                    $translation = 'broadcast_only_on';
                }

                $names = [];
                foreach ($services as $service) {
                    $names[] = $service->getShortName();
                }

                // Return all services with the relevant qualifier ('only' or 'except')
                $serviceNames[] = $this->translator->trans(
                    $translation,
                    ['%1' => implode('', $this->prefixNames($names))]
                );
            }
        }

        return $serviceNames;
    }

    /**
     * Breaks down broadcast network information in services where the broadcast happened and services where
     * the broadcast didn't happen. When building these two lists, services with the same short name as the
     * network get ignored. This happens on World News broadcasts, which has a service (p00v5fbq) with no
     * broadcast. This leads to things like 'BBC World News except BBC World News'
     *
     * @param CollapsedBroadcast $collapsedBroadcast
     * @return array an associative array containing 3 indexes:
     *               'network' => the network
     *               'on_services' => services from the network where the broadcast happened
     *               'not_on_services' => services from the network where the broadcast didn't happened
     * @throws DataNotFetchedException
     */
    private function getCollapsedBroadcastNetworkBreakdown(CollapsedBroadcast $collapsedBroadcast): array
    {
        $breakdowns = [];

        // Build list of network and the services from that network where the broadcast happened
        foreach ($collapsedBroadcast->getServices() as $service) {
            $network = $service->getNetwork();
            if ($network) {
                $nid = (string) $network->getNid();
                if (!array_key_exists($nid, $breakdowns)) {
                    $breakdowns[$nid] = [
                        'network' => $network,
                        'on_services' => [],
                    ];
                }

                // If the service has the same name as the network, ignore it
                if (strcasecmp($service->getShortName(), $network->getName())) {
                    $breakdowns[$nid]['on_services'][(string) $service->getSid()] = $service;
                }
            }
        }

        // Build a list of the services from a network where the broadcast didn't happen
        foreach ($breakdowns as $nid => $breakdown) {
            $networkServices = [];
            $allServices = $breakdown['network']->getServices();
            foreach ($allServices as $service) {
                // If the service has the same name as the network, ignore it
                if (strcasecmp($service->getShortName(), $breakdown['network']->getName())) {
                    $networkServices[] = $service;
                }
            }

            $breakdowns[$nid]['not_on_services'] = array_udiff(
                $networkServices,
                $breakdown['on_services'],
                function (Service $a, Service $b) {
                    return strcmp((string) $a->getSid(), (string) $b->getSid());
                }
            );

            $breakdowns[$nid]['on_services'] = array_values($breakdowns[$nid]['on_services']);
        }

        return array_values($breakdowns);
    }

    private function prefixNames(array $names): array
    {
        $names = $this->truncateList($names);

        $namesCount = count($names);

        // Attach a comma to the beginning of each name, except for the first and last one
        for ($i = 1; $i < $namesCount - 1; $i++) {
            $names[$i] = ', ' . $names[$i];
        }

        // Attach an ampersand to the beginning of the last name
        if ($namesCount > 1) {
            $names[$namesCount - 1] = ' & ' . $names[$namesCount - 1];
        }

        return $names;
    }

    private function truncateList(array $names): array
    {
        $namesCount = count($names);

        // If there are more than 5 names, use only the first five names and attach an 'and X more' qualifier at the end
        if ($namesCount > 5) {
            $names = \array_slice($names, 0, 5);
            $names[] = $this->translator->trans('x_more %count%', ['%count%' => $namesCount - 5]);
        }

        return $names;
    }
}
