#!/usr/bin/env python3
"""Run real Flutter integration flow and save host-side emulator evidence."""
from pathlib import Path
import subprocess,re,sys,time,json
root=Path(__file__).resolve().parents[1]
out=root/'outputs';shots=out/'screenshots';shots.mkdir(parents=True,exist_ok=True)
subprocess.run(['php',str(root/'scripts'/'prepare-flow.php')],check=True)
subprocess.run(['adb','-s','emulator-5554','wait-for-device'],check=True,timeout=90)
for _ in range(90):
 if subprocess.check_output(['adb','-s','emulator-5554','shell','getprop','sys.boot_completed'],text=True).strip()=='1':break
 time.sleep(1)
else:raise RuntimeError('Emulator did not complete boot')
command=['flutter','drive','--driver=test_driver/integration_test.dart','--target=integration_test/flow_test.dart','-d','emulator-5554']
record=None
record_start=None
markers=[]
with (out/'flutter-integration.log').open('w') as log:
 p=subprocess.Popen(command,cwd=root/'mobile',stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,bufsize=1)
 for line in p.stdout:
  log.write(line);log.flush()
  m=re.search(r'CARECIRCLE_CAPTURE:([\w-]+)',line)
  if m:
   if record is None:
    record_start=time.monotonic()
    record=subprocess.Popen(['adb','-s','emulator-5554','shell','screenrecord','--bit-rate','6000000','--time-limit','180','/sdcard/carecircle-flow.mp4'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
   markers.append({'name':m.group(1),'source_seconds':round(time.monotonic()-record_start,3)})
   with (shots/(m.group(1)+'.png')).open('wb') as shot:subprocess.run(['adb','-s','emulator-5554','exec-out','screencap','-p'],stdout=shot,check=True)
   print('Captured',m.group(1),flush=True)
  if 'EXCEPTION CAUGHT' in line:
   with (shots/'integration-failure.png').open('wb') as shot:subprocess.run(['adb','-s','emulator-5554','exec-out','screencap','-p'],stdout=shot)
  if any(x in line for x in ['TestFailure','EXCEPTION','All tests passed','Failure Details']):print(line.strip(),flush=True)
 code=p.wait()
 if record is not None:
  subprocess.run(['adb','-s','emulator-5554','shell','pkill','-2','screenrecord'],stdout=subprocess.DEVNULL)
  record.wait(timeout=15)
  (out/'raw').mkdir(exist_ok=True)
  subprocess.run(['adb','-s','emulator-5554','pull','/sdcard/carecircle-flow.mp4',str(out/'raw'/'carecircle-native-flow.mp4')],check=True,stdout=subprocess.DEVNULL)
 (out/'flow-markers.json').write_text(json.dumps({'exit_code':code,'markers':markers},indent=2))
 print('Flutter integration exit:',code);sys.exit(code)
