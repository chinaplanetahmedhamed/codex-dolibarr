<?php
declare(strict_types=1);

/** Exact, non-posting money rules. All public monetary inputs must be strings. */
final class OfficeExpenseMoney
{
    private $exponents;

    public function __construct(array $exponents = array('EGP'=>2, 'CNY'=>2, 'USD'=>2))
    {
        if (!extension_loaded('bcmath')) throw new LogicException('BCMath is required for exact expense calculations.');
        foreach ($exponents as $code=>$exponent) {
            if (!is_string($code) || !preg_match('/^[A-Z]{3}$/D', $code) || !is_int($exponent) || $exponent < 0 || $exponent > 4) {
                throw new InvalidArgumentException('Invalid currency configuration.');
            }
        }
        $this->exponents = $exponents;
    }

    public function amount($value, string $currency): string
    {
        $scale = $this->exponent($currency);
        $fraction = $scale ? '(?:\.[0-9]{1,'.$scale.'})?' : '';
        if (!is_string($value) || !preg_match('/^(?:0|[1-9][0-9]{0,11})'.$fraction.'$/D', $value)) {
            throw new InvalidArgumentException('Use an unsigned decimal string within currency precision (maximum 12 integer digits).');
        }
        return bcadd($value, '0', $scale);
    }

    public function total(array $values, string $currency): string
    {
        $scale=$this->exponent($currency);
        $total=bcadd('0', '0', $scale);
        foreach ($values as $value) $total=bcadd($total, $this->amount($value, $currency), $scale);
        return $total;
    }

    public function convert($value, string $currency, string $base, array $quote): array
    {
        $original = $this->amount($value, $currency);
        $scale = $this->exponent($base);
        if ($currency === $base && !$quote) {
            $rate = '1.00000000'; $date = null; $source = 'identity';
        } else {
            $rate = $quote['rate'] ?? null;
            $date = $quote['date'] ?? null;
            $source = $quote['source'] ?? null;
            if (!is_string($rate) || !preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,8})?$/D', $rate) || bccomp($rate, '0', 8) <= 0) {
                throw new InvalidArgumentException('A positive exact rate with at most 8 decimal places is required.');
            }
            self::date($date);
            self::text($source, 160);
            if ($base === $currency && bccomp($rate, '1', 8) !== 0) throw new InvalidArgumentException('Same-currency rate must be one.');
            $rate = bcadd($rate, '0', 8);
        }
        $exact = bcmul($original, $rate, $this->exponent($currency) + 8);
        $halfUnit = '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd($exact, $halfUnit, $scale);
        // Apply the same bounds to the converted estimate as to original money.
        $this->amount($rounded, $base);
        return array('original_amount'=>$original, 'original_currency'=>$currency,
            'base_currency'=>$base, 'base_amount'=>$rounded, 'rate'=>$rate,
            'rate_date'=>$date, 'rate_source'=>$source, 'rounding'=>'half_up_to_currency_exponent');
    }

    /** Single-currency operational illustration, never a ledger or posting instruction. */
    public function settlement(array $advance, array $claims, array $returns): array
    {
        $currency = $advance['currency'] ?? '';
        $scale = $this->exponent($currency);
        $start = $this->amount($advance['amount'] ?? null, $currency);
        $sums = array();
        foreach (array($claims, $returns) as $rows) {
            $total = '0';
            foreach ($rows as $row) {
                if (($row['currency'] ?? '') !== $currency) throw new InvalidArgumentException('Cross-currency settlement needs an explicitly approved conversion.');
                $total = bcadd($total, $this->amount($row['amount'] ?? null, $currency), $scale);
            }
            $sums[] = $total;
        }
        if (bccomp($sums[1], $start, $scale) > 0) throw new InvalidArgumentException('Returned advance cannot exceed the advance.');
        $net = bcsub(bcsub($start, $sums[0], $scale), $sums[1], $scale);
        $zero = bcadd('0', '0', $scale);
        return array('currency'=>$currency, 'advance'=>$start, 'expense'=>bcadd($sums[0], '0', $scale),
            'returned'=>bcadd($sums[1], '0', $scale), 'outstanding'=>bccomp($net, '0', $scale) > 0 ? $net : $zero,
            'reimbursement_due'=>bccomp($net, '0', $scale) < 0 ? bcsub('0', $net, $scale) : $zero);
    }

    public static function date($value): void
    {
        if (!is_string($value)) throw new InvalidArgumentException('Date must be YYYY-MM-DD.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Date must be a real YYYY-MM-DD date.');
    }

    public static function text($value, int $max): void
    {
        if (!is_string($value) || trim($value) === '' || strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value) || !preg_match('//u', $value)) {
            throw new InvalidArgumentException('Required text is missing, too long, or contains invalid characters.');
        }
    }

    private function exponent(string $currency): int
    {
        if (!array_key_exists($currency, $this->exponents)) throw new InvalidArgumentException('Currency is not configured.');
        return $this->exponents[$currency];
    }
}
