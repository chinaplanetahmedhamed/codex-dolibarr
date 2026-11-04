<?php
/** Read-only summaries. Finance and hierarchy authorization stay in canonical statement(). */
class CustomerAccountSummary
{
    private $service;
    public function __construct(CustomerAccountService $service){$this->service=$service;}
    private static function position($minor){return ['balance_minor'=>$minor,'due_minor'=>bccomp($minor,'0',0)<0?bcsub('0',$minor,0):'0'];}
    public function one($customer,$project=0,$task=0){
        $s=$this->service->statement($customer,$project,$task);return self::fromStatement($s);
    }
    public static function fromStatement(array $s){$out=self::position($s['balance_minor']);
        $out+=['base_currency'=>$s['base_currency'],'received_minor'=>$s['received_minor'],'spent_minor'=>$s['spent_minor'],'unallocated_minor'=>$s['unallocated_minor'],'currencies'=>[]];
        foreach($s['original_balances'] as $currency=>$minor)$out['currencies'][$currency]=self::position($minor);
        return $out;
    }
    public function batch(array $scopes){
        if(count($scopes)>20)throw new InvalidArgumentException('At most 20 visible account scopes.');
        $out=[];$cache=[];
        foreach($scopes as $scope){
            if(!is_array($scope)||array_diff(array_keys($scope),['customer','project','task']))throw new InvalidArgumentException('Invalid scope.');
            foreach(['customer','project','task'] as $k){$v=$scope[$k]??0;if(!is_int($v)||$v<0||$v>2147483647)throw new InvalidArgumentException('Invalid scope ID.');$scope[$k]=$v;}
            if(!$scope['customer']||($scope['task']&&!$scope['project']))throw new InvalidArgumentException('Invalid account hierarchy.');
            $key=implode(':',[$scope['customer'],$scope['project'],$scope['task']]);
            if(!array_key_exists($key,$cache)){try{$cache[$key]=['available'=>true,'totals'=>$this->one($scope['customer'],$scope['project'],$scope['task'])];}catch(RuntimeException $e){if($e->getCode()!==403)throw $e;$cache[$key]=['available'=>false];}}
            $out[]=['scope'=>$scope]+$cache[$key];
        }
        return $out;
    }
}
