#!/usr/bin/env python3
"""Start isolated local development services; never touch other projects."""
from pathlib import Path
import subprocess,os,sys,time,json,urllib.request,signal,shutil
root=Path(__file__).resolve().parents[1];backend=root/'backend';runtime=root/'outputs/runtime';runtime.mkdir(parents=True,exist_ok=True)
php=shutil.which('php')
if not php:raise SystemExit('PHP was not found')
commands={'api':[php,str(backend/'artisan'),'serve','--host=127.0.0.1','--port=8088'],'queue':[php,str(backend/'artisan'),'queue:listen','--sleep=1','--tries=3'],'scheduler':[php,str(backend/'artisan'),'schedule:work']}
def owned_pid(role):
 try:
  pid=int((runtime/(role+'.pid')).read_text().strip());os.kill(pid,0)
  command=subprocess.check_output(['ps','-p',str(pid),'-o','command='],text=True)
  if str(backend/'artisan') in command:return pid
 except (OSError,ValueError,subprocess.CalledProcessError):pass
 return None
def healthy():
 try:
  with urllib.request.urlopen('http://127.0.0.1:8088/api/v1/health',timeout=2) as r:return json.load(r).get('service')=='CareCircle'
 except Exception:return False
mode=sys.argv[1] if len(sys.argv)>1 else 'start'
if mode=='stop':
 for role in commands:
  pid=owned_pid(role)
  if pid:
   if os.getpgid(pid)==pid:os.killpg(pid,signal.SIGTERM)
   else:os.kill(pid,signal.SIGTERM)
   print('Stopped',role)
 sys.exit(0)
if mode not in ['start','status']:raise SystemExit('Usage: local-services.py start|stop|status')
for role,command in commands.items():
 pid=owned_pid(role)
 if role=='api' and healthy():continue
 if not pid and mode=='start':
  with (runtime/(role+'.log')).open('ab') as log:
   process=subprocess.Popen(command,cwd=backend,stdin=subprocess.DEVNULL,stdout=log,stderr=subprocess.STDOUT,start_new_session=True,close_fds=True)
  (runtime/(role+'.pid')).write_text(str(process.pid))
for _ in range(40):
 if healthy():break
 time.sleep(.25)
else:raise SystemExit('CareCircle API did not respond. Inspect outputs/runtime/api.log')
for role in commands:
 pid=owned_pid(role)
 if not pid:raise SystemExit('CareCircle '+role+' is not running; inspect its runtime log')
 print(role+': running (PID '+str(pid)+')')
print('Admin: http://127.0.0.1:8088/admin')
