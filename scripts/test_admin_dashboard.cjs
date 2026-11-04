const test=require('node:test'), assert=require('node:assert/strict'), fs=require('node:fs'), vm=require('node:vm');
const settle=async()=>{for(let i=0;i<12;i++)await Promise.resolve();};
function transport(fetcher) {
    const notice={dataset:{},textContent:''}, timers=[];
    const c={URL,Headers,AbortController,Intl,Date,console,fetch:fetcher,
        window:{location:{href:'http://localhost/parking/admin/index.php'}},
        document:{hidden:false,getElementById:()=>notice,querySelector:()=>({content:'csrf-test'})},
        setTimeout(fn,ms){timers.push({fn,ms});return timers.length;},clearTimeout(){}};
    vm.createContext(c);vm.runInContext(fs.readFileSync(require.resolve('../admin/js/admin-core.js'),'utf8')+'\nglobalThis.transport=AdminTransport;',c);
    return {c,notice,timers,api:c.transport};
}
const response=(body={ok:true},status=200)=>({ok:status<400,status,json:async()=>body});
test('admin requests run sequentially',async()=>{
    let release,active=0,max=0,count=0;
    const h=transport(async()=>{active++;max=Math.max(max,active);count++;if(count===1)await new Promise(r=>release=r);active--;return response();});
    const first=h.api.request('/api/one.php'),second=h.api.request('/api/two.php');await settle();
    assert.equal(count,1);release();await Promise.all([first,second]);assert.equal(max,1);assert.equal(count,2);
});
test('older searches cannot replace the latest search',async()=>{
    let release,count=0;
    const h=transport(async()=>{count++;if(count===1)await new Promise(r=>release=r);return response({ok:true,data:count});});
    const old=h.api.request('/api/sessions.php?q=old');await settle();const latest=h.api.request('/api/sessions.php?q=new');
    release();assert.equal((await old).stale,true);assert.equal((await latest).data,2);
});
test('duplicate pending changes are coalesced and carry a security token',async()=>{
    let release,count=0,headers;
    const h=transport(async(url,options)=>{count++;headers=options.headers;await new Promise(r=>release=r);return response();});
    const options={method:'POST',body:'{"id":1}'};
    const a=h.api.request('/api/save.php',options),b=h.api.request('/api/save.php',options);await settle();
    assert.equal(count,1);assert.equal(headers.get('X-CSRF-Token'),'csrf-test');release();await Promise.all([a,b]);
});
test('permission denial does not sign out the administrator',async()=>{
    const h=transport(async()=>response({ok:false,error:'Not permitted',need_login:false},403));
    const result=await h.api.request('/api/users.php');assert.equal(result.ok,false);assert.match(h.c.window.location.href,/index.php/);
});
test('expired sessions redirect to login',async()=>{
    const h=transport(async()=>response({ok:false,need_login:true},401));await h.api.request('/api/users.php');assert.match(h.c.window.location.href,/login.php/);
});
test('failed changes report uncertainty without automatically replaying',async()=>{
    let count=0;const h=transport(async()=>{count++;throw Error('Lost connection');});
    const result=await h.api.request('/api/save.php',{method:'POST',body:'{}'});assert.equal(count,1);assert.match(result.error,/Check the record/);
});
test('periodic refresh backs off after failure',async()=>{
    const h=transport(async()=>response());h.api.schedule(async()=>false);await h.timers[0].fn();assert.equal(h.timers.at(-1).ms,10000);
});

function controller(responder) {
    const elements=new Map();const el=id=>{if(!elements.has(id))elements.set(id,{value:'',textContent:'',style:{},dataset:{}});return elements.get(id);};
    const c={console,URLSearchParams,window:{location:{href:''}},document:{body:{dataset:{}},addEventListener(){},getElementById:el},
        AdminTransport:{request:responder},AdminTools:{renderReport(data){c.report=data;},renderOverview(){}},alert(){}};
    vm.createContext(c);vm.runInContext(fs.readFileSync(require.resolve('../admin/js/admin.js'),'utf8')+'\nglobalThis.methods={saveSettings,loadSettings,loadReports,exportSessionsCsv};',c);
    return {c,el,methods:c.methods};
}
test('settings uses actual form IDs and the API update format',async()=>{
    const requests=[];const h=controller(async(url,options)=>{requests.push({url,options});return {ok:true,data:{}};});
    for(const [id,value]of Object.entries({setCapacity:'60',setOverstay:'8',setMallName:'Mall',setPrinterName:'POS80',setPrinterWidth:'80',setAlprConf:'0.85',setAlprDebounce:'30',setCloudUrl:'https://example.test/ingest'}))h.el(id).value=value;
    await h.methods.saveSettings();assert.match(requests[0].url,/action=update/);
    const payload=JSON.parse(requests[0].options.body);assert.equal(payload.settings.capacity,'60');assert.equal(payload.settings.printer_name,'POS80');assert.equal(payload.settings.alpr_confidence_threshold,'0.85');
});
test('reports read the visible date inputs and pass the structured response to rendering',async()=>{
    let request;const data={summary:{total_entries:3},shifts:[],destinations:[]};
    const h=controller(async url=>{request=url;return {ok:true,data};});h.el('repDateFrom').value='2026-10-01';h.el('repDateTo').value='2026-10-20';
    await h.methods.loadReports();assert.match(request,/date_from=2026-10-01/);assert.equal(h.c.report,data);
});
test('CSV export preserves plate, ticket, status, dates and issue filters',()=>{
    const h=controller(async()=>({ok:true}));for(const [id,value]of Object.entries({filterDateFrom:'2026-10-01',filterDateTo:'2026-10-20',filterPlate:'KDA 123A',filterTicket:'MM-1',filterStatus:'COMPLETED',filterIssue:'print_failed'}))h.el(id).value=value;
    h.methods.exportSessionsCsv();const params=new URLSearchParams(h.c.window.location.href.split('?')[1]);assert.equal(params.get('plate'),'KDA 123A');assert.equal(params.get('status'),'COMPLETED');assert.equal(params.get('issue'),'print_failed');assert.equal(params.get('date_to'),'2026-10-20');
});
