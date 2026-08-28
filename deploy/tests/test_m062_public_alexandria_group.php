<?php
/** Public-search regression for Alexandria city grouping with exact terminal booking. */
$root = dirname(__DIR__, 2);
require_once $root.'/custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php';

function m062PublicAssert($condition, $message) { if (!$condition) throw new RuntimeException($message); }

class M062PublicAlexandriaFakePrices
{
    public $searchPods = array();
    public $trendPods = array();
    public $trendErrorPod = '';
    private $rates = array();

    public function __construct()
    {
        foreach (array(
            101 => array('pod' => 'ALEXANDRIA OLD PORT', 'price' => 2400),
            102 => array('pod' => 'ALEXANDRIA TMT', 'price' => 2350),
            103 => array('pod' => 'ALEXANDRIA', 'price' => 2450),
        ) as $id => $fixture) {
            $this->rates[$id] = array(
                'rate_id' => $id,
                'fingerprint' => hash('sha256', 'm062-public-'.$id),
                'pol' => 'GUANGZHOU',
                'pod' => $fixture['pod'],
                'country_code' => 'EG',
                'shipping_line' => 'Public Carrier '.$id,
                'container_type' => '40HQ',
                'service_type' => 'direct',
                'currency' => 'USD',
                'final_price' => $fixture['price'],
                'valid_from' => '2026-08-01',
                'valid_to' => '2026-09-01',
                'included' => array('Ocean freight'),
                'excluded' => array('Destination charges'),
                'notes' => '',
                'warnings' => array(),
                'components' => array(),
            );
        }
    }

    public function search($filters, $includeCost, $limit)
    {
        $this->searchPods[] = $filters['pod'];
        foreach ($this->rates as $rate) {
            if ($rate['pod'] === $filters['pod']) return array('results' => array($rate), 'error' => '');
        }
        return array('results' => array(), 'error' => '');
    }

    public function getPublicRouteOptions($countryCode)
    {
        return array('countries' => array(), 'routes' => array(
            array('pol' => 'GUANGZHOU', 'pod' => 'ALEXANDRIA'),
            array('pol' => 'GUANGZHOU', 'pod' => 'ALEXANDRIA OLD PORT'),
            array('pol' => 'GUANGZHOU', 'pod' => 'ALEXANDRIA TMT'),
            array('pol' => 'GUANGZHOU', 'pod' => 'SOKHNA'),
        ));
    }

    public function getPublicPriceTrend($filters, $months)
    {
        $this->trendPods[] = $filters['pod'];
        if ($filters['pod'] === $this->trendErrorPod) return array('points' => array(), 'error' => 'fixture failure');
        $prices = array(
            'ALEXANDRIA OLD PORT' => 2400,
            'ALEXANDRIA TMT' => 2350,
            'ALEXANDRIA' => 2450,
        );
        if (!isset($prices[$filters['pod']])) return array('points' => array(), 'error' => '');
        return array('points' => array(array(
            'date' => '2026-08-01',
            'price' => $prices[$filters['pod']],
            'currency' => 'USD',
        )), 'error' => '');
    }

    public function getCustomerRateById($id, $filters)
    {
        return isset($this->rates[$id]) && $this->rates[$id]['pod'] === $filters['pod'] ? $this->rates[$id] : null;
    }
}

try {
    $fake = new M062PublicAlexandriaFakePrices();
    $service = new PublicShippingRateService(null, 2, str_repeat('m', 32), 1000, $fake);

    $options = $service->routeOptions(array('country_code' => 'EG'));
    m062PublicAssert($options['routes'] === array(
        array('pol' => 'GUANGZHOU', 'pod' => 'ALEXANDRIA'),
        array('pol' => 'GUANGZHOU', 'pod' => 'SOKHNA'),
    ), 'Alexandria terminal options were not collapsed to one customer destination.');

    $result = $service->search(array(
        'country_code' => 'EG',
        'pol' => 'GUANGZHOU',
        'pod' => 'ALEXANDRIA',
        'container_type' => '40HQ',
    ));
    m062PublicAssert($fake->searchPods === array('ALEXANDRIA', 'ALEXANDRIA OLD PORT', 'ALEXANDRIA TMT'), 'Alexandria search did not expand to exact pricing destinations.');
    m062PublicAssert(array_column($result['results'], 'pod') === array('ALEXANDRIA TMT', 'ALEXANDRIA OLD PORT', 'ALEXANDRIA'), 'Terminal cards were not returned in public price order.');
    $trend = $service->priceTrend(array('country_code' => 'EG', 'pol' => 'GUANGZHOU', 'pod' => 'ALEXANDRIA', 'container_type' => '40HQ'));
    m062PublicAssert($trend['grouped_destination'] === true && $trend['points'] === array(), 'Grouped terminal history was exposed as one misleading flat trend.');
    m062PublicAssert($fake->trendPods === array('ALEXANDRIA OLD PORT', 'ALEXANDRIA TMT', 'ALEXANDRIA'), 'Alexandria history did not query exact destinations independently.');
    m062PublicAssert(array_column($trend['series'], 'pod') === $fake->trendPods, 'Alexandria history series lost their exact destination labels.');
    m062PublicAssert(array_column(array_column($trend['series'], 'points'), 0)[0]['price'] === 2400, 'Old Port history was not preserved as its own series.');
    m062PublicAssert(array_column(array_column($trend['series'], 'points'), 0)[1]['price'] === 2350, 'TMT history was not preserved as its own series.');
    $trendJson = json_encode($trend);
    foreach (array('cost_price', 'supplier_cost', 'base_margin', 'rate_id', 'fingerprint', 'shipping_line') as $forbidden) {
        m062PublicAssert(strpos($trendJson, $forbidden) === false, 'Public Alexandria trend leaked '.$forbidden.'.');
    }

    $failingTrend = new M062PublicAlexandriaFakePrices();
    $failingTrend->trendErrorPod = 'ALEXANDRIA TMT';
    $failingService = new PublicShippingRateService(null, 2, str_repeat('f', 32), 1000, $failingTrend);
    try {
        $failingService->priceTrend(array('country_code' => 'EG', 'pol' => 'GUANGZHOU', 'pod' => 'ALEXANDRIA', 'container_type' => '40HQ'));
        throw new RuntimeException('Partial Alexandria trend was returned after a terminal query failed.');
    } catch (PublicShippingRateException $e) {
        m062PublicAssert($e->getPublicCode() === 'rate_service_unavailable' && $e->getHttpStatus() === 503, 'Grouped trend failure did not fail closed.');
    }

    foreach ($result['results'] as $publicRate) {
        m062PublicAssert(!isset($publicRate['rate_id']) && !isset($publicRate['fingerprint']), 'Public Alexandria result leaked an internal identifier.');
        $resolved = $service->resolveToken($publicRate['rate_token']);
        m062PublicAssert($resolved['filters']['pod'] === $publicRate['pod'], 'Booking token did not retain the exact terminal POD.');
        m062PublicAssert($resolved['priced']['pod'] === $publicRate['pod'], 'Booking revalidation crossed Alexandria terminals.');
    }

    $outsideEgypt = new M062PublicAlexandriaFakePrices();
    $outsideService = new PublicShippingRateService(null, 2, str_repeat('n', 32), 1000, $outsideEgypt);
    $outsideService->search(array('country_code' => 'MA', 'pol' => 'GUANGZHOU', 'pod' => 'ALEXANDRIA', 'container_type' => '40HQ'));
    m062PublicAssert($outsideEgypt->searchPods === array('ALEXANDRIA'), 'Alexandria grouping escaped the Egypt country boundary.');

    echo "M062_PUBLIC_ALEXANDRIA_GROUP=PASS option=ONE cards=THREE booking=EXACT trend=SEPARATE\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'M062_PUBLIC_ALEXANDRIA_GROUP=FAIL REASON='.$e->getMessage()."\n");
    exit(1);
}
