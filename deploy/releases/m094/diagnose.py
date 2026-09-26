#!/usr/bin/python3 -I
"""Read-only targeted M093 preflight and M094 upload/member diagnostics."""
import pathlib,hashlib,subprocess,json,inspect,re
p=pathlib.Path('/home/codex-deploy/staging-inbox/m093-deploy.py');b=p.read_bytes()
assert hashlib.sha256(b).hexdigest()=='fa485f394e27545b785f9cf5bd3d59cc43132b5bd670961270a1a46aacb953dc'
n={'__name__':'m093_diagnostic'};exec(compile(b,str(p),'exec'),n)
out={};orig=n['command']
def command(argv,timeout=90,data=None):
 r=subprocess.run(argv,input=data,stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=timeout,env={'PATH':'/usr/sbin:/usr/bin:/sbin:/bin'})
 if r.returncode:out['failed_command']={'stage':inspect.stack()[1].function,'program':argv[:5],'returncode':r.returncode};raise RuntimeError('DIAGNOSTIC_COMMAND_FAILED')
 return r.stdout
n['command']=command
try:
 manifest,payload=n['load_payload']();web,db,root=n['target']('production');n['compare'](root,manifest['prerequisites']);n['compare'](root,manifest['before']['production'])
 for name,data in payload.items():
  if name.endswith('.php'):command(['docker','exec','-i',web,'php','-l'],data=data)
 n['readiness'](web);n['health'](web);out['m093_preflight']='PASS'
except Exception as e:out['m093_preflight']=str(e)
code=n['BOOT']+r'''
$out=array('php'=>array('sapi'=>PHP_SAPI));foreach(array('upload_max_filesize','post_max_size','memory_limit','max_execution_time','max_input_time','max_file_uploads') as $k)$out['php'][$k]=ini_get($k);
$out['members']=array();$q="SELECT g.rowid team_id,g.nom team_name,g.entity team_entity,gu.entity membership_entity,u.rowid user_id,u.login,u.entity user_entity,u.statut,u.admin,COALESCE(u.fk_soc,0) external FROM ".MAIN_DB_PREFIX."usergroup g JOIN ".MAIN_DB_PREFIX."usergroup_user gu ON gu.fk_usergroup=g.rowid JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=gu.fk_user WHERE g.entity=1 AND g.nom='Egypt Operation' ORDER BY u.rowid";
$r=$db->query($q);if(!$r)exit(41);while($a=$db->fetch_object($r))$out['members'][]=$a;echo json_encode($out);'''
try:out['runtime']=json.loads(n['php']('vision_erp',code))
except Exception as e:out['runtime_error']=str(e)
try:
 out['apache_modules']=command(['docker','exec','vision_erp','apache2ctl','-M']).decode().splitlines()
 out['php_limit_overrides']=command(['docker','exec','vision_erp','sh','-c',"grep -R -E 'upload_max_filesize|post_max_size|memory_limit|LimitRequestBody|RequestReadTimeout' /etc/apache2 /usr/local/etc/php/conf.d 2>/dev/null || true"]).decode().splitlines()
except Exception as e:out['configuration_error']=str(e)
try:
 cfg=command(['docker','exec','management-nginx-proxy-manager-1','nginx','-T']).decode()
 out['proxy_limits']=[x.strip() for x in cfg.splitlines() if re.search(r'^\s*(client_max_body_size|client_body_timeout|proxy_read_timeout|proxy_send_timeout)\s',x)]
except Exception as e:out['proxy_error']=str(e)
try:
 code=n['BOOT']+r"""$r=$db->query('SELECT rowid,status,fk_user_assign,fk_usergroup,date_archived FROM '.MAIN_DB_PREFIX.'cpo_task WHERE entity=1 AND rowid=62');echo json_encode($db->fetch_object($r));"""
 out['m093_task62']=json.loads(n['php']('vision_erp',code))
 out['apache_scope']=command(['docker','exec','vision_erp','sh','-c',"grep -R -E 'AllowOverride|<Directory|LimitRequestBody' /etc/apache2/apache2.conf /etc/apache2/sites-enabled /etc/apache2/conf-enabled 2>/dev/null || true"]).decode().splitlines()
 out['task_htaccess']=command(['docker','exec','vision_erp','sh','-c',"for p in /var/www/html/.htaccess /var/www/html/custom/.htaccess /var/www/html/custom/chinaplanetoperations/.htaccess /var/www/html/custom/chinaplanetoperations/tasks/.htaccess; do if test -f \"$p\"; then echo \"$p\"; cat \"$p\"; fi; done"]).decode().splitlines()
 out['erp_proxy_config']=[section for section in re.split(r'(?m)^# configuration file ',cfg) if re.search(r'server_name\s+erp\.visiontrading\.biz\s*;',section)]
except Exception as e:out['detail_error']=str(e)
p=pathlib.Path('/home/codex-deploy/staging-inbox/m094-diagnostic.json');assert not p.is_symlink();p.write_text(json.dumps(out,indent=2));p.chmod(0o644)
print('M094_DIAGNOSTIC_RECORDED')
