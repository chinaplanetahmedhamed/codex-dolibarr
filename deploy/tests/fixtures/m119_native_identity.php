<?php
declare(strict_types=1);
// Included only after the owned synthetic native bootstrap. Never load real configuration.
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

function m119IdentityDatabase(bool $withCredentials=false,string $currency='EGP',int $entity=1): array
{
    global $db,$conf;
    $db=m119NativeDatabase($currency,$entity);
    $conf->modules_parts['login']=array();
    $conf->modules=array('expensereport'=>1);
    $conf->file=(object)array('dol_document_root'=>array('main'=>DOL_DOCUMENT_ROOT));
    // User::fetch uses this MySQL function; only the disposable SQLite connection is adapted.
    $db->db->createFunction('GREATEST',static function(...$values) {
        return in_array(null,$values,true) ? null : max($values);
    },-1);
    foreach (array('user','user_extrafields','extrafields','c_country','c_departements','establishment',
        'user_rights','rights_def','usergroup_rights','usergroup_user','events') as $table) {
        $sql=file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_'.$table.'.sql');
        $sql=preg_replace('/--[^\n]*/','',$sql);
        $sql=str_ireplace('ON UPDATE CURRENT_TIMESTAMP','',$sql);
        if (!$db->query(trim($sql))) throw new RuntimeException('Synthetic identity schema failed: '.$table);
    }
    // Permission/currency/workflow probes require no credentials or authenticated session.
    // Only the explicitly selected historical password/session tests opt into this branch.
    $secret=null;$passwordSql='NULL';
    if ($withCredentials) {
        $conf->global->MAIN_SECURITY_HASH_ALGO='password_hash';
        $secret=bin2hex(random_bytes(24));
        $passwordSql="'".$db->escape(password_hash($secret,PASSWORD_BCRYPT))."'";
    }
    foreach (array(7=>array(1,1,0),8=>array(1,1,0),9=>array(1,0,0),10=>array(1,1,99),11=>array(2,1,0)) as $id=>$fields) {
        $db->query("INSERT INTO llx_user(rowid,entity,login,lastname,statut,fk_soc,pass_crypted) VALUES(".$id.','.$fields[0].",'synthetic-".$id."','SYNTHETIC',".$fields[1].','.$fields[2].','.$passwordSql.')');
    }
    $db->query("INSERT INTO llx_rights_def(id,entity,module,perms) VALUES(90001,1,'expensereport','creer'),(90001,2,'expensereport','creer')");
    $db->query('INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,7,90001),(1,9,90001),(1,10,90001),(2,11,90001)');
    return array($db,$secret);
}

function m119FetchIdentity(string $login,int $entity=1): User
{
    global $db;
    $identity=new User($db);
    if ($identity->fetch(0,$login,'',0,$entity) !== 1) throw new DomainException('Synthetic native identity unavailable.');
    $identity->loadRights('expensereport');
    return $identity;
}
