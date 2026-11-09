/* Camera restriction warnings are recovered through the existing live snapshot. */
const ParkingAlerts=(()=>{
    let items=[],signature='';const dismissed=new Set(),lastSound=new Map();
    const key=a=>`${a.detection_id}:${a.camera}:${a.plate_number}`;
    function host(){let node=document.getElementById('vehicleRestrictionAlerts');if(!node){node=document.createElement('section');node.id='vehicleRestrictionAlerts';node.className='vehicle-restriction-alerts';node.setAttribute('aria-live','assertive');document.body.appendChild(node);}return node;}
    function render(){
        const visible=items.filter(a=>!dismissed.has(key(a)));const next=JSON.stringify(visible);
        if(next===signature)return;signature=next;const node=host();node.replaceChildren();node.hidden=!visible.length;
        for(const item of visible){
            const card=document.createElement('article');card.className='vehicle-restriction-card';
            const title=document.createElement('strong');title.textContent=item.is_blacklisted?'BLACKLISTED CAR APPROACHING':item.is_clamped?'CLAMPED VEHICLE AT CAMERA':'VEHICLE WITH OPEN INCIDENT';
            const plate=document.createElement('div');plate.className='restriction-plate';plate.textContent=item.formatted_plate||item.plate_number;
            const note=document.createElement('p');note.textContent=`${item.camera==='exit'?'Exit':'Entrance'} camera - ${item.is_clamped?'Supervisor must record clamp release before departure.':'Supervisor authorization required to leave.'}`;
            card.append(title,plate,note);
            if(Number(item.session_id)){const review=document.createElement('button');review.textContent=item.is_clamped?'Follow up / release clamp':'Review incident / follow-up';review.onclick=()=>ParkingOperations.open(Number(item.session_id));card.appendChild(review);}
            if(!item.is_clamped){const leave=document.createElement('button');leave.textContent='Authorize to leave';leave.onclick=()=>executeClearExit(Number(item.session_id)||0,item.plate_number);card.appendChild(leave);}
            const dismiss=document.createElement('button');dismiss.textContent='Dismiss alert';dismiss.onclick=()=>{dismissed.add(key(item));render();};card.appendChild(dismiss);node.appendChild(card);
        }
    }
    function set(rows){items=rows||[];render();}
    function show(item){if(typeof STATE==='undefined'||!STATE.user)return;items=items.filter(a=>!(a.plate_number===item.plate_number&&a.camera===item.camera));items.unshift(item);items=items.slice(0,20);render();const last=lastSound.get(item.plate_number)||0;if(Date.now()-last>10000){lastSound.set(item.plate_number,Date.now());if(typeof playAlertChime==='function')playAlertChime();}}
    function policy(plate){return items.find(a=>a.plate_number===plate)||null;}
    function reset(){items=[];signature='';dismissed.clear();lastSound.clear();document.getElementById('vehicleRestrictionAlerts')?.remove();}
    return{set,show,policy,reset};
})();
