#!/usr/bin/env python3
"""Fixed staging metadata/native-context probe; no writes, secret output or deployment."""
import json
import os
import subprocess

BASE = "sha256:56e459f0d2e7be2c9d809015b922624234f2899cc9f36e74fab1c5254913e482"
APP = "erp-staging-dolibarr-1"
COMPOSE = "/data/compose/2/docker-compose.yml"


def run(args):
    result = subprocess.run(args, capture_output=True, text=True, timeout=30)
    if result.returncode:
        phase = "compose validation" if args[:2] == ["docker", "compose"] else ("native context" if args[:2] == ["docker", "exec"] else "container metadata")
        signature = next((s for s in ("Access denied", "Unknown database", "Permission denied", "Class \"User\" not found", "Call to undefined function", "config file content seems to be not correctly defined") if s in result.stdout + result.stderr), "details withheld")
        raise RuntimeError(phase + " exited " + str(result.returncode) + ": " + signature)
    return result.stdout


def selected(name):
    fields = '{"name":{{json .Name}},"running":{{json .State.Running}},"image":{{json .Image}},"project":{{json (index .Config.Labels "com.docker.compose.project")}},"service":{{json (index .Config.Labels "com.docker.compose.service")}},"ports":{{json .NetworkSettings.Ports}},"mounts":{{json .Mounts}}}'
    return json.loads(run(["docker", "inspect", "--format", fields, name]))


def main():
    if os.geteuid() != 0 or os.uname().nodename != "srv1011043":
        raise RuntimeError("fixed administrative host identity differs")
    app, database, mail = (selected(n) for n in (APP, "erp-staging-mariadb-1", "erp-staging-mailpit-1"))
    for item in (app, database, mail):
        if not item["running"] or item["project"] != "erp-staging":
            raise RuntimeError("fixed staging resource identity differs")
    if app["image"] != BASE or app["service"] != "dolibarr" or app["ports"] != {"80/tcp": [{"HostIp": "127.0.0.1", "HostPort": "18088"}]}:
        raise RuntimeError("fixed staging application image/service/port differs")
    if any(database["ports"].values()) or mail["ports"].get("1025/tcp") != [{"HostIp": "127.0.0.1", "HostPort": "11025"}] or mail["ports"].get("8025/tcp") != [{"HostIp": "127.0.0.1", "HostPort": "18025"}]:
        raise RuntimeError("fixed staging database/mail exposure differs")
    mounts = {m["Destination"]: m for m in app["mounts"]}
    for target, volume in (("/var/www/html/custom", "erp-staging_erp-staging_custom"), ("/var/www/documents", "erp-staging_erp-staging_docs")):
        m = mounts.get(target, {})
        if m.get("Type") != "volume" or m.get("Name") != volume or m.get("Source") != "/var/lib/docker/volumes/" + volume + "/_data" or m.get("RW") is not True:
            raise RuntimeError("fixed staging mount differs")
    php = r'''
ob_start();
require '/var/www/html/master.inc.php';
$u=new User($db);if($u->fetch(1)!==1){exit(2);}$u->loadRights('expensereport');
$tables=array();foreach(array('cpo_oe_schema','cpo_oe_draft','cpo_oe_draft_audit','cpo_oe_receipt','cpo_oe_receipt_audit')as$t){
 $r=$db->query("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$db->escape(MAIN_DB_PREFIX.$t)."'");if(!$r){exit(3);}$tables[$t]=(int)$db->fetch_object($r)->n;
}
$out=array('dolibarr'=>DOL_VERSION,'php'=>PHP_VERSION,'bcmath'=>extension_loaded('bcmath'),'gd'=>extension_loaded('gd'),'driver'=>$db->type,'entity'=>$conf->entity,'base_currency'=>$conf->currency,'native_total_precision'=>$conf->global->MAIN_MAX_DECIMALS_TOT??null,'expense_module'=>!empty($conf->expensereport->enabled),'pilot_id'=>(int)$u->id,'pilot_admin'=>!empty($u->admin),'pilot_active'=>(int)$u->status===1,'pilot_internal'=>empty($u->socid),'pilot_valid'=>!$u->isNotIntoValidityDateRange(),'pilot_native_right'=>$u->hasRight('expensereport','creer'),'owned_table_presence'=>$tables);
ob_end_clean();echo json_encode($out,JSON_THROW_ON_ERROR);
'''
    native = json.loads(run(["docker", "exec", APP, "php", "-r", php]))
    if native["dolibarr"] != "24.0.1" or native["php"] != "8.2.32" or native["driver"] != "mysqli" or native["pilot_id"] != 1 or not all(native[k] for k in ("gd", "expense_module", "pilot_admin", "pilot_active", "pilot_internal", "pilot_valid", "pilot_native_right")):
        raise RuntimeError("native staging pilot prerequisites differ")
    if any(native["owned_table_presence"].values()):
        raise RuntimeError("owned staging schema already exists; manual reconciliation required")
    # Parse only in server memory; never output environment, command or configuration contents.
    try:
        compose = json.loads(run(["docker", "compose", "-p", "erp-staging", "-f", COMPOSE, "config", "--format", "json"]))
    except Exception as error:
        print(json.dumps({"result": "M119_CONTEXT_ONLY_RELEASE_BLOCKED", "native": native, "compose_file_present_on_host": os.path.isfile(COMPOSE), "compose_error": str(error) if isinstance(error, RuntimeError) else type(error).__name__}))
        return 1
    desired = compose.get("services", {}).get("dolibarr", {})
    if not isinstance(desired, dict) or not desired or not isinstance(desired.get("environment"), dict) or not desired["environment"]:
        raise RuntimeError("required compose service/environment definition is absent")
    actual_env = json.loads(run(["docker", "inspect", "--format", "{{json .Config.Env}}", APP]))
    actual_env = dict(v.split("=", 1) for v in actual_env)
    expected_env = desired.get("environment", {})
    env_matches = all(str(value) == actual_env.get(key) for key, value in expected_env.items())
    if desired.get("image") != "local/dolibarr:24.0.1" or not env_matches:
        raise RuntimeError("compose image/environment differs from the verified running service")
    print(json.dumps({"result": "M119_READONLY_CONTEXT_VERIFIED", "application_image": app["image"], "mounts": {p: {k: mounts[p][k] for k in ("Type", "Name", "Source", "RW")} for p in ("/var/www/html/custom", "/var/www/documents")}, "native": native, "compose": {"file": COMPOSE, "service": "dolibarr", "image": desired.get("image"), "image_matches": desired.get("image") == "local/dolibarr:24.0.1", "environment_matches": env_matches}}, indent=2))


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(json.dumps({"result": "M119_READONLY_PREFLIGHT_STOP", "reason": str(error) if isinstance(error, RuntimeError) else type(error).__name__}))
        raise SystemExit(1)
