const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function harness(responder){
    const elements=new Map();const calls=[];
    const element=id=>{
        if(!elements.has(id))elements.set(id,{id,value:'',innerHTML:'',textContent:'',style:{},dataset:{},disabled:false,
            appendChild(){},replaceChildren(){},remove(){},focus(){this.focused=true;},querySelectorAll(){return[];}});
        return elements.get(id);
    };
    const context={console,Date,Number,String,Error,JSON,Array,STATE:{user:{id:1,role:'guard'},csrfToken:'test-token'},
        document:{body:{dataset:{userRole:'guard'},appendChild(){}},getElementById:element,querySelectorAll:()=>[],addEventListener(){},createElement:()=>element('created')},
        fetch:async(url,options={})=>{calls.push({url,options});const body=await responder(url,options,calls.length);return{status:200,json:async()=>body};}};
    vm.createContext(context);
    vm.runInContext(fs.readFileSync(require.resolve('../assets/js/parking-operations.js'),'utf8')+'\nglobalThis.ops=ParkingOperations;',context);
    return {ops:context.ops,element,calls};
}
const overview=current=>({ok:true,data:{operator_name:'On-duty Guard',current,cases:[],recent:[],handover_complete:false}});
const shift={id:7,started_at:'2026-11-09 08:00:00',opening_count:2,arrivals:1,departures:0,expected_closing:3};

test('opening follow-ups uses the existing shift and provides code approval without another start step',async()=>{
    const h=harness(async()=>overview(shift));await h.ops.open();
    assert.equal(h.calls.length,1);assert.match(h.element('opsContent').innerHTML,/Shift #7/);
    assert.match(h.element('opsContent').innerHTML,/opsSupervisorPin/);
    assert.doesNotMatch(h.element('opsContent').innerHTML,/Start my shift/);
});
test('an older signed-in session is attached once and then reuses the recorded shift',async()=>{
    const h=harness(async(url,options,n)=>n===1?overview(null):url.includes('use_current_shift')?{ok:true,data:{shift}}:overview(shift));
    await h.ops.open();assert.equal(h.calls.length,3);
    assert.match(h.calls[1].url,/use_current_shift/);assert.equal(h.calls[1].options.method,'POST');
    assert.equal(h.calls[1].options.headers['X-CSRF-Token'],'test-token');
    assert.match(h.element('opsContent').innerHTML,/Shift #7/);
});
test('handover saves require a code and clear the code field even on a failed approval',async()=>{
    const h=harness(async(url)=>url.includes('end_shift')?{ok:false,error:'Invalid code'}:overview(shift));await h.ops.open();
    h.element('opsPhysical').value='3';h.element('opsHandoverNotes').value='Remaining cars handed over';
    h.element('opsHandover').onsubmit({preventDefault(){}});
    assert.equal(h.calls.length,1);assert.match(h.element('opsStatus').textContent,/supervisor code/i);
    h.element('opsHandoverPin').value='834672';h.element('opsHandover').onsubmit({preventDefault(){}});
    assert.equal(h.element('opsHandoverPin').value,'');
    const sent=JSON.parse(h.calls[1].options.body);assert.equal(sent.supervisor_pin,'834672');assert.equal(sent.physical_count,'3');
    await new Promise(resolve=>setImmediate(resolve));assert.match(h.element('opsStatus').textContent,/Invalid code/);
});
test('completed handover does not silently create a second shift',async()=>{
    const h=harness(async()=>({ok:true,data:{operator_name:'Guard',current:null,cases:[],recent:[],handover_complete:true}}));
    await h.ops.open();assert.equal(h.calls.length,1);assert.match(h.element('opsContent').innerHTML,/Handover complete/);
    assert.doesNotMatch(h.element('opsContent').innerHTML,/Start my shift/);
});
