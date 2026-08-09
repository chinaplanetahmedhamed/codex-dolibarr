<?php

/**
 * Bounded translation adapter for untrusted shipping-agent source notes.
 *
 * Runtime connection settings are process-environment-only. Provider output is
 * is accepted automatically when the provider returns a valid bounded response.
 * Staff can correct the translated text from the import review page.
 */
class ShippingNoteTranslationService
{
    const MAX_INPUT_CHARS = 8000;
    const MAX_OUTPUT_CHARS = 12000;
    const MAX_RESPONSE_BYTES = 65536;
    const CONNECT_TIMEOUT = 2;
    const REQUEST_TIMEOUT = 8;
    const FREE_ENDPOINT = 'https://api.mymemory.translated.net/get';
    const FREE_LANGUAGE_PAIR = 'zh-CN|en';
    const FREE_MAX_QUERY_BYTES = 500;

    private $transport;
    private $environment;
    private $resolver;

    public function __construct($transport = null, ?array $environment = null, $resolver = null)
    {
        $this->transport = $transport;
        $this->environment = $environment;
        $this->resolver = $resolver;
    }

    public static function normalizeSource($value)
    {
        $value = (string) $value;
        if (!mb_check_encoding($value, 'UTF-8')) return '';
        $value = str_replace("\0", '', $value);
        $value = str_replace(array("\r\n", "\r"), "\n", $value);
        $value = str_replace(array('\\r\\n', '\\n', '\\r'), "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/[ \t]+\n/u', "\n", $value);
        $value = preg_replace('/\n[ \t]+/u', "\n", $value);
        return trim((string) $value);
    }

    public static function containsHan($value)
    {
        return preg_match('/\p{Han}/u', (string) $value) === 1;
    }

    public static function safeText($value, $maxChars)
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) return '';
        $value = self::normalizeSource($value);
        if (preg_match('/<\s*\/?\s*[A-Za-z][^>]*>/u', $value)) return '';
        if ($value === '' || mb_strlen($value, 'UTF-8') > (int) $maxChars) return '';
        return $value;
    }

    /** Operational tokens that automatic translation must preserve verbatim. */
    public static function protectedTokens($value)
    {
        $value = self::normalizeSource($value);
        $tokens = array();
        if (preg_match_all('/(?<![A-Za-z0-9])[A-Za-z0-9](?:[A-Za-z0-9._:\/-]{0,62}[A-Za-z0-9])?(?![A-Za-z0-9])/u', $value, $matches)) {
            foreach ($matches[0] as $token) {
                $hasDigit = preg_match('/\d/', $token) === 1;
                $hasLetter = preg_match('/[A-Za-z]/', $token) === 1;
                $isUpperCode = $hasLetter && strlen($token) >= 2 && strtoupper($token) === $token;
                if (($hasDigit || $isUpperCode) && !in_array($token, $tokens, true)) $tokens[] = $token;
            }
        }
        usort($tokens, function ($a, $b) { return strlen($b) <=> strlen($a); });
        return $tokens;
    }

    public static function validateEnglishTranslation($source, $translation)
    {
        $source = self::normalizeSource($source);
        $translation = self::safeText($translation, self::MAX_OUTPUT_CHARS);
        if ($translation === '') return 'translation_invalid';
        if ($translation === $source && self::containsHan($source)) return 'translation_matches_source';
        if (self::containsHan($translation)) return 'translation_not_english';
        $sourceTokens = self::protectedTokens($source);
        $translationTokens = self::protectedTokens($translation);
        foreach ($sourceTokens as $token) if (strpos($translation, $token) === false) return 'protected_token_missing';
        foreach ($translationTokens as $token) if (!in_array($token, $sourceTokens, true)) return 'protected_token_added';
        $prose = trim(str_replace($sourceTokens, ' ', $translation));
        if (preg_match('/[A-Za-z]{3,}/', $prose) !== 1) return 'translation_not_english';
        return '';
    }

    /**
     * @return array status, notes_en, source_hash, provider, model, error, attempted
     */
    public function translate($source, $remainingSeconds = null)
    {
        $source = self::normalizeSource($source);
        $result = array(
            'status' => 'not_needed', 'notes_en' => '', 'source_hash' => $source === '' ? '' : hash('sha256', $source),
            'provider' => '', 'model' => '', 'error' => '', 'attempted' => false,
        );
        if ($source === '') return $result;
        if (mb_strlen($source, 'UTF-8') > self::MAX_INPUT_CHARS) {
            $result['status'] = 'failed'; $result['error'] = 'input_too_long'; return $result;
        }
        if (!self::containsHan($source)) {
            $english = self::safeText($source, self::MAX_OUTPUT_CHARS);
            if ($english === '') { $result['status'] = 'failed'; $result['error'] = 'source_markup_invalid'; return $result; }
            $result['status'] = 'approved'; $result['notes_en'] = $english; return $result;
        }

        $endpoint = trim($this->env('CHINAPLANETSP_TRANSLATION_ENDPOINT'));
        $model = trim($this->env('CHINAPLANETSP_TRANSLATION_MODEL'));
        $apiKey = $this->env('CHINAPLANETSP_TRANSLATION_API_KEY');
        if ($endpoint === '' && $model === '') {
            $endpoint = self::FREE_ENDPOINT;
            $model = self::FREE_LANGUAGE_PAIR;
            $apiKey = '';
        } elseif ($endpoint === '' || $model === '') {
            $result['status'] = 'pending'; $result['error'] = 'provider_not_configured'; return $result;
        }
        $target = $this->validateEndpoint($endpoint);
        if (empty($target['ok'])) {
            $result['status'] = 'failed'; $result['error'] = $target['error']; return $result;
        }
        $isFreeProvider = $target['provider'] === 'api.mymemory.translated.net';
        if ($isFreeProvider) {
            if ($endpoint !== self::FREE_ENDPOINT || $model !== self::FREE_LANGUAGE_PAIR) {
                $result['status'] = 'failed'; $result['error'] = 'free_provider_config_invalid'; return $result;
            }
            if (strlen($source) > self::FREE_MAX_QUERY_BYTES) {
                $result['status'] = 'failed'; $result['error'] = 'free_provider_input_too_long'; return $result;
            }
        } elseif (!$this->safeIdentifier($model, 128)) {
            $result['status'] = 'failed'; $result['error'] = 'model_invalid'; return $result;
        }

        $result['attempted'] = true;
        $result['provider'] = $target['provider'];
        $result['model'] = $model;
        if ($isFreeProvider) {
            $payload = array('q'=>$source, 'langpair'=>self::FREE_LANGUAGE_PAIR, 'mt'=>'1');
            $headers = array('Accept: application/json');
        } else {
            $payload = array(
                'model' => $model,
                'temperature' => 0,
                'messages' => array(
                    array('role' => 'system', 'content' => 'Translate the shipping carrier note into clear operational English. Copy every date, number, vessel name, voyage code, currency, unit, acronym, and alphanumeric token exactly as written in the source; do not reformat tokens such as 2026.7.20 or 8.31. Do not add new dates, numbers, codes, abbreviations, or facts. Preserve line breaks. Return English plain text only, with no Chinese, Markdown, or HTML.'),
                    array('role' => 'user', 'content' => $source),
                ),
            );
            if ($target['provider'] === 'api.deepseek.com' && strpos($model, 'deepseek-v4-') === 0) {
                $payload['thinking'] = array('type'=>'disabled');
            }
            $headers = array('Accept: application/json', 'Content-Type: application/json');
            if ($apiKey !== '') $headers[] = 'Authorization: Bearer '.$apiKey;
        }
        $remainingMs = $remainingSeconds === null ? self::REQUEST_TIMEOUT * 1000 : max(1, min(self::REQUEST_TIMEOUT * 1000, (int) floor((float) $remainingSeconds * 1000)));
        $options = array(
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => min(self::REQUEST_TIMEOUT, max(1, (int) ceil($remainingMs / 1000))),
            'connect_timeout_ms' => min(self::CONNECT_TIMEOUT * 1000, $remainingMs),
            'timeout_ms' => $remainingMs,
            'max_response_bytes' => self::MAX_RESPONSE_BYTES,
            'resolve' => $target['resolve'],
            'method' => $isFreeProvider ? 'GET' : 'POST',
        );

        try {
            $response = $this->transport
                ? call_user_func($this->transport, $endpoint, $payload, $headers, $options)
                : $this->curlTransport($endpoint, $payload, $headers, $options);
        } catch (Throwable $e) {
            $result['status'] = 'failed'; $result['error'] = 'transport_exception'; return $result;
        }
        if (!is_array($response)) {
            $result['status'] = 'failed'; $result['error'] = 'transport_invalid'; return $result;
        }
        if (!empty($response['error'])) {
            $result['status'] = 'failed'; $result['error'] = $this->safeErrorCode($response['error']); return $result;
        }
        $statusCode = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');
        if ($statusCode < 200 || $statusCode >= 300) {
            $result['status'] = 'failed'; $result['error'] = 'provider_http_error'; return $result;
        }
        if ($body === '' || strlen($body) > self::MAX_RESPONSE_BYTES || !mb_check_encoding($body, 'UTF-8')) {
            $result['status'] = 'failed'; $result['error'] = 'provider_response_invalid'; return $result;
        }
        $decoded = json_decode($body, true);
        if ($isFreeProvider) {
            $providerStatus = is_array($decoded) ? (int) ($decoded['responseStatus'] ?? 0) : 0;
            $providerMessage = is_array($decoded) ? strtolower((string) ($decoded['responseDetails'] ?? '')) : '';
            $rawTranslation = is_array($decoded) ? ($decoded['responseData']['translatedText'] ?? null) : null;
            if ($providerStatus === 403 || $providerStatus === 429 || strpos($providerMessage, 'quota') !== false || (is_string($rawTranslation) && stripos($rawTranslation, 'mymemory warning') !== false)) {
                $result['status'] = 'failed'; $result['error'] = 'provider_quota_exhausted'; return $result;
            }
            if ($providerStatus !== 200 || !is_string($rawTranslation)) {
                $result['status'] = 'failed'; $result['error'] = 'provider_schema_invalid'; return $result;
            }
            $rawTranslation = html_entity_decode($rawTranslation, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } else {
            if (!is_array($decoded) || !isset($decoded['choices'][0]['message']['content']) || !is_string($decoded['choices'][0]['message']['content'])) {
                $result['status'] = 'failed'; $result['error'] = 'provider_schema_invalid'; return $result;
            }
            $rawTranslation = $decoded['choices'][0]['message']['content'];
        }
        if (preg_match('/<\s*\/?\s*[A-Za-z][^>]*>/u', $rawTranslation)) {
            $result['status'] = 'failed'; $result['error'] = 'translation_markup_invalid'; return $result;
        }
        $translation = self::safeText($rawTranslation, self::MAX_OUTPUT_CHARS);
        $validationError = self::validateEnglishTranslation($source, $translation);
        if ($validationError !== '') { $result['status'] = 'failed'; $result['error'] = $validationError; return $result; }
        $result['status'] = 'approved';
        $result['notes_en'] = $translation;
        return $result;
    }

    private function env($name)
    {
        if ($this->environment !== null) return isset($this->environment[$name]) ? (string) $this->environment[$name] : '';
        $value = getenv($name);
        return $value === false ? '' : (string) $value;
    }

    private function safeIdentifier($value, $maxLength)
    {
        return $value !== '' && strlen($value) <= $maxLength && preg_match('/^[A-Za-z0-9._:\/-]+$/', $value) === 1;
    }

    private function safeErrorCode($value)
    {
        $allowed = array('timeout', 'response_too_large', 'connection_failed', 'tls_failed', 'transport_exception', 'transport_invalid');
        $value = strtolower((string) $value);
        return in_array($value, $allowed, true) ? $value : 'provider_unavailable';
    }

    private function validateEndpoint($endpoint)
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return array('ok'=>false, 'error'=>'endpoint_invalid');
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) return array('ok'=>false, 'error'=>'endpoint_unsafe');
        $scheme = strtolower($parts['scheme']);
        $host = strtolower(trim($parts['host'], '[]'));
        if (!in_array($scheme, array('https', 'http'), true) || $host === '') return array('ok'=>false, 'error'=>'endpoint_scheme_invalid');
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) return array('ok'=>false, 'error'=>'endpoint_port_invalid');

        $ips = $this->resolveHost($host);
        if (!$ips) return array('ok'=>false, 'error'=>'endpoint_dns_invalid');
        $loopbackOnly = true;
        foreach ($ips as $ip) {
            if (!$this->isLoopback($ip)) $loopbackOnly = false;
            if (!$this->isLoopback($ip) && !$this->isPublicIp($ip)) return array('ok'=>false, 'error'=>'endpoint_ip_forbidden');
        }
        if ($scheme === 'http' && !$loopbackOnly) return array('ok'=>false, 'error'=>'endpoint_https_required');

        $pin = $ips[0];
        $resolveHost = strpos($pin, ':') !== false ? '['.$pin.']' : $pin;
        return array(
            'ok'=>true,
            'provider'=>mb_substr($host, 0, 64),
            'resolve'=>array($host.':'.$port.':'.$resolveHost),
        );
    }

    private function resolveHost($host)
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) return array($host);
        if ($this->resolver) {
            $resolved = call_user_func($this->resolver, $host);
            return is_array($resolved) ? array_values(array_unique(array_filter($resolved, function ($ip) { return (bool) filter_var($ip, FILTER_VALIDATE_IP); }))) : array();
        }
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = array();
        if (is_array($records)) foreach ($records as $record) {
            $ip = isset($record['ip']) ? $record['ip'] : (isset($record['ipv6']) ? $record['ipv6'] : '');
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) $ips[] = $ip;
        }
        return array_values(array_unique($ips));
    }

    private function isLoopback($ip)
    {
        if ($ip === '::1') return true;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && strpos($ip, '127.') === 0;
    }

    private function isPublicIp($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function curlTransport($endpoint, array $payload, array $headers, array $options)
    {
        if (!function_exists('curl_init')) return array('status'=>0, 'body'=>'', 'error'=>'connection_failed');
        $method = isset($options['method']) && $options['method'] === 'GET' ? 'GET' : 'POST';
        if ($method === 'GET') {
            $query = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
            if ($query === '') return array('status'=>0, 'body'=>'', 'error'=>'transport_invalid');
            $requestUrl = $endpoint.'?'.$query;
            $encoded = '';
        } else {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) return array('status'=>0, 'body'=>'', 'error'=>'transport_invalid');
            $requestUrl = $endpoint;
        }
        $handle = curl_init($requestUrl);
        $body = '';
        $tooLarge = false;
        $curlOptions = array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT_MS => (int) $options['connect_timeout_ms'],
            CURLOPT_TIMEOUT_MS => (int) $options['timeout_ms'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => 0,
            CURLOPT_RESOLVE => $options['resolve'],
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$tooLarge, $options) {
                if (strlen($body) + strlen($chunk) > (int) $options['max_response_bytes']) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        );
        if ($method === 'POST') {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = $encoded;
        } else {
            $curlOptions[CURLOPT_HTTPGET] = true;
        }
        curl_setopt_array($handle, $curlOptions);
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errno = curl_errno($handle);
        curl_close($handle);
        if ($tooLarge) return array('status'=>$status, 'body'=>'', 'error'=>'response_too_large');
        if ($ok === false) return array('status'=>$status, 'body'=>'', 'error'=>$errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'connection_failed');
        return array('status'=>$status, 'body'=>$body, 'error'=>'');
    }
}
