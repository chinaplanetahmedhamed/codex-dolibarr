#!/usr/bin/env python3
"""Explicit fresh socket-only native financial fixture. Never uses installed config/data."""
import argparse, hashlib, json, os, subprocess, tempfile, time
from pathlib import Path

HERE=Path(__file__).resolve().parent
SOURCE=HERE.parents[2]
CORE=Path('/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-24.0.1-m118/htdocs')
SCHEMA_SOURCE=SOURCE
OUT=SOURCE/'outputs/M121/local-assembly-2026-11-03/native-lifecycle'
MYSQL=Path('/Applications/MAMP/Library/bin/mysql')
MYSQLD=Path('/Applications/MAMP/Library/bin/mysqld')

def main():
    OUT.mkdir(parents=True,exist_ok=True); os.chmod(OUT,0o700)
    fixture=Path(tempfile.mkdtemp(prefix='m121-native-finance-',dir='/tmp')); os.chmod(fixture,0o700)
    data=fixture/'data'; data.mkdir(mode=0o700)
    overlay=fixture/'htdocs'; overlay.mkdir(mode=0o700)
    for item in CORE.iterdir():
        if item.name not in ('conf','custom'): (overlay/item.name).symlink_to(item,target_is_directory=item.is_dir())
    (overlay/'conf').mkdir(mode=0o700); (overlay/'custom').mkdir(mode=0o700)
    for name in ('chinaplanetcustomercenter','chinaplanetoperations','chinaplanetlogistics'):
        (overlay/'custom'/name).symlink_to(SOURCE/'custom'/name,target_is_directory=True)
    (fixture/'documents').mkdir(mode=0o700)
    settings={'fixture':str(fixture),'source':str(SOURCE),'core':str(CORE),'overlay':str(overlay),'schema_source':str(SCHEMA_SOURCE),'database':'m121_native_finance_synthetic','output':str(OUT)}
    (fixture/'fixture.json').write_text(json.dumps(settings)); os.chmod(fixture/'fixture.json',0o600)
    process=None; started=time.time(); result={'status':'failed','scope':'disposable native fixture only'}
    try:
        subprocess.run([str(MYSQLD),'--no-defaults','--initialize-insecure','--basedir=/Applications/MAMP/Library','--datadir='+str(data),'--log-error='+str(fixture/'initialize.log')],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE,timeout=45)
        process=subprocess.Popen([str(MYSQLD),'--no-defaults','--basedir=/Applications/MAMP/Library','--datadir='+str(data),'--socket='+str(fixture/'mysql.sock'),'--pid-file='+str(fixture/'mysql.pid'),'--log-error='+str(fixture/'server.log'),'--skip-networking'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        for _ in range(100):
            if process.poll() is not None: raise RuntimeError('Owned fixture server exited before readiness')
            if (fixture/'mysql.sock').exists(): break
            time.sleep(.1)
        else: raise RuntimeError('Owned fixture socket unavailable')
        cli=[str(MYSQL),'--no-defaults','--protocol=socket','--socket='+str(fixture/'mysql.sock'),'-u','root','--batch','--skip-column-names']
        identity=subprocess.run(cli+['-e','SELECT @@skip_networking,@@socket,@@datadir'],check=True,capture_output=True,text=True,timeout=10).stdout.strip().split('\t')
        if len(identity)!=3 or identity[0]!='1' or identity[1]!=str(fixture/'mysql.sock') or Path(identity[2]).resolve()!=data.resolve(): raise RuntimeError('Fixture identity mismatch: no schema writes allowed')
        subprocess.run(cli+['-e','CREATE DATABASE m121_native_finance_synthetic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE,timeout=10)
        print('CHECKPOINT: fresh private MySQL socket identity verified; native bootstrap beginning',flush=True)
        command=['/opt/homebrew/bin/php','-d','mysqli.default_socket='+str(fixture/'mysql.sock'),str(HERE/'scenario.php'),str(fixture/'fixture.json')]
        run=subprocess.run(command,capture_output=True,text=True,timeout=150)
        (OUT/'native-run.log').write_text(run.stdout+run.stderr); os.chmod(OUT/'native-run.log',0o600)
        print((run.stdout+run.stderr)[-3500:],flush=True)
        if run.returncode: raise RuntimeError('Native scenario failed; bounded diagnostic retained in native-run.log')
        result={'status':'passed','scope':'actual native payment/bank methods in disposable fixture','scenario':json.loads((OUT/'result.json').read_text())}
    except Exception as exc:
        result['error']=str(exc); print(str(exc),flush=True)
    finally:
        if process and process.poll() is None:
            process.terminate()
            try: process.wait(timeout=15)
            except subprocess.TimeoutExpired: process.kill(); process.wait(timeout=5)
        result.update(elapsed_seconds=round(time.time()-started,3),owned_pid=None if process is None else process.pid,owned_returncode=None if process is None else process.returncode,socket_and_pid_absent=not (fixture/'mysql.sock').exists() and not (fixture/'mysql.pid').exists(),fixture=str(fixture))
        (OUT/'lifecycle.json').write_text(json.dumps(result,indent=2)); os.chmod(OUT/'lifecycle.json',0o600)
        print('Owned server stopped; socket/PID absent:',result['socket_and_pid_absent'],flush=True)
    return 0 if result['status']=='passed' and result['socket_and_pid_absent'] else 1
if __name__=='__main__': raise SystemExit(main())
