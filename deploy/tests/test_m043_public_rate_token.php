<?php
/** Pure unit test for M043 authenticated public rate tokens. */
$root = dirname(__DIR__, 2);
require_once $root.'/custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php';
function m043Assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
class M043FakePrices {
    public $priced;
    public function __construct() {
        $this->priced = array(
            'rate_id'=>73, 'fingerprint'=>hash('sha256','rate-73-v1'), 'pol'=>'NANSHA', 'pod'=>'ALEXANDRIA', 'country_code'=>'EG',
            'shipping_line'=>'Public Carrier', 'container_type'=>'40HQ', 'service_type'=>'direct', 'currency'=>'USD',
            'final_price'=>2450.50, 'valid_from'=>'2026-08-01', 'valid_to'=>'2026-08-20', 'included'=>array('Ocean freight'),
            'excluded'=>array('Destination charges'), 'notes'=>'Subject to space', 'warnings'=>array(), 'components'=>array(array('label'=>'Ocean freight','amount'=>2450.50)),
        );
    }
    public function search($filters, $includeCost, $limit) { return array('results'=>array($this->priced), 'error'=>''); }
    public function getPublicRouteOptions($countryCode) {
        if ($countryCode === '') return array('countries'=>array(array('code'=>'EG','label'=>'Egypt')), 'routes'=>array());
        return array('countries'=>array(), 'routes'=>array(array('pol'=>'NANSHA','pod'=>'ALEXANDRIA')));
    }
    public function getPublicPriceTrend($filters, $months) {
        return array('points'=>array(array('date'=>'2026-07-01','price'=>2300.00,'currency'=>'USD')), 'error'=>'');
    }
    public function getCustomerRateById($id, $filters) { return $id === 73 ? $this->priced : null; }
}
try {
    $fake = new M043FakePrices();
    $service = new PublicShippingRateService(null, 2, str_repeat('k', 32), 1000, $fake);
    $result = $service->search(array('pol'=>'NANSHA','pod'=>'ALEXANDRIA','container_type'=>'40HQ'));
    $countries = $service->routeOptions(array());
    m043Assert($countries['countries'][0]['code'] === 'EG' && empty($countries['routes']), 'country options missing');
    $routes = $service->routeOptions(array('country_code'=>'eg'));
    m043Assert($routes['country_code'] === 'EG' && $routes['routes'][0]['pod'] === 'ALEXANDRIA', 'dependent route options missing');
    $trend = $service->priceTrend(array('country_code'=>'EG','pol'=>'NANSHA','pod'=>'ALEXANDRIA','container_type'=>'40HQ'));
    m043Assert($trend['reference_only'] === true && $trend['points'][0]['price'] === 2300.00, 'public trend projection missing');
    $trendJson = json_encode($trend);
    foreach (array('cost_price','supplier_cost','base_margin','rate_id','fingerprint','shipping_line') as $forbidden) m043Assert(strpos($trendJson, $forbidden) === false, 'public trend leaks '.$forbidden);
    m043Assert(count($result['results']) === 1, 'rate missing');
    $public = $result['results'][0];
    m043Assert(!isset($public['rate_id']) && !isset($public['fingerprint']) && isset($public['rate_token']), 'identifier leaked');
    m043Assert(strpos($public['rate_token'], '73') === false && strpos($public['rate_token'], $fake->priced['fingerprint']) === false, 'opaque token leaks source data');
    $resolved = $service->resolveToken($public['rate_token']);
    m043Assert((int)$resolved['priced']['rate_id'] === 73, 'valid token did not resolve');
    $tampered = substr($public['rate_token'], 0, -1).($public['rate_token'][strlen($public['rate_token'])-1] === 'A' ? 'B' : 'A');
    try { $service->resolveToken($tampered); throw new RuntimeException('tampered token accepted'); } catch (PublicShippingRateException $e) { m043Assert($e->getPublicCode()==='rate_changed', 'unsafe tamper error'); }
    $expired = new PublicShippingRateService(null, 2, str_repeat('k', 32), 1600, $fake);
    try { $expired->resolveToken($public['rate_token']); throw new RuntimeException('expired token accepted'); } catch (PublicShippingRateException $e) { m043Assert($e->getPublicCode()==='rate_changed', 'unsafe expiry error'); }
    echo "M043_RATE_TOKEN=PASS encrypted=YES ttl=600 projection=ALLOWLIST\n";
} catch(Throwable $e) { fwrite(STDERR,"M043_RATE_TOKEN=FAIL REASON=".$e->getMessage()."\n"); exit(1); }
