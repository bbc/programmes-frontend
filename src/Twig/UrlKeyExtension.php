<?php

namespace App\Twig;

use BBC\ProgrammesPagesService\Domain\Entity\Network;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UrlKeyExtension extends AbstractExtension
{
    // Remember to change in branding as well!
    private const URL_KEYS = [
        'bbc_1xtra' => true,
        'bbc_6music' => true,
        'bbc_7' => true,
        'bbc_afrique_radio' => true,
        'bbc_alba' => true,
        'bbc_arabic_radio' => true,
        'bbc_asian_network' => true,
        'bbc_bangla_radio' => true,
        'bbc_burmese_radio' => true,
        'bbc_cantonese_radio' => true,
        'bbc_dari_radio' => true,
        'bbc_four' => true,
        'bbc_gahuza_radio' => true,
        'bbc_hausa_radio' => true,
        'bbc_hindi_radio' => true,
        'bbc_indonesian_radio' => true,
        'bbc_kyrgyz_radio' => true,
        'bbc_london' => true,
        'bbc_music' => true,
        'bbc_music_jazz' => '/programmes/p033dmdy',
        'bbc_nepali_radio' => true,
        'bbc_news' => '/tv/bbcnews',
        'bbc_news24' => '/tv/bbcnews',
        'bbc_one' => true,
        'bbc_parliament' => '/tv/bbcparliament',
        'bbc_pashto_radio' => true,
        'bbc_persian_radio' => true,
        'bbc_radio_berkshire' => true,
        'bbc_radio_bristol' => true,
        'bbc_radio_cambridge' => true,
        'bbc_radio_cornwall' => true,
        'bbc_radio_coventry_warwickshire' => true,
        'bbc_radio_cumbria' => true,
        'bbc_radio_cymru' => true,
        'bbc_radio_cymru_mwy' => '/programmes/b07v33kg',
        'bbc_radio_derby' => true,
        'bbc_radio_devon' => true,
        'bbc_radio_essex' => true,
        'bbc_radio_five_live' => true,
        'bbc_radio_five_live_olympics_extra' => '/programmes/b00cxqmw',
        'bbc_radio_five_live_sports_extra' => true,
        'bbc_radio_four' => true,
        'bbc_radio_four_extra' => true,
        'bbc_radio_foyle' => true,
        'bbc_radio_gloucestershire' => true,
        'bbc_radio_guernsey' => true,
        'bbc_radio_hereford_worcester' => true,
        'bbc_radio_humberside' => true,
        'bbc_radio_jersey' => true,
        'bbc_radio_kent' => true,
        'bbc_radio_lancashire' => true,
        'bbc_radio_leeds' => true,
        'bbc_radio_leicester' => true,
        'bbc_radio_lincolnshire' => true,
        'bbc_radio_manchester' => true,
        'bbc_radio_merseyside' => true,
        'bbc_radio_nan_gaidheal' => true,
        'bbc_radio_newcastle' => true,
        'bbc_radio_norfolk' => true,
        'bbc_radio_northampton' => true,
        'bbc_radio_nottingham' => true,
        'bbc_radio_one' => true,
        'bbc_radio_oxford' => true,
        'bbc_radio_scotland' => true,
        'bbc_radio_scotland_music_extra' => '/programmes/p04d9wfq',
        'bbc_radio_sheffield' => true,
        'bbc_radio_shropshire' => true,
        'bbc_radio_solent' => true,
        'bbc_radio_somerset_sound' => true,
        'bbc_radio_stoke' => true,
        'bbc_radio_suffolk' => true,
        'bbc_radio_surrey' => true,
        'bbc_radio_sussex' => true,
        'bbc_radio_three' => true,
        'bbc_radio_two' => true,
        'bbc_radio_two_country' => '/programmes/p02jxzfq',
        'bbc_radio_two_eurovision' => '/programmes/p01xjf0z',
        'bbc_radio_two_fifties' => '/programmes/p03lsql7',
        'bbc_radio_two_beatles' => '/programmes/p07ktp01',
        'bbc_radio_ulster' => true,
        'bbc_radio_wales' => true,
        'bbc_radio_wiltshire' => true,
        'bbc_radio_york' => true,
        'bbc_russian_radio' => true,
        'bbc_school_radio' => '/programmes/p007g5y4',
        'bbc_sinhala_radio' => true,
        'bbc_somali_radio' => true,
        'bbc_southern_counties_radio' => true,
        'bbc_sport' => true,
        'bbc_swahili_radio' => true,
        'bbc_tamil_radio' => true,
        'bbc_tees' => true,
        'bbc_three' => true,
        'bbc_three_counties_radio' => true,
        'bbc_two' => true,
        'bbc_urdu_radio' => true,
        'bbc_uzbek_radio' => true,
        'bbc_wm' => true,
        'bbc_world_news' => true,
        'bbc_world_service' => true,
        'cbbc' => true,
        'cbeebies' => true,
        'cbeebies_radio' => '/cbeebies/radio',
        's4cpbs' => '/tv/s4c',
        'bbc_radio_one_vintage' => '/programmes/p059cwkn',
        'bbc_radio_cymru_2' => '/radiocymru2',
        'bbc_sounds_podcasts' => true,
        'bbc_sounds_mixes' => true,
        'bbc_brasil' => true,
        'bbc_scotland' => true,
        'bradford_1xtra' => '/programmes/p07nl4my',
    ];

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('network_link', [$this, 'networkLink']),
        ];
    }

    public function networkLink(?Network $network): string
    {
        if ($network === null) {
            return '';
        }

        $nid = (string) $network->getNid();
        $urlKey = $network->getUrlKey();

        if ($urlKey === null || !array_key_exists($nid, self::URL_KEYS)) {
            return '';
        }

        if (self::URL_KEYS[$nid] === true) {
            return '/' . urlencode($urlKey);
        }

        return (string) self::URL_KEYS[$nid];
    }
}
