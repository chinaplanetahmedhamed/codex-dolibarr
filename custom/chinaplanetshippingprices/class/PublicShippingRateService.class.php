<?php

require_once __DIR__.'/ShippingPriceService.class.php';

class PublicShippingRateException extends RuntimeException
{
    private $publicCode;
    private $httpStatus;

    public function __construct($publicCode, $httpStatus = 422)
    {
        parent::__construct($publicCode);
        $this->publicCode = (string) $publicCode;
        $this->httpStatus = (int) $httpStatus;
    }

    public function getPublicCode() { return $this->publicCode; }
    public function getHttpStatus() { return $this->httpStatus; }
}

/**
 * Public-safe FCL search facade.
 *
 * It keeps rate identifiers and fingerprints server-side inside an
 * authenticated encrypted token. The existing ShippingPriceService remains
 * authoritative for eligibility and pricing.
 */
class PublicShippingRateService
{
    const TOKEN_AAD = 'chinaplanet-public-rate-v1';
    const TOKEN_TTL = 600;
    const RESULT_LIMIT = 20;

    private $prices;
    private $entity;
    private $tokenKey;
    private $now;

    public function __construct($db, $entity = null, $tokenKey = null, $now = null, $priceService = null)
    {
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
        $this->prices = $priceService ?: new ShippingPriceService($db, $this->entity);
        $configuredKey = $tokenKey === null ? (string) getenv('CHINAPLANET_PUBLIC_RATE_TOKEN_KEY') : (string) $tokenKey;
        if ($tokenKey === null && strlen($configuredKey) < 32 && function_exists('getDolGlobalString')) {
            $configuredKey = (string) getDolGlobalString('CHINAPLANET_PUBLIC_RATE_TOKEN_KEY');
        }
        if (strlen($configuredKey) < 32) throw new PublicShippingRateException('rate_service_not_configured', 503);
        $this->tokenKey = hash('sha256', $configuredKey, true);
        $this->now = $now === null ? time() : (int) $now;
    }

    public function search(array $input)
    {
        $filters = $this->normalizeRequiredFilters($input);
        $pricedResults = array();
        foreach ($this->publicDestinationMembers($filters) as $pod) {
            $exactFilters = $filters;
            $exactFilters['pod'] = $pod;
            $payload = $this->prices->search($exactFilters, false, self::RESULT_LIMIT);
            if (!empty($payload['error'])) throw new PublicShippingRateException('rate_service_unavailable', 503);
            foreach ($payload['results'] as $priced) {
                if (!is_array($priced) || empty($priced['rate_id']) || empty($priced['fingerprint'])) continue;
                $pricedResults[(int) $priced['rate_id']] = array('priced' => $priced, 'filters' => $exactFilters);
            }
        }
        uasort($pricedResults, function ($left, $right) {
            $priceOrder = (float) $left['priced']['final_price'] <=> (float) $right['priced']['final_price'];
            if ($priceOrder !== 0) return $priceOrder;
            $podOrder = strcasecmp((string) $left['priced']['pod'], (string) $right['priced']['pod']);
            if ($podOrder !== 0) return $podOrder;
            return strcasecmp((string) $left['priced']['shipping_line'], (string) $right['priced']['shipping_line']);
        });

        $results = array();
        foreach ($pricedResults as $entry) {
            if (count($results) >= self::RESULT_LIMIT) break;
            $priced = $entry['priced'];
            if (!is_array($priced) || empty($priced['rate_id']) || empty($priced['fingerprint'])) continue;
            $tokenPayload = array(
                'v' => 1,
                'entity' => $this->entity,
                'rate_id' => (int) $priced['rate_id'],
                'filters' => $entry['filters'],
                'fingerprint' => (string) $priced['fingerprint'],
                'exp' => $this->now + self::TOKEN_TTL,
            );
            $results[] = $this->publicProjection($priced, $this->encryptToken($tokenPayload));
        }
        return array('results' => $results);
    }

    public function routeOptions(array $input)
    {
        $countryCode = strtoupper($this->cleanFilter($input['country_code'] ?? '', 2));
        if ($countryCode !== '' && !preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new PublicShippingRateException('invalid_route_options', 422);
        }
        $options = $this->prices->getPublicRouteOptions($countryCode);
        if (!is_array($options)) throw new PublicShippingRateException('rate_service_unavailable', 503);
        $routes = array();
        foreach (isset($options['routes']) && is_array($options['routes']) ? $options['routes'] : array() as $route) {
            if (!is_array($route) || empty($route['pol']) || empty($route['pod'])) continue;
            $publicPod = $this->publicDestinationLabel($countryCode, $route['pod']);
            $key = strtoupper((string) $route['pol'])."\0".strtoupper($publicPod);
            $routes[$key] = array('pol' => (string) $route['pol'], 'pod' => $publicPod);
        }
        uasort($routes, function ($left, $right) {
            $polOrder = strcasecmp($left['pol'], $right['pol']);
            return $polOrder !== 0 ? $polOrder : strcasecmp($left['pod'], $right['pod']);
        });
        return array(
            'country_code' => $countryCode,
            'countries' => array_values(isset($options['countries']) && is_array($options['countries']) ? $options['countries'] : array()),
            'routes' => array_values($routes),
        );
    }

    public function priceTrend(array $input)
    {
        $filters = $this->normalizeRequiredFilters($input);
        $groupedDestination = $this->isAlexandriaGroup($filters['country_code'], $filters['pod']);
        $trend = array('points' => array(), 'error' => '');
        $series = array();
        if ($groupedDestination) {
            foreach ($this->alexandriaTrendMembers() as $pod) {
                $memberFilters = $filters;
                $memberFilters['pod'] = $pod;
                $memberTrend = $this->prices->getPublicPriceTrend($memberFilters, 12);
                if (!empty($memberTrend['error'])) throw new PublicShippingRateException('rate_service_unavailable', 503);
                $memberPoints = array_values(isset($memberTrend['points']) && is_array($memberTrend['points']) ? $memberTrend['points'] : array());
                if ($memberPoints) $series[] = array('pod' => $pod, 'points' => $memberPoints);
            }
        } else {
            $trend = $this->prices->getPublicPriceTrend($filters, 12);
            if (!empty($trend['error'])) throw new PublicShippingRateException('rate_service_unavailable', 503);
        }
        return array(
            'route' => array(
                'country_code' => $filters['country_code'],
                'pol' => $filters['pol'],
                'pod' => $filters['pod'],
                'container_type' => $filters['container_type'],
            ),
            'period_months' => 12,
            'reference_only' => true,
            'grouped_destination' => $groupedDestination,
            'points' => array_values(isset($trend['points']) && is_array($trend['points']) ? $trend['points'] : array()),
            'series' => $series,
        );
    }

    public function resolveToken($token)
    {
        $payload = $this->decryptToken((string) $token);
        if ((int) ($payload['entity'] ?? 0) !== $this->entity || (int) ($payload['exp'] ?? 0) <= $this->now) {
            throw new PublicShippingRateException('rate_changed', 409);
        }
        $filters = $this->normalizeRequiredFilters(isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : array());
        $priced = $this->prices->getCustomerRateById((int) ($payload['rate_id'] ?? 0), $filters);
        if (!$priced || empty($priced['fingerprint']) || !hash_equals((string) ($payload['fingerprint'] ?? ''), (string) $priced['fingerprint'])) {
            throw new PublicShippingRateException('rate_changed', 409);
        }
        return array('priced' => $priced, 'filters' => $filters);
    }

    public function buildCommercialSnapshot(array $resolved, $containerQuantity, $acknowledgedAt)
    {
        $priced = $resolved['priced'];
        $quantity = max(1, min(100, (int) $containerQuantity));
        $unitPrice = round((float) $priced['final_price'], 2);
        $terms = array(
            'included' => array_values($priced['included']),
            'excluded' => array_values($priced['excluded']),
            'warnings' => array_values($priced['warnings']),
            'notes' => (string) $priced['notes'],
        );
        return array(
            'schema_version' => 1,
            'route' => array(
                'pol' => (string) $priced['pol'],
                'pod' => (string) $priced['pod'],
                'country_code' => (string) $priced['country_code'],
                'public_carrier_label' => (string) $priced['shipping_line'],
                'container_type' => (string) $priced['container_type'],
                'service_type' => (string) $priced['service_type'],
            ),
            'currency' => (string) $priced['currency'],
            'price_basis' => 'per_container',
            'unit_price' => $unitPrice,
            'container_quantity' => $quantity,
            'total' => round($unitPrice * $quantity, 2),
            'valid_from' => (string) $priced['valid_from'],
            'valid_to' => (string) $priced['valid_to'],
            'components' => array_values($priced['components']),
            'terms' => $terms,
            'terms_hash' => hash('sha256', json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'fingerprint' => (string) $priced['fingerprint'],
            'acknowledged_at' => (string) $acknowledgedAt,
            'space_confirmation' => false,
        );
    }

    private function normalizeRequiredFilters(array $input)
    {
        $pol = $this->cleanFilter($input['pol'] ?? '', 80);
        $pod = $this->cleanFilter($input['pod'] ?? '', 80);
        $container = strtoupper($this->cleanFilter($input['container_type'] ?? '', 16));
        if ($pol === '' || $pod === '' || !in_array($container, array('20GP', '40GP', '40HQ'), true)) {
            throw new PublicShippingRateException('invalid_rate_search', 422);
        }
        if ($this->hasWildcard($pol) || $this->hasWildcard($pod)) throw new PublicShippingRateException('invalid_rate_search', 422);
        return array(
            'pol' => $pol,
            'pod' => $pod,
            'container_type' => $container,
            'country_code' => strtoupper($this->cleanFilter($input['country_code'] ?? '', 2)),
            'shipping_line' => '',
            'service_type' => $this->cleanFilter($input['service_type'] ?? '', 16),
            'incoterm' => strtoupper($this->cleanFilter($input['incoterm'] ?? 'FOB', 8)),
            'cargo_type' => 'normal',
            'documents' => array(),
        );
    }

    private function cleanFilter($value, $limit)
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string) $value));
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    private function hasWildcard($value)
    {
        return strpos($value, '%') !== false || strpos($value, '*') !== false || strpos($value, '_') !== false;
    }

    /**
     * Alexandria is one customer search destination but remains three exact
     * pricing destinations. Expansion is Egypt-only and tokens retain the
     * exact terminal filter used for the selected card.
     */
    private function publicDestinationMembers(array $filters)
    {
        if ($this->isAlexandriaGroup($filters['country_code'], $filters['pod'])) {
            return array('ALEXANDRIA', 'ALEXANDRIA OLD PORT', 'ALEXANDRIA TMT');
        }
        return array($filters['pod']);
    }

    /**
     * Keep Alexandria terminal history exact. The generic legacy destination
     * remains a separate last series and is never averaged into either terminal.
     */
    private function alexandriaTrendMembers()
    {
        return array('ALEXANDRIA OLD PORT', 'ALEXANDRIA TMT', 'ALEXANDRIA');
    }

    private function publicDestinationLabel($countryCode, $pod)
    {
        $pod = trim((string) $pod);
        if (strtoupper(trim((string) $countryCode)) === 'EG' && in_array(strtoupper($pod), array('ALEXANDRIA', 'ALEXANDRIA OLD PORT', 'ALEXANDRIA TMT'), true)) {
            return 'ALEXANDRIA';
        }
        return $pod;
    }

    private function isAlexandriaGroup($countryCode, $pod)
    {
        return strtoupper(trim((string) $countryCode)) === 'EG' && strtoupper(trim((string) $pod)) === 'ALEXANDRIA';
    }

    private function publicProjection(array $priced, $token)
    {
        return array(
            'pol' => (string) $priced['pol'],
            'pod' => (string) $priced['pod'],
            'country_code' => (string) $priced['country_code'],
            'public_carrier_label' => (string) $priced['shipping_line'],
            'container_type' => (string) $priced['container_type'],
            'service_type' => (string) $priced['service_type'],
            'currency' => (string) $priced['currency'],
            'unit_price' => round((float) $priced['final_price'], 2),
            'price_basis' => 'per_container',
            'valid_from' => (string) $priced['valid_from'],
            'valid_to' => (string) $priced['valid_to'],
            'included' => array_values($priced['included']),
            'excluded' => array_values($priced['excluded']),
            'notes' => (string) $priced['notes'],
            'warnings' => array_values($priced['warnings']),
            'rate_token' => $token,
        );
    }

    private function encryptToken(array $payload)
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'aes-256-gcm',
            $this->tokenKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::TOKEN_AAD,
            16
        );
        if ($ciphertext === false) throw new PublicShippingRateException('rate_service_unavailable', 503);
        return $this->base64UrlEncode($iv.$tag.$ciphertext);
    }

    private function decryptToken($token)
    {
        $raw = $this->base64UrlDecode($token);
        if ($raw === false || strlen($raw) < 29) throw new PublicShippingRateException('rate_changed', 409);
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $json = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->tokenKey, OPENSSL_RAW_DATA, $iv, $tag, self::TOKEN_AAD);
        $payload = $json === false ? null : json_decode($json, true);
        if (!is_array($payload) || (int) ($payload['v'] ?? 0) !== 1) throw new PublicShippingRateException('rate_changed', 409);
        return $payload;
    }

    private function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode($value)
    {
        if (!preg_match('/^[A-Za-z0-9_-]{32,4096}$/', (string) $value)) return false;
        $padding = strlen($value) % 4;
        if ($padding) $value .= str_repeat('=', 4 - $padding);
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
