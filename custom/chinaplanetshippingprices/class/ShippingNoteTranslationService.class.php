<?php

/**
 * Fail-closed carrier-note policy and bounded text helpers.
 *
 * M046-C01 deliberately contains no provider endpoint, environment resolver,
 * or network transport implementation. Reintroducing any provider requires a
 * separate approved Mission and a new reviewed adapter contract.
 */
class ShippingNoteTranslationService
{
    const MAX_INPUT_CHARS = 8000;
    const MAX_OUTPUT_CHARS = 12000;
    const SAFE_DERIVED_TOKENS = array('TBD');

    /** Keep the legacy constructor signature without retaining transport state. */
    public function __construct($transport = null, ?array $environment = null, $resolver = null)
    {
    }

    /** Stored constants and provider configuration cannot enable this release. */
    public static function automaticTranslationEnabled()
    {
        return false;
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

    /** Operational tokens that a future approved translation must preserve. */
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
        foreach ($translationTokens as $token) {
            if (!in_array($token, $sourceTokens, true) && !in_array($token, self::SAFE_DERIVED_TOKENS, true)) return 'protected_token_added';
        }
        $prose = trim(str_replace($sourceTokens, ' ', $translation));
        if (preg_match('/[A-Za-z]{3,}/', $prose) !== 1) return 'translation_not_english';
        return '';
    }

    /**
     * Return before source normalization or any provider/transport processing.
     * The arguments are intentionally unused while the release policy is off.
     */
    public function translate($source, $remainingSeconds = null)
    {
        if (self::automaticTranslationEnabled()) {
            throw new RuntimeException('No translation provider is available in this release.');
        }
        return array(
            'status' => 'not_needed', 'notes_en' => '', 'source_hash' => '',
            'provider' => '', 'model' => '', 'error' => 'translation_disabled', 'attempted' => false,
        );
    }
}
