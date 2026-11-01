<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseStaffAccess.php';

final class OfficeExpenseStaffController
{
    private OfficeExpenseStaffAccess $access;private string $token;
    public function __construct(OfficeExpenseStaffAccess $access,string $token){$this->access=$access;$this->token=$token;}
    public function handle(string $method,array $query,array $post,array $files=[]): array
    {
        $state=['status'=>200,'error'=>null,'redirect'=>null,'offices'=>[],'office'=>'','directory'=>[],'members'=>[],'selected'=>null,'language'=>($query['lang']??$post['lang']??'ar')==='en'?'en':'ar'];
        try{
            $offices=$this->access->managedOffices();if(!$offices)throw new DomainException('No managed branch.');
            $office=$post['office']??$query['office']??array_key_first($offices);
            if(!is_string($office)||!isset($offices[$office]))throw new DomainException('Branch unavailable.');
            if(!in_array($method,['GET','POST'],true))return array_replace($state,['status'=>405,'error'=>'method']);
            if($method==='POST'){
                if($files||$this->token===''||!is_string($post['token']??null)||!hash_equals($this->token,$post['token']))return array_replace($state,['status'=>403,'error'=>'csrf']);
                $allowed=['token','action','office','staff_id','revision','permissions','record_scope','lang'];
                if(array_diff(array_keys($post),$allowed)||!in_array($post['action']??null,['assign','revoke'],true))throw new InvalidArgumentException('Invalid assignment fields.');
                $id=self::number($post['staff_id']??null,false);$revision=self::number($post['revision']??null,true);
                if($post['action']==='assign'){
                    $permissions=$post['permissions']??null;if(!is_array($permissions)||array_diff(array_keys($permissions),array_keys(OfficeExpenseStaffAccess::ACTIONS)))throw new InvalidArgumentException('Invalid permissions.');
                    $grants=[];foreach($permissions as$section=>$level){if(!in_array($level,['none','read','create'],true))throw new InvalidArgumentException('Invalid level.');if($level!=='none')$grants[$section]=$level==='create'?['read','create']:['read'];}
                    if(!is_string($post['record_scope']??null))throw new InvalidArgumentException('Invalid scope.');
                    $this->access->assign($id,$office,$grants,$post['record_scope'],$revision);
                }else{
                    if(isset($post['permissions'])||isset($post['record_scope']))throw new InvalidArgumentException('Unexpected revoke fields.');
                    $this->access->revoke($id,$office,$revision);
                }
                return array_replace($state,['status'=>303,'redirect'=>'users.php?lang='.$state['language'].'&office='.$office.'&saved=1']);
            }
            $state['offices']=$offices;$state['office']=$office;$state['directory']=array_values(array_filter($this->access->directory(),fn($row)=>$this->access->mayManage($row['id'],$office)));$state['members']=$this->access->members($office);
            foreach($state['members']as&$member)$member['manageable']=$this->access->mayManage($member['user_id'],$office);unset($member);
            if(isset($query['staff_id'])){$id=self::number($query['staff_id'],false);if(!$this->access->mayManage($id,$office))throw new DomainException('Staff unavailable.');foreach($state['directory']as$row)if($row['id']===$id)$state['selected']=$row;if($state['selected']===null)throw new DomainException('Staff unavailable.');}
            return $state;
        }catch(InvalidArgumentException $error){return array_replace($state,['status'=>422,'error'=>'invalid','directory'=>[],'members'=>[]]);}
        catch(DomainException $error){return array_replace($state,['status'=>403,'error'=>'forbidden','directory'=>[],'members'=>[]]);}
        catch(Throwable $error){return array_replace($state,['status'=>503,'error'=>'unavailable','directory'=>[],'members'=>[]]);}
    }
    private static function number($value,bool $zero): int
    {
        if(!is_string($value)||!preg_match($zero?'/^(0|[1-9][0-9]{0,8})$/D':'/^[1-9][0-9]{0,8}$/D',$value))throw new InvalidArgumentException('Invalid identity or revision.');return (int)$value;
    }
}
