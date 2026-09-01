<?php

/** Versioned, fail-closed AQL lookup adapter. It contains no licensed table data. */
class QcAqlService
{
    private $dataset; private $datasetId; private $datasetHash;

    public static function configured()
    {
        return !empty($GLOBALS['conf']->global->CHINAPLANETOPERATIONS_AQL_DATASET_PATH) && !empty($GLOBALS['conf']->global->CHINAPLANETOPERATIONS_AQL_DATASET_SHA256);
    }

    public function __construct($path = null, $expectedHash = null)
    {
        if ($path === null) $path = !empty($GLOBALS['conf']->global->CHINAPLANETOPERATIONS_AQL_DATASET_PATH) ? (string) $GLOBALS['conf']->global->CHINAPLANETOPERATIONS_AQL_DATASET_PATH : '';
        if ($expectedHash === null) $expectedHash = !empty($GLOBALS['conf']->global->CHINAPLANETOPERATIONS_AQL_DATASET_SHA256) ? strtolower((string) $GLOBALS['conf']->global->CHINAPLANETOPERATIONS_AQL_DATASET_SHA256) : '';
        if ($path === '' || $path[0] !== '/' || !is_file($path) || is_link($path) || !preg_match('/^[a-f0-9]{64}$/', (string) $expectedHash)) throw new RuntimeException('Validated AQL dataset is not configured.');
        $actual = hash_file('sha256', $path); if (!is_string($actual) || !hash_equals((string) $expectedHash, $actual)) throw new RuntimeException('AQL dataset hash mismatch.');
        $data = json_decode((string) file_get_contents($path), true); if (!is_array($data)) throw new RuntimeException('AQL dataset is invalid.');
        foreach (array('dataset_id','standard','edition','sampling','inspection','level','lot_ranges','plans') as $field) if (!isset($data[$field])) throw new RuntimeException('AQL dataset contract is incomplete.');
        if ((string) $data['standard'] !== 'ISO 2859-1' || (string) $data['edition'] !== '2026' || (string) $data['sampling'] !== 'single' || (string) $data['inspection'] !== 'normal' || (string) $data['level'] !== 'II') throw new RuntimeException('AQL dataset scope is not supported.');
        $this->dataset = $data; $this->datasetId = (string) $data['dataset_id']; $this->datasetHash = $actual;
    }

    public function calculate($lotSize, array $aqlByClass, $criticalZeroTolerance = false)
    {
        $lotSize = (int) $lotSize; if ($lotSize <= 0) throw new RuntimeException('AQL lot size must be positive.');
        if ($criticalZeroTolerance && !array_key_exists('critical', $aqlByClass)) $aqlByClass = array('critical'=>0) + $aqlByClass;
        if (!$aqlByClass) throw new RuntimeException('At least one AQL defect class is required.');
        $code = null;
        foreach ($this->dataset['lot_ranges'] as $range) {
            if (!isset($range['min'],$range['max'],$range['code'])) throw new RuntimeException('AQL lot range is invalid.');
            if ($lotSize >= (int) $range['min'] && $lotSize <= (int) $range['max']) { $code = (string) $range['code']; break; }
        }
        if ($code === null) throw new RuntimeException('AQL lot size is outside the validated dataset.');
        $results = array();
        foreach ($aqlByClass as $class => $aql) {
            if (!in_array($class, array('critical','major','minor'), true)) throw new RuntimeException('Unsupported AQL defect class.');
            if ($class === 'critical' && $criticalZeroTolerance) { $results[$class] = array('aql'=>'zero_tolerance','code_letter'=>$code,'sample_size'=>$lotSize,'accept'=>0,'reject'=>1,'inspect_all'=>true); continue; }
            $aqlKey = rtrim(rtrim(number_format((float) $aql, 3, '.', ''), '0'), '.');
            $planCode = $code; $guard = 0;
            while (true) {
                if (!isset($this->dataset['plans'][$planCode][$aqlKey])) throw new RuntimeException('AQL value is not present in the validated dataset.');
                $plan = $this->dataset['plans'][$planCode][$aqlKey];
                if (!empty($plan['redirect_code'])) { $planCode = (string) $plan['redirect_code']; if (++$guard > 20) throw new RuntimeException('AQL table redirect loop.'); continue; }
                foreach (array('sample_size','accept','reject') as $field) if (!isset($plan[$field])) throw new RuntimeException('AQL plan is incomplete.');
                $sample = min($lotSize, (int) $plan['sample_size']); $accept = (int) $plan['accept']; $reject = (int) $plan['reject'];
                if ($sample <= 0 || $accept < 0 || $reject !== $accept + 1) throw new RuntimeException('AQL plan boundaries are invalid.');
                $results[$class] = array('aql'=>$aqlKey,'code_letter'=>$planCode,'sample_size'=>$sample,'accept'=>$accept,'reject'=>$reject,'inspect_all'=>$sample === $lotSize && (int) $plan['sample_size'] > $lotSize); break;
            }
        }
        return array('standard'=>'ISO 2859-1','edition'=>'2026','sampling'=>'single','inspection'=>'normal','level'=>'II','dataset_id'=>$this->datasetId,'dataset_sha256'=>$this->datasetHash,'lot_size'=>$lotSize,'classes'=>$results);
    }
}
