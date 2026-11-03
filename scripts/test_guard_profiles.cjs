const test=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
function harness(fetch) {
    const elements=new Map();
    function el(id) { if(!elements.has(id)) elements.set(id,{value:'',textContent:'',hidden:true,checked:true,readOnly:false,style:{},focus(){},querySelector(sel){return el(id+sel);}}); return elements.get(id); }
    const context={fetch, console, STATE:{user:{id:1},wizard:{destination:''}},document:{getElementById:el,querySelector:el},renderGuardDestGrid(){},renderIntakeShops(){},showToast(){}};
    vm.createContext(context);vm.runInContext(fs.readFileSync(require.resolve('../guard/js/guard-profiles.js'),'utf8')+'\nglobalThis.profiles=GuardProfiles;',context);
    el('wizPlateInput').value='KDA123A';
    return {context,el};
}
const profile={has_bank_profile:true,plate_number:'KDA123A',driver_name:'Alice',driver_phone:'254733333333',masked_phone:'••••••3333',visit_count:4,is_frequent:true,default_destination:'Shop A'};
const reply=data=>({json:async()=>({ok:true,data})});
test('lookup suggests details without filling or locking today’s fields',async()=>{
    const h=harness(async()=>reply(profile)); await h.context.profiles.lookup('wiz','KDA123A');
    assert.equal(h.el('wizNameInput').value,'');assert.equal(h.el('wizPhoneInput').readOnly,false);
    assert.equal(h.el('wizReturningProfile').hidden,false);
    h.context.profiles.choose('wiz','use');
    assert.equal(h.el('wizNameInput').value,'Alice');assert.equal(h.context.STATE.wizard.destination,'Shop A');
});
test('different driver clears suggestions and leaves remembering off',async()=>{
    const h=harness(async()=>reply(profile)); await h.context.profiles.lookup('wiz','KDA123A');
    h.context.profiles.choose('wiz','use');h.context.profiles.choose('wiz','different');
    assert.equal(h.el('wizNameInput').value,'');assert.equal(h.el('wizPhoneInput').value,'');
    assert.equal(h.context.STATE.wizard.destination,'');assert.equal(h.context.profiles.payload('wiz').remember_details,false);
});
test('changing a plate removes reused identity immediately',async()=>{
    const h=harness(async()=>reply(profile));await h.context.profiles.lookup('wiz','KDA123A');h.context.profiles.choose('wiz','use');
    h.context.profiles.plateChanged('wiz','KDB456B');
    assert.equal(h.el('wizNameInput').value,'');assert.equal(h.el('wizReturningProfile').hidden,true);
});
test('late lookup responses cannot populate another vehicle’s suggestion card',async()=>{
    let release;const h=harness(async()=>new Promise(resolve=>release=resolve));
    const pending=h.context.profiles.lookup('wiz','KDA123A');
    h.el('wizPlateInput').value='KDB456B';h.context.profiles.plateChanged('wiz','KDB456B');
    release(reply(profile));await pending;assert.equal(h.el('wizReturningProfile').hidden,true);
});
test('blur does not reset an already chosen profile or launch another lookup',async()=>{
    let calls=0;const h=harness(async()=>{calls++;return reply(profile);});
    await h.context.profiles.lookup('wiz','KDA123A');h.context.profiles.choose('wiz','different');await h.context.profiles.lookup('wiz','KDA123A');
    assert.equal(calls,1);assert.equal(h.context.profiles.payload('wiz').remember_details,false);
});
