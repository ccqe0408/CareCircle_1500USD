#!/usr/bin/env python3
"""Assemble genuine local capture and Chinese explanation captions in HyperFrames."""
from pathlib import Path
import json,shutil,html
root=Path(__file__).resolve().parents[1];project=root/'videos/carecircle-demo';assets=project/'assets';comps=project/'compositions/frames';comps.mkdir(parents=True,exist_ok=True)
folder=root/'outputs/raw/desktop-exception-frames';frames=json.loads((folder/'frames.json').read_text());shutil.copy2(folder/frames[-1]['file'],assets/'screens/admin-closed.jpg')
scenes=[
 dict(id='intro',duration=8,title=['CareCircle'],body='社区日常互助，从提出需求到确认交接。',kind='phone',image='01-family-home.png',captions=[(0,8,'以下画面来自真实运行的 Flutter App 与 Laravel 管理后台。')]),
 dict(id='review',duration=14,title=['社区审核','才可接单'],body='管理员核验志愿者，并记录审核依据。',kind='admin',video='desktop-review.mp4',media_duration=7.5,rate=.35,hold='admin-approved-desktop.png',captions=[]),
 dict(id='request',duration=28,title=['家属发起','邻里接手'],body='授权家属代办。系统按服务区域、类别、时间和容量筛选。',kind='phone',video='native-flow.mp4',media_start=0,media_duration=28,captions=[(0,10,'家属填写受助人、领取地点、服务时段，并确认服务范围。'),(10,10,'志愿者收到适合自己的邀请，主动选择是否接受。'),(20,8,'接受后锁定指派。随后更新执行状态，双方查看同一进度。')]),
 dict(id='handover',duration=48.4,title=['收到物品','确认交接'],body='居民核对物品后生成确认码，由当前志愿者输入验证。',kind='phone',video='native-flow.mp4',media_start=28,media_duration=48.4,captions=[(0,12,'家属切回居民界面，核对交接状态并生成一次性确认码。'),(12,12,'志愿者回到同一任务，输入居民提供的确认码。'),(24,10,'错误确认码被服务器拒绝，任务仍未完成。'),(34,10,'输入正确确认码后，任务记录为交接验证完成。'),(44,4.4,'重新登录家属账号，完成状态依然保留。')]),
 dict(id='exceptions',duration=32,title=['异常有人处理','结果分别统计'],body='无人匹配时升级管理员；人工关闭保留原因与操作记录。',kind='admin',video='desktop-exception.mp4',media_duration=9.5,rate=.5,hold='admin-closed.jpg',second_image='admin-stats-desktop.png',captions=[(0,10,'所有候选人都不符合服务区域，系统将请求升级管理员。'),(10,6,'管理员记录处理结果，关闭请求并保留审计。'),(16,16,'后台将交接验证完成、人工关闭和取消分开统计。')]),
 dict(id='closing',duration=12,title=['流程已跑通','验证有记录'],body='Laravel API 与后台、Flutter App 共用持久化数据。',kind='phone',image='08-family-persisted-completion.png',captions=[(0,7,'已验证权限、并发接单、邀请过期、错误码与跨角色完整流程。'),(7,5,'本地与模拟器验证。演示人物及地址均为虚构，尚无真实社区试点。')]),
]
font="@font-face{font-family:CareCN;src:url('assets/fonts/CareCN-subset.ttf') format('truetype');font-weight:100 900;font-display:block;}"
shared=font+'''
*{box-sizing:border-box} .chapter-title{margin:0;display:flex;flex-wrap:wrap;gap:16px;font-weight:850;line-height:1.22;letter-spacing:-2px;color:#153F35}.chapter-title span{display:inline-block;transform:translateY(var(--hf-word-y,0px)) scale(var(--hf-word-scale,1));filter:blur(var(--hf-word-blur,0px));will-change:transform,opacity}
.chapter-body{font-weight:400;line-height:1.65;color:#41594B;margin:24px 0 0}.chapter-count{font-size:24px;letter-spacing:3px;color:#66766D}.explanation{font-size:31px;font-weight:550;line-height:1.65;color:#153F35}.media{position:absolute;object-fit:contain}.phone-media{left:1390px;top:112px;width:405px;height:900px;border-radius:26px;border:2px solid #D9E3D7;background:#F7F8F2}.admin-media{left:150px;top:260px;width:1620px;height:770px;border-radius:12px;background:#101110}.clip{position:absolute} .caption-phone{left:100px;top:760px;width:1120px;height:200px}.caption-admin{left:110px;top:190px;width:1710px;height:54px;font-size:25px;line-height:1.5}
'''
def esc(s):return html.escape(s,quote=True)
def image_tag(id,src,start,duration,kind):return f'<img id="{id}" class="clip media {kind}-media" src="assets/screens/{src}" data-start="{start}" data-duration="{duration}" data-track-index="1" />'
start=0;hosts=[];story=[];srt=[]
for number,scene in enumerate(scenes,1):
 sid='scene-'+scene['id'];duration=scene['duration'];kind=scene['kind'];media=[]
 if scene.get('video'):
  md=scene['media_duration'];media.append(f'<video id="{sid}-video" class="clip media {kind}-media" src="assets/raw/{scene["video"]}" data-start="0" data-duration="{md}" data-media-start="{scene.get("media_start",0)}" data-playback-rate="{scene.get("rate",1)}" data-track-index="1" muted playsinline></video>')
  if scene.get('hold'):
   until=16 if scene.get('second_image') else duration
   media.append(image_tag(sid+'-hold',scene['hold'],md,until-md,kind))
  if scene.get('second_image'):media.append(image_tag(sid+'-stats',scene['second_image'],16,duration-16,kind))
 else:media.append(image_tag(sid+'-image',scene['image'],0,duration,kind))
 captions=[]
 for j,(at,length,text) in enumerate(scene['captions']):
  # Explanations live beside the phone or above the admin capture, never over controls.
  captions.append(f'<div id="{sid}-caption-{j}" class="clip explanation caption-{kind}" data-start="{at}" data-duration="{length}" data-track-index="5">{esc(text)}</div>')
  srt.append((start+at,start+at+length,text))
 title=''.join('<span>'+esc(x)+'</span>' for x in scene['title'])
 if kind=='phone':header=f'left:100px;top:170px;width:1120px';title_size=80 if number==1 else 66;body_size=34
 else:header='left:110px;top:85px;width:1710px';title_size=48;body_size=26
 # Admin scene descriptions use the subtitle band; avoid stacking two lines over the capture.
 body='' if kind=='admin' and scene['captions'] else '<p class="chapter-body" style="font-size:'+str(body_size)+'px">'+esc(scene['body'])+'</p>'
 markup=f'''<!doctype html><html><head><meta charset="utf-8"></head><body><template>
<style>{shared} #{sid}{{position:absolute;inset:0;width:100%;height:100%;font-family:CareCN,sans-serif;background:#F7F8F2}}</style>
<div id="{sid}" data-composition-id="{sid}" data-width="1920" data-height="1080" data-duration="{duration}">
<div style="position:absolute;{header}"><div class="chapter-count">{number:02d} / 06</div><h1 class="chapter-title hf-catalog-staggered-fade-up" style="font-size:{title_size}px">{title}</h1>{body}</div>
{''.join(media)}{''.join(captions)}
</div><script>(()=>{{const tl=gsap.timeline({{paused:true}});tl.fromTo('#{sid} .chapter-title span',{{opacity:0,'--hf-word-y':'22px','--hf-word-scale':.92,'--hf-word-blur':'5px'}},{{opacity:1,'--hf-word-y':'0px','--hf-word-scale':1,'--hf-word-blur':'0px',duration:.3,stagger:.055,ease:'power3.out'}},.05);window.__timelines['{sid}']=tl;}})();</script>
</template></body></html>'''
 (comps/(sid+'.html')).write_text(markup)
 hosts.append(f'<div id="host-{sid}" class="clip" data-composition-id="{sid}" data-composition-src="compositions/frames/{sid}.html" data-start="{start}" data-duration="{duration}" data-track-index="0" data-width="1920" data-height="1080"></div>')
 story.append(f'## Frame {number}\nstatus: animated\nsrc: compositions/frames/{sid}.html\nduration: {duration}\nMotion: registry staggered-fade-up; source cuts follow hyperframes-core creator-editing-recipes hard cut contract.\nBeat: {scene["body"]}\n')
 start+=duration
index=f'''<!doctype html><html><head><meta charset="utf-8"><title>CareCircle · 系统演示</title><style>{font}html,body{{margin:0;width:100%;height:100%;overflow:hidden;background:#F7F8F2}}#carecircle-demo{{position:relative;width:100%;height:100%;font-family:CareCN,sans-serif;color:#153F35}}.clip{{position:absolute;inset:0}}.brand{{position:absolute;z-index:20;top:28px;left:110px;font-size:24px;font-weight:800;letter-spacing:1px}}.foot{{position:absolute;z-index:20;bottom:17px;left:110px;font-size:18px;color:#5F7065;letter-spacing:1px}}</style><script src="assets/vendor/gsap.min.js"></script></head><body><div id="carecircle-demo" data-composition-id="carecircle-demo" data-width="1920" data-height="1080" data-duration="{start}">{''.join(hosts)}<div class="brand">CareCircle</div><div class="foot">真实本地录屏 · 模拟人物及地址 · 非紧急社区协助</div></div><script>window.__timelines['carecircle-demo']=gsap.timeline({{paused:true}});</script></body></html>'''
(project/'index.html').write_text(index);(project/'STORYBOARD.md').write_text('# CareCircle verified walkthrough\n\n'+'\n'.join(story))
def timestamp(t):
 ms=round(t*1000);h,ms=divmod(ms,3600000);m,ms=divmod(ms,60000);s,ms=divmod(ms,1000);return f'{h:02}:{m:02}:{s:02},{ms:03}'
(project/'captions.zh-CN.srt').write_text('\n\n'.join(f'{i}\n{timestamp(a)} --> {timestamp(b)}\n{text}' for i,(a,b,text) in enumerate(srt,1)))
(project/'edit-decisions.json').write_text(json.dumps({'duration':start,'source':'Actual Android and local admin capture','native_speed':1,'admin_review_rate':.35,'admin_exception_rate':.5,'scenes':scenes},ensure_ascii=False,indent=2))
print('Authored duration:',start,'seconds')
