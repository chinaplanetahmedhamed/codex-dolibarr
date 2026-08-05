<?php

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetshippingprices/class/ShippingPriceService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php';

/**
 * Deterministic shipping price service for Phase 2, n8n and AI clients.
 * Supplier cost and internal margins are never returned by this endpoint.
 *
 * @access protected
 * @class DolibarrApiAccess {@requires user,external}
 */
class ChinaPlanetShippingPrices extends DolibarrApi
{
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * Search currently published rates.
     *
     * @param string $pol Port of loading
     * @param string $pod Port of destination
     * @param string $container_type Container type
     * @param string $country_code ISO-2 country
     * @param string $shipping_line Shipping line or blank
     * @param string $service_type direct, transit or blank
     * @param string $incoterm FOB or EXW
     * @param string $cargo_type normal, battery, oil, powder, chemical or copy_brand
     * @param string $documents Comma-separated document codes
     * @return array
     * @url GET search
     */
    public function search($pol = '', $pod = '', $container_type = '', $country_code = '', $shipping_line = '', $service_type = '', $incoterm = 'FOB', $cargo_type = 'normal', $documents = '')
    {
        $apiUser = DolibarrApiAccess::$user;
        if (!$apiUser || (!$apiUser->hasRight('chinaplanetshippingprices', 'api') && !$apiUser->hasRight('chinaplanetshippingprices', 'read') && empty($apiUser->admin))) {
            throw new RestException(403, 'Shipping price API permission required.');
        }
        $service = new ShippingPriceService($this->db);
        $payload = $service->search(array(
            'pol' => $pol, 'pod' => $pod, 'container_type' => $container_type,
            'country_code' => $country_code, 'shipping_line' => $shipping_line,
            'service_type' => $service_type, 'incoterm' => $incoterm,
            'cargo_type' => $cargo_type,
            'documents' => $documents === '' ? array() : array_map('trim', explode(',', $documents)),
        ), false, 100);
        if ($payload['error'] !== '') throw new RestException(500, 'Shipping price search failed.');
        return array(
            'api_version' => '1.0',
            'generated_at' => dol_print_date(dol_now('gmt'), '%Y-%m-%dT%H:%M:%SZ', 'gmt'),
            'filters' => $payload['filters'],
            'count' => count($payload['results']),
            'results' => $payload['results'],
        );
    }

    /**
     * Restricted website gateway search. This is deliberately separate from
     * the authenticated M035/customer API permission and projection.
     *
     * @url GET website-rates
     */
    public function websiteRates($pol = '', $pod = '', $container_type = '', $country_code = '', $service_type = '', $incoterm = 'FOB')
    {
        $apiUser = DolibarrApiAccess::$user;
        if (!$apiUser || (!$apiUser->hasRight('chinaplanetshippingprices', 'website_rate_search') && empty($apiUser->admin))) {
            throw new RestException(403, 'Website rate-search permission required.');
        }
        try {
            $service = new PublicShippingRateService($this->db);
            return $service->search(array(
                'pol' => $pol,
                'pod' => $pod,
                'container_type' => $container_type,
                'country_code' => $country_code,
                'service_type' => $service_type,
                'incoterm' => $incoterm,
            ));
        } catch (PublicShippingRateException $e) {
            throw new RestException($e->getHttpStatus(), $e->getPublicCode());
        }
    }

    /**
     * Bounded route options for the public website's dependent selects.
     * No prices, rate identifiers, carrier names or availability counts are returned.
     *
     * @url GET website-route-options
     */
    public function websiteRouteOptions($country_code = '')
    {
        $apiUser = DolibarrApiAccess::$user;
        if (!$apiUser || (!$apiUser->hasRight('chinaplanetshippingprices', 'website_rate_search') && empty($apiUser->admin))) {
            throw new RestException(403, 'Website rate-search permission required.');
        }
        try {
            $service = new PublicShippingRateService($this->db);
            return $service->routeOptions(array('country_code' => $country_code));
        } catch (PublicShippingRateException $e) {
            throw new RestException($e->getHttpStatus(), $e->getPublicCode());
        }
    }
}
