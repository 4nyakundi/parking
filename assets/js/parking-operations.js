/* Shared guard/management operations. Loaded on demand; no background polling loop. */
const ParkingOperations = (() => {
    let data=null, sessionId=0, revision=0, busy=false;
    const el=id=>document.getElementById(id);
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const manager=()=>['admin','supervisor'].includes(document.body.dataset.userRole || (typeof STATE!=='undefined'?STATE.user?.role:''));
    async function request(action,payload=null) {
        const url='../api/guard/operations.php?action='+action;
        const options=payload?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':typeof STATE!=='undefined'?STATE.csrfToken:document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify(payload)}:{};
        if(typeof AdminTransport!=='undefined') return AdminTransport.request(url,options);
        const response=await fetch(url,{...options,cache:'no-store'}); const json=await response.json();
        if(response.status===401) { close(); if(typeof GuardLive!=='undefined') GuardLive.snapshot(); }
        return json;
    }
    function status(message) {if(el('opsStatus'))el('opsStatus').textContent=message;}
    function close() { revision++; data=null;sessionId=0;el('parkingOperations')?.remove(); }
    async function open(id=0) {
        close(); sessionId=Number(id)||0;
        const overlay=document.createElement('div');overlay.id='parkingOperations';overlay.className='ops-overlay';
        overlay.innerHTML=`<section class="ops-panel" role="dialog" aria-modal="true" aria-labelledby="opsTitle">
          <header><h2 id="opsTitle">Parking Follow-ups & Incidents</h2><button type="button" id="opsClose" aria-label="Close">Close</button></header>
          <p id="opsStatus" role="status">Loading…</p><div id="opsContent"></div></section>`;
        document.body.appendChild(overlay);el('opsClose').onclick=close;el('opsClose').focus();
        overlay.onkeydown=event=>{
            if(event.key==='Escape'&&!busy)close();
            if(event.key==='Tab') {const nodes=[...overlay.querySelectorAll('button,input,select,textarea')].filter(n=>!n.disabled&&!n.hidden&&n.offsetParent!==null);const first=nodes[0],last=nodes.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}}
        };
        await refresh();
    }
    async function refresh() {
        const token=++revision;
        try {const json=await request('overview');if(token!==revision||!el('opsContent'))return;if(!json.ok)throw Error(json.error);data=json.data;
            if(!data.current&&!data.handover_complete){const attach=await request('use_current_shift',{});if(token!==revision||!el('opsContent'))return;if(!attach.ok)throw Error(attach.error);return refresh();}
            render();status('Current signed-in shift - Nairobi time. Supervisor code is required for each follow-up or incident change.');}
        catch(e){if(token===revision)status(e.message||'Could not load operations.');}
    }
    function render() {
        const shift=data.current, cases=data.cases;
        const active=cases.filter(c=>c.status==='ACTIVE');
        const overdue=active.filter(c=>new Date(c.deadline.replace(' ','T')+'+03:00').getTime()<=Date.now());
        if(!cases.some(c=>Number(c.id)===sessionId))sessionId=Number(cases.find(c=>Number(c.is_clamped)||Number(c.has_incident)||c.vehicle_category==='blacklisted'||Number(c.open_actions))?.id||cases[0]?.id)||0;
        el('opsContent').innerHTML=`<div class="ops-metrics"><span><strong>${active.length}</strong> Inside</span><span><strong>${cases.filter(c=>Number(c.open_actions)>0).length}</strong> Open follow-ups</span><span><strong>${cases.filter(c=>Number(c.has_incident)).length}</strong> Incidents</span><span><strong>${cases.filter(c=>Number(c.is_clamped)).length}</strong> Clamped</span><span><strong>${cases.filter(c=>c.vehicle_category==='blacklisted').length}</strong> Blacklisted</span></div>
        <div class="ops-card"><h3>Vehicle follow-ups, incidents & clamps</h3><label>Vehicle<select id="opsVehicle"><option value="0">Choose a vehicle</option>${cases.map(c=>`<option value="${Number(c.id)}" ${Number(c.id)===sessionId?'selected':''}>${esc(c.plate_number)} - ${esc(c.driver_name)}${Number(c.is_clamped)?' | CLAMPED':''}${c.vehicle_category==='blacklisted'?' | BLACKLISTED':''}${Number(c.has_incident)?' | INCIDENT':''}${c.status==='COMPLETED'?' | Exited':''}</option>`).join('')}</select></label>
        <label>Supervisor code<input id="opsSupervisorPin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" placeholder="4-6 digit supervisor PIN"></label><p>Every saved follow-up, incident, clamp release or blacklist change requires supervisor approval.</p><div id="opsVehicleContent"></div></div>
        <details class="ops-card"><summary>Current shift - ${esc(data.operator_name)}${shift?' | Shift #'+Number(shift.id):''}</summary>${shift?`<p>Started ${esc(shift.started_at)} | Opening ${shift.opening_count} + arrivals ${shift.arrivals} - departures ${shift.departures} = ${shift.expected_closing} expected inside.</p><form id="opsHandover"><label>Physical cars counted<input id="opsPhysical" type="number" min="0" step="1" required></label><label>Handover notes<textarea id="opsHandoverNotes" maxlength="4000"></textarea></label><label>Supervisor code<input id="opsHandoverPin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" required></label><button>End shift & save handover</button></form>`:'<p>Handover complete. Sign in with the incoming guard account to use the next shift.</p>'}</details>
        <details class="ops-card"><summary>Other vehicle exceptions (${overdue.length} overdue)</summary><div class="ops-scroll"><table><thead><tr><th>Plate</th><th>Deadline</th><th>Open actions</th><th></th></tr></thead><tbody>${cases.filter(c=>overdue.includes(c)||Number(c.open_actions)).map(c=>`<tr><td>${esc(c.plate_number)}</td><td>${esc(c.deadline)}</td><td>${Number(c.open_actions)}</td><td><button data-case="${Number(c.id)}">Review</button></td></tr>`).join('')||'<tr><td colspan="4">No outstanding exceptions.</td></tr>'}</tbody></table></div>${manager()?'<button id="opsReconcile">Open missing-exit reconciliation</button><button id="opsMissing">Review missing driver details</button><button id="opsMessages">Review failed messages</button>':''}</details>
        <details class="ops-card"><summary>Recent shifts & handovers</summary><div class="ops-scroll"><table><thead><tr><th>Guard</th><th>Start / end</th><th>Opening + in - out</th><th>System / physical</th><th>Handover</th></tr></thead><tbody>${data.recent.map(r=>`<tr><td>${esc(r.guard_name)}</td><td>${esc(r.started_at)}<br>${esc(r.ended_at||'On duty')}</td><td>${r.opening_count} + ${r.arrivals} - ${r.departures} = ${r.expected_closing}</td><td>${r.closing_count??'--'} / ${r.physical_count??'--'}</td><td>${esc(r.handover_notes||'--')}</td></tr>`).join('')}</tbody></table></div></details><button id="opsRefresh">Refresh follow-ups</button>`;
        el('opsRefresh').onclick=refresh;
        if(el('opsHandover'))el('opsHandover').onsubmit=e=>{e.preventDefault();save('end_shift',{physical_count:el('opsPhysical').value,notes:el('opsHandoverNotes').value});};
        el('opsVehicle').onchange=()=>{sessionId=Number(el('opsVehicle').value);el('opsSupervisorPin').value='';vehicle();};
        document.querySelectorAll('[data-case]').forEach(b=>b.onclick=()=>{sessionId=Number(b.dataset.case);el('opsVehicle').value=String(sessionId);vehicle();});
        if(el('opsReconcile'))el('opsReconcile').onclick=()=>{close();navigateTo('reconcile');};
        for(const [id,issue] of [['opsMissing','missing_details'],['opsMessages','message_failed']])if(el(id))el(id).onclick=()=>{close();navigateTo('sessions');document.getElementById('filterIssue').value=issue;loadSessions(1);};
        vehicle();
    }
    async function vehicle() {
        const c=data?.cases.find(c=>Number(c.id)===sessionId);const container=el('opsVehicleContent');
        if(!container)return;container.replaceChildren();if(!c)return;
        container.innerHTML=`<div class="ops-flags">${Number(c.is_clamped)?'<strong class="ops-danger">CLAMPED - release the clamp before departure</strong>':''}${c.vehicle_category==='blacklisted'?'<strong class="ops-danger">BLACKLISTED - supervisor authorization required to leave</strong>':''}${Number(c.has_incident)?'<strong class="ops-warning">OPEN INCIDENT - supervisor authorization required to leave</strong>':''}</div><p>Entered ${esc(c.entry_time)} · Approved deadline ${esc(c.deadline)}${c.extension_reason?' · '+esc(c.extension_reason):''}</p>
          <form id="opsAction"><label>Action<select id="opsType"><option value="follow_up">Follow-up</option><option value="driver_contacted">Driver contacted</option><option value="supervisor_notified">Supervisor notified</option><option value="incident">Incident</option>${c.status==='ACTIVE'?'<option value="clamped">Vehicle clamped</option>':''}</select></label><label>Details<textarea id="opsNotes" required maxlength="4000"></textarea></label><button>Save follow-up / incident</button></form>
          <h4>Action history</h4><div id="opsHistory">Loading…</div>
          ${c.status==='ACTIVE'?'<details><summary>Approve extra time</summary><form id="opsExtension"><p>Extra minutes are added after the current deadline or now, whichever is later. The entry time stays unchanged.</p><label>Extra minutes<input type="number" id="opsMinutes" min="1" max="1440" required value="30"></label><label>Approval reason<textarea id="opsReason" required maxlength="4000"></textarea></label><button>Approve extension</button></form></details>':''}
          ${c.vehicle_category==='blacklisted'?`<p class="ops-danger">Blacklist reason: ${esc(c.blacklist_reason||'Recorded in vehicle register')}</p>`:`<details><summary>Add vehicle to blacklist</summary><form id="opsBlacklist"><label>Misconduct / blacklist reason<textarea id="opsBlacklistReason" required maxlength="4000"></textarea></label><p>The plate will remain blacklisted on future camera detections. Driver profile details are retained.</p><button class="ops-blacklist-button">Add to blacklist</button></form></details>`}
          <details ${c.status!=='ACTIVE'?'hidden':''}><summary>Change intended stores</summary><form id="opsStores"><p>Select one or more stores using the checkboxes.</p><div id="opsStoreList" class="ops-stores">Loading stores…</div><button>Save destinations</button></form></details>
`;
        el('opsAction').onsubmit=e=>{e.preventDefault();save('log_action',{session_id:sessionId,type:el('opsType').value,notes:el('opsNotes').value});};
        if(el('opsExtension'))el('opsExtension').onsubmit=e=>{e.preventDefault();save('extend',{session_id:sessionId,minutes:el('opsMinutes').value,notes:el('opsReason').value});};
        if(el('opsBlacklist'))el('opsBlacklist').onsubmit=e=>{e.preventDefault();save('blacklist',{session_id:sessionId,notes:el('opsBlacklistReason').value});};
        el('opsStores').onsubmit=e=>{e.preventDefault();save('destinations',{session_id:sessionId,destinations:[...document.querySelectorAll('#opsStoreList input:checked')].map(n=>n.value)});};
        const id=sessionId,token=revision;
        try {
            const json=await request('history&session_id='+id);
            if(id!==sessionId||token!==revision||!el('opsHistory'))return;
            if(!json.ok)throw Error(json.error);
            el('opsHistory').innerHTML=json.data.actions.map(a=>`<article class="ops-history"><strong>${esc(a.action_type.replaceAll('_',' '))}</strong> · ${esc(a.author)} · ${esc(a.created_at)}<br>${a.shift_id?'Shift #'+Number(a.shift_id):'Historical action - no shift recorded'}${a.authorized_by_name?' - Approved by '+esc(a.authorized_by_name):''}<p>${esc(a.notes)}</p>${a.resolved_at?`Resolved by ${esc(a.resolved_by_name)} at ${esc(a.resolved_at)}`:` ${a.action_type==='clamped'?`<label>Clamp release details<input id="opsReleaseNotes${Number(a.id)}" maxlength="4000"></label>`:''}<button data-resolve="${Number(a.id)}">${a.action_type==='clamped'?'Record clamp release':'Mark resolved'}</button>`}</article>`).join('')||'No follow-ups recorded.';
            document.querySelectorAll('[data-resolve]').forEach(b=>b.onclick=()=>save('resolve_action',{session_id:id,action_id:Number(b.dataset.resolve),notes:el('opsReleaseNotes'+b.dataset.resolve)?.value||''}));
            const response=await fetch('../api/driver/destinations.php');const stores=await response.json();
            if(id!==sessionId||token!==revision||!el('opsStoreList'))return;
            if(!stores.ok)throw Error(stores.error||'Store directory unavailable.');
            el('opsStoreList').innerHTML=stores.data.map(s=>`<label><input type="checkbox" value="${esc(s.name)}" ${c.destinations.includes(s.name)?'checked':''}>${esc(s.name)}</label>`).join('');
        }catch(e){status(e.message||'Could not load vehicle details.');}
    }
    async function save(action,payload) {
        if(busy)return;
        if(['log_action','resolve_action','extend','end_shift','blacklist'].includes(action)){if(!data?.current){status('Handover is complete. Sign in for the next shift before saving changes.');return;}const field=el(action==='end_shift'?'opsHandoverPin':'opsSupervisorPin');const pin=field?.value||'';if(!/^[0-9]{4,6}$/.test(pin)){status('Enter the supervisor code to approve this change.');field?.focus();return;}payload={...payload,supervisor_pin:pin};field.value='';}
        busy=true;const token=revision;
        document.querySelectorAll('#parkingOperations button').forEach(b=>b.disabled=true);status('Saving…');
        try {
            const json=await request(action,payload);if(token!==revision)return;if(!json.ok)throw Error(json.error);
            await refresh();status('Saved to the current shift with supervisor approval.');
            if(typeof GuardLive!=='undefined')GuardLive.snapshot();
        }catch(e){if(token===revision)status(e.message||'Could not confirm the change. Refresh before retrying.');}
        finally{busy=false;document.querySelectorAll('#parkingOperations button').forEach(b=>b.disabled=false);}
    }
    document.addEventListener('DOMContentLoaded',()=>{
        const host=document.querySelector('.top-bar .brand-section');
        if(host){const b=document.createElement('button');b.textContent='Follow-ups & Incidents';b.className='btn-roster-action';b.onclick=()=>open();host.appendChild(b);}
    });
    return {open,close};
})();
