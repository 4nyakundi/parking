const test=require('node:test');const assert=require('node:assert/strict');const vm=require('node:vm');const fs=require('node:fs');
function harness(){
    const nodes=new Map();let now=100000,sounds=0;const departures=[],reviews=[];
    function element(){let id='';const n={children:[],textContent:'',className:'',hidden:false,setAttribute(){},append(...rows){this.children.push(...rows);},appendChild(row){this.children.push(row);},replaceChildren(...rows){this.children=rows;},remove(){nodes.delete(id);}};Object.defineProperty(n,'id',{get:()=>id,set:v=>{id=v;nodes.set(v,n);}});return n;}
    const context={console,Date:{now:()=>now},STATE:{user:{id:1}},document:{getElementById:id=>nodes.get(id),createElement:element,body:{appendChild(){}}},playAlertChime(){sounds++;},executeClearExit:(...args)=>departures.push(args),ParkingOperations:{open:id=>reviews.push(id)}};
    vm.createContext(context);vm.runInContext(fs.readFileSync(require.resolve('../guard/js/parking-alerts.js'),'utf8')+'\nglobalThis.alerts=ParkingAlerts;',context);
    return {alerts:context.alerts,nodes,departures,reviews,sounds:()=>sounds,tick:()=>{now+=1000;},card:()=>nodes.get('vehicleRestrictionAlerts').children[0]};
}
const arrival={detection_id:9,camera:'entrance',plate_number:'KDA123A',session_id:0,is_blacklisted:true,is_clamped:false};
test('blacklisted approach shows a warning and routes authorization through the exit dialog',()=>{
    const h=harness();h.alerts.show(arrival);assert.equal(h.card().children[0].textContent,'BLACKLISTED CAR APPROACHING');
    assert.equal(h.departures.length,0);const button=h.card().children.find(n=>n.textContent==='Authorize to leave');assert.ok(button);button.onclick();
    assert.equal(h.departures.length,1);assert.equal(h.departures[0][1],'KDA123A');
    h.alerts.set([arrival]);assert.equal(h.nodes.get('vehicleRestrictionAlerts').hidden,true);
    h.alerts.show({...arrival,detection_id:10});assert.equal(h.nodes.get('vehicleRestrictionAlerts').hidden,false);
});
test('clamped camera detections require reviewing and releasing the clamp before departure',()=>{
    const h=harness();h.alerts.show({...arrival,session_id:4,is_clamped:true});
    assert.equal(h.card().children.some(n=>n.textContent==='Authorize to leave'),false);
    h.card().children.find(n=>n.textContent==='Follow up / release clamp').onclick();assert.equal(h.reviews[0],4);assert.equal(h.departures.length,0);
});
test('camera values render as text, repeated sounds are capped, and sign-out clears alerts',()=>{
    const h=harness();h.alerts.show({...arrival,plate_number:'<img onerror=bad()>'});assert.equal(h.card().children[1].textContent,'<img onerror=bad()>');assert.equal(h.sounds(),1);
    h.tick();h.alerts.show({...arrival,detection_id:10,plate_number:'<img onerror=bad()>'});assert.equal(h.sounds(),1);
    h.alerts.reset();assert.equal(h.nodes.has('vehicleRestrictionAlerts'),false);
});
