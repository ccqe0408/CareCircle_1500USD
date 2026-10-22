#!/usr/bin/env python3
from pathlib import Path
import json,subprocess,shutil
root=Path(__file__).resolve().parents[1];project=root/'videos/carecircle-demo';assets=project/'assets';(assets/'raw').mkdir(parents=True,exist_ok=True);(assets/'screens').mkdir(exist_ok=True);(assets/'vendor').mkdir(exist_ok=True)
for name in ['desktop-review','desktop-exception']:
 folder=root/'outputs/raw'/(name+'-frames');frames=json.loads((folder/'frames.json').read_text());lines=[]
 for i,f in enumerate(frames):
  duration=(frames[i+1]['timestamp']-f['timestamp']) if i+1<len(frames) else 2
  lines += ["file '"+str((folder/f['file']).resolve())+"'",'duration '+str(max(.06,min(1,duration)))]
 lines += ["file '"+str((folder/frames[-1]['file']).resolve())+"'"]
 concat=folder/'concat.txt';concat.write_text('\n'.join(lines))
 subprocess.run(['ffmpeg','-y','-loglevel','error','-f','concat','-safe','0','-i',str(concat),'-vf','setsar=1','-r','30','-c:v','libx264','-pix_fmt','yuv420p',str(assets/'raw'/(name+'.mp4'))],check=True)
 print(name,subprocess.check_output(['ffprobe','-v','error','-show_entries','format=duration','-of','csv=p=0',str(assets/'raw'/(name+'.mp4'))],text=True).strip())
shutil.copy2(root/'outputs/raw/carecircle-native-flow.mp4',assets/'raw/native-flow.mp4')
for filename in ['01-family-home.png','05-recipient-handover-code.png','06-wrong-code-rejected.png','08-family-persisted-completion.png','admin-approved-desktop.png','admin-no-volunteer-desktop.png','admin-stats-desktop.png']:
 shutil.copy2(root/'outputs/screenshots'/filename,assets/'screens'/filename)
shutil.copy2(root/'backend/node_modules/gsap/dist/gsap.min.js',assets/'vendor/gsap.min.js')
(assets/'vendor/GSAP-LICENSE.txt').write_text("GSAP: Standard 'no charge' license. https://gsap.com/standard-license/\nExact installed dependency recorded in backend/package-lock.json.\n")
