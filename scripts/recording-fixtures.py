#!/usr/bin/env python3
"""Prepare fictional local records for browser walkthrough capture."""
import json,urllib.request,datetime,time
from pathlib import Path
root=Path(__file__).resolve().parents[1]
base='http://127.0.0.1:8088/api/v1'
def call(path,data=None,token=None,method=None):
 h={'Accept':'application/json','Content-Type':'application/json'}
 if token:h['Authorization']='Bearer '+token
 req=urllib.request.Request(base+path,data=json.dumps(data).encode() if data is not None else None,headers=h,method=method or ('POST' if data is not None else 'GET'))
 with urllib.request.urlopen(req,timeout=15) as r:return json.load(r)
a=call('/login',{'email':'aisha.demo@carecircle.test','password':'CircleDemo2026!'})
call('/volunteer-profile',{'zone_ids':[1],'category_ids':[1,2],'max_tasks':1,'availability':[{'day':d,'start':'08:00','end':'19:00'} for d in range(1,8)]},a['token'],'PUT');call('/logout',{},a['token'])
j=call('/login',{'email':'jason@carecircle.test','password':'CircleDemo2026!'})
b=next(p for p in call('/beneficiaries',token=j['token']) if p['user_id']==j['user']['id'])
s=(datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=8)))+datetime.timedelta(days=1)).replace(hour=16,minute=0,second=0,microsecond=0)
r=call('/requests',{'beneficiary_id':b['id'],'service_category_id':2,'title':'Lakeside parcel · recorded demo','pickup_address':'DEMO ONLY · Lakeside community counter','notes':'Fictional recording scenario; no eligible volunteer serves this area.','scheduled_start':s.isoformat(),'scheduled_end':(s+datetime.timedelta(hours=1)).isoformat(),'prepaid_confirmed':True},j['token'])
for _ in range(20):
 r=call('/requests/'+str(r['id']),token=j['token'])
 if r['status']=='escalated':break
 time.sleep(.25)
call('/logout',{},j['token'])
(root/'outputs'/'recording-fixtures.json').write_text(json.dumps({'request_id':r['id'],'status':r['status']},indent=2))
print('Fictional recording fixtures ready:',r['id'],r['status'])
