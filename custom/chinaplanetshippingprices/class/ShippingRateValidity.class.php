<?php
/** Publication defaults only; existing customer expiry/legacy fallback is unchanged. */
class ShippingRateValidity
{
    public static function range($from = '', $to = '', $publicationDate = null)
    {
        $from = trim((string) $from);
        $to = trim((string) $to);
        if ($from === '') $from = $publicationDate === null ? date('Y-m-d') : $publicationDate;
        self::validateDate($from);
        // Existing expiry is inclusive, so seven dates end on start + six days.
        if ($to === '') $to = (new DateTimeImmutable($from))->modify('+6 days')->format('Y-m-d');
        self::validateDate($to);
        if ($to < $from) throw new InvalidArgumentException('Valid to must be on or after Valid from. / تاريخ النهاية يجب ألا يسبق البداية.');
        return array($from, $to);
    }

    public static function forBatch($batch, $from = '', $to = '', $publicationDate = null)
    {
        // Repeated publication/repair must never renew rates or rewrite history.
        if ((int) $batch->status === 2 || ($batch->import_mode ?? '') === 'history') return null;
        return self::range($from, $to, $publicationDate);
    }

    private static function validateDate($value)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', (string) $value, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new InvalidArgumentException('Enter a valid date (YYYY-MM-DD). / أدخل تاريخًا صحيحًا.');
        }
    }
}
