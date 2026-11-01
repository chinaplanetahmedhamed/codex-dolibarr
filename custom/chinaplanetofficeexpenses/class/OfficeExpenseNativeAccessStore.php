<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseNativeConnection.php';

/** Module membership only. Native user accounts and financial records are never modified. */
final class OfficeExpenseNativeAccessStore
{
    private OfficeExpenseNativeConnection $connection;
    public function __construct($db)
    {
        if(!defined('MAIN_DB_PREFIX')||!preg_match('/^[A-Za-z0-9_]{1,40}$/D',MAIN_DB_PREFIX))throw new InvalidArgumentException('Invalid native prefix.');
        $this->connection=new OfficeExpenseNativeConnection($db);
    }
    public function find(int $entity,int $user,string $office): ?array
    {
        $this->scope($entity,$user,$office);
        $row=$this->connection->one('SELECT entity,fk_user,office,active,grants_json,record_scope,revision FROM '.MAIN_DB_PREFIX.'cpo_oe_membership WHERE entity='.$entity.' AND fk_user='.$user.' AND office='.$this->connection->quote($office));
        if($row===null)return null;
        $grants=json_decode($row['grants_json'],true,16,JSON_THROW_ON_ERROR);
        return ['entity'=>(int)$row['entity'],'user_id'=>(int)$row['fk_user'],'office'=>$row['office'],'active'=>(int)$row['active']===1,'grants'=>$grants,'record_scope'=>$row['record_scope'],'revision'=>(int)$row['revision']];
    }
    public function members(int $entity,string $office): array
    {
        $this->scope($entity,1,$office);$rows=$this->connection->rows('SELECT fk_user FROM '.MAIN_DB_PREFIX.'cpo_oe_membership WHERE entity='.$entity.' AND office='.$this->connection->quote($office).' ORDER BY fk_user');
        return array_map(fn($row)=>$this->find($entity,(int)$row['fk_user'],$office),$rows);
    }
    public function replace(int $entity,int $actor,int $target,string $office,array $grants,string $scope,int $expected,bool $active,callable $authorize): array
    {
        $this->scope($entity,$target,$office);if($actor<1||$expected<0)throw new InvalidArgumentException('Invalid membership version.');
        $c=$this->connection;$c->beginOwned();
        try{
            // Recheck authorization in the same owned transaction; never inherit caller transactions.
            $authorize();$old=$this->find($entity,$target,$office);
            if(($old['revision']??0)!==$expected)throw new DomainException('Membership changed; refresh and retry.');
            $next=['entity'=>$entity,'user_id'=>$target,'office'=>$office,'active'=>$active,'grants'=>$grants,'record_scope'=>$scope,'revision'=>$expected+1];
            $q=fn($v)=>$c->quote($v);$table=MAIN_DB_PREFIX.'cpo_oe_membership';$date=$q(gmdate('Y-m-d H:i:s'));
            $where='entity='.$entity.' AND fk_user='.$target.' AND office='.$q($office);
            $json=json_encode($grants,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            if($old===null)$c->query('INSERT INTO '.$table.'(entity,fk_user,office,active,grants_json,record_scope,revision,date_modification) VALUES('.$entity.','.$target.','.$q($office).','.($active?1:0).','.$q($json).','.$q($scope).',1,'.$date.')');
            else $c->query('UPDATE '.$table.' SET active='.($active?1:0).',grants_json='.$q($json).',record_scope='.$q($scope).',revision='.($expected+1).',date_modification='.$date.' WHERE '.$where.' AND revision='.$expected);
            // The unique audit version also rejects a concurrent stale writer with identical grants.
            $c->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_oe_access_audit(entity,fk_actor,fk_user,office,revision,before_json,after_json,date_creation) VALUES('.$entity.','.$actor.','.$target.','.$q($office).','.($expected+1).','.$q(json_encode($old,JSON_THROW_ON_ERROR)).','.$q(json_encode($next,JSON_THROW_ON_ERROR)).','.$date.')');
            if($this->find($entity,$target,$office)!==$next)throw new RuntimeException('Membership readback differs.');
            $audit=$c->one('SELECT fk_actor,before_json,after_json FROM '.MAIN_DB_PREFIX.'cpo_oe_access_audit WHERE '.$where.' AND revision='.($expected+1));
            if(!$audit||(int)$audit['fk_actor']!==$actor||json_decode($audit['before_json'],true,16,JSON_THROW_ON_ERROR)!==$old||json_decode($audit['after_json'],true,16,JSON_THROW_ON_ERROR)!==$next)throw new RuntimeException('Membership audit readback differs.');
            $c->commitOwned();return $next;
        }catch(Throwable $error){$c->rollbackOwnedOnFailure($error);throw $error;}
    }
    private function scope(int $entity,int $user,string $office): void
    {
        if($entity<1||$user<1||!in_array($office,['egypt','china'],true))throw new InvalidArgumentException('Invalid branch membership scope.');
    }
}
