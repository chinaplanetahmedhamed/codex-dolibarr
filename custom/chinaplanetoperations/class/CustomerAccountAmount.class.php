<?php
/** Exact USD-base trial arithmetic. All public monetary values are decimal strings. */
class CustomerAccountAmount
{
    public static function minor($value)
    {
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,2})?$/D', $value) || bccomp($value, '0', 2) <= 0) {
            throw new InvalidArgumentException('Amount must be positive, at most 999999999.99, with at most 2 decimals.');
        }
        return bcmul($value, '100', 0);
    }
    public static function rate($value)
    {
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,5})(\.[0-9]{1,12})?$/D', $value) || bccomp($value, '0', 12) <= 0 || bccomp($value, '100000', 12) > 0) {
            throw new InvalidArgumentException('Rate must be positive, at most 100000, with at most 12 decimals.');
        }
        return bcadd($value, '0', 12);
    }
    public static function base($amount, $rate)
    {
        return bcadd(bcmul(self::minor($amount), self::rate($rate), 14), '0.5', 0);
    }
    public static function money($minor)
    {
        return bcdiv((string) $minor, '100', 2);
    }
    public static function allocate($amount, $rate, array $lines)
    {
        if (!$lines || count($lines) > 100) throw new InvalidArgumentException('Provide 1–100 allocation lines.');
        $original = self::minor($amount); $sum = '0'; $base = '0'; $out = [];
        foreach ($lines as $line) {
            $m = self::minor($line['amount'] ?? '');
            $b = self::base($line['amount'], $rate);
            $sum = bcadd($sum, $m, 0); $base = bcadd($base, $b, 0);
            $out[] = ['project'=>(int) ($line['project'] ?? 0), 'task'=>(int) ($line['task'] ?? 0), 'original_minor'=>$m, 'base_minor'=>$b, 'rounding_minor'=>'0'];
        }
        if (bccomp($sum, $original, 0) !== 0) throw new InvalidArgumentException('Allocation amounts must equal the original amount.');
        $i = count($out) - 1; $delta = bcsub(self::base($amount, $rate), $base, 0);
        // Preserve nonnegative allocation magnitudes even for many tiny lines.
        if (bccomp(bcadd($out[$i]['base_minor'], $delta, 0), '0', 0) < 0) throw new InvalidArgumentException('Allocation rounding is too small. Combine tiny allocation lines.');
        $out[$i]['base_minor'] = bcadd($out[$i]['base_minor'], $delta, 0);
        $out[$i]['rounding_minor'] = $delta;
        return $out;
    }
}
