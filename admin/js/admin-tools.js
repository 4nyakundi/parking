/* Reporting and management display helpers. No camera commands. */
const AdminTools = (() => {
    let destinationChart = null, dayLoaded = '', dayUpdated = 0;
    const el = id => document.getElementById(id);
    const text = (id,value) => { if (el(id)) el(id).textContent = value ?? '—'; };
    function listError(body,message) {
        if (!body) return;
        body.closest('.table-card')?.querySelector('.admin-pagination')?.replaceChildren();
        body.replaceChildren(); const row = document.createElement('tr'), cell = document.createElement('td');
        cell.colSpan = body.closest('table')?.querySelectorAll('thead th').length || 8;
        cell.className = 'admin-load-error'; cell.textContent = message || 'Could not load records. Please retry.';
        row.appendChild(cell); body.appendChild(row);
    }
    function pagination(body,data,load) {
        if (!body) return;
        const card = body.closest('.table-card'); if (!card) return;
        let bar = card.querySelector('.admin-pagination');
        if (!bar) { bar = document.createElement('div'); bar.className = 'admin-pagination'; card.appendChild(bar); }
        bar.replaceChildren();
        const pages = Math.max(1,data.pages || 1), page = data.page || 1;
        const label = document.createElement('span'); label.textContent = `${data.total} records · Page ${page} of ${pages}`;
        bar.appendChild(label);
        for (const [caption,target,disabled] of [['Previous',page-1,page<=1],['Next',page+1,page>=pages]]) {
            const button = document.createElement('button'); button.className = 'btn-primary'; button.textContent = caption; button.disabled = disabled;
            button.addEventListener('click',() => load(target)); bar.appendChild(button);
        }
    }
    function rows(body,items,fields) {
        body.replaceChildren();
        for (const item of items) {
            const row = document.createElement('tr');
            for (const key of fields) { const cell = document.createElement('td'); cell.textContent = typeof key === 'function' ? key(item) : (item[key] ?? '—'); row.appendChild(cell); }
            body.appendChild(row);
        }
        if (!items.length) { const row = document.createElement('tr'), cell = document.createElement('td'); cell.colSpan=fields.length; cell.textContent='No matching records.'; row.appendChild(cell); body.appendChild(row); }
    }
    function openIssue(issue) { navigateTo('sessions'); el('filterIssue').value = issue; loadSessions(1); }
    function renderOverview(data) {
        const daily = data.daily;
        for (const [id,value] of Object.entries({ovTotal:data.capacity,ovOccupied:data.occupied,ovAvailable:Math.max(0,data.capacity-data.occupied),
            ovEntries:daily.total_arrivals,ovExits:daily.exited,ovRegistered:daily.registered,ovUnregistered:daily.unregistered,
            ovOverstay:data.overstays,ovAvgStay:`${data.avg_stay_mins}m`})) text(id,value);
        text('ovOverstayRule',`Parked at least ${data.overstay_hours} hours`);
        const attention = el('adminAttention'); attention.replaceChildren();
        const issues = [[daily.pending_registration,'Awaiting registration','visitors'],[data.overstays,'Overstayed cars','sessions','overstay'],
            [data.attention['Print failures'],'Print failures','sessions','print_failed'],[data.attention['Message failures'],'Message failures','sessions','message_failed'],
            [data.attention['Pending cloud uploads'],'Pending cloud uploads','cloud']];
        for (const [count,label,section,issue] of issues) {
            const button=document.createElement('button'); button.className='admin-attention-item'; button.textContent=`${label}: ${count}`;
            button.addEventListener('click',() => { if (issue) openIssue(issue); else { navigateTo(section); if (section==='visitors') { el('visitorStatusFilter').value='PENDING'; loadVisitors(1); } } }); attention.appendChild(button);
        }
        const devices = new Map(data.devices.map(device=>[device.device,device]));
        const statuses = ['entrance_cam','exit_cam','alpr_worker','printer','cloud_sync'].map(name=>{
            const device=devices.get(name);
            const fresh=device && (name==='printer' || Number(device.age_seconds)<=120);
            return `${name.replaceAll('_',' ')}: ${!device ? 'Unknown' : !fresh ? 'Stale' : name==='printer' && device.status==='OK' ? 'Last job OK' : device.status}`;
        });
        text('adminDeviceSummary',statuses.join(' · '));
        text('adminBackupSummary',data.backup ? `Last recorded backup: ${data.backup.created_at} · ${data.backup.filename}. Recovery test not confirmed.` : 'No backup recorded.');
        text('adminShiftSummary',data.handover ? `Last sign-off: ${data.handover.created_at} · ${data.handover.full_name || 'Supervisor'} · Variance ${data.handover.details?.drift_delta ?? '—'}` : 'No shift sign-off recorded.');
        const date=el('adminTrafficDate'); date.max=daily.date;
        if (!date.value) date.value=daily.date;
        if (dayLoaded!==date.value || (date.value===daily.date && Date.now()-dayUpdated>15000)) loadDay();
    }
    async function loadDay(page=1) {
        const date=el('adminTrafficDate').value; if (!date) return;
        const result=await apiFetch(`../api/guard/history.php?mode=day&date=${encodeURIComponent(date)}&page=${page}`);
        if (result.stale) return;
        if (!result.ok) { text('adminTrafficSummary',result.error); return; }
        const d=result.data, s=d.summary; dayLoaded=date; dayUpdated=Date.now();
        text('adminTrafficSummary',`${d.date} · ${s.present_visits} visits present · ${s.unique_cars} unique plates · ${s.arrivals} arrivals · ${s.exits} exits · ${s.movements} movements · ${s.remaining} remaining`);
        rows(el('adminTrafficBody'),d.sessions,['plate_number','driver_name','destination','entry_time',item=>item.exit_time || 'Still inside']);
        pagination(el('adminTrafficBody'),d,loadDay);
    }
    function renderReport(data) {
        const s=data.summary;
        text('repTotalIn',s.total_entries); text('repTotalOut',s.total_exits); text('repAvgStay',`${s.avg_duration_mins}m`); text('repPeak',s.peak_hour);
        rows(el('shiftTableBody'),data.shifts,['guard_name','entries_approved','exits_cleared','rejections']);
        if (window.Chart) {
            destinationChart?.destroy();
            destinationChart=new Chart(el('chartDestinations'),{type:'bar',data:{labels:data.destinations.map(d=>d.destination),datasets:[{label:'Visits',data:data.destinations.map(d=>d.count),backgroundColor:'#116fc7'}]},options:{responsive:true,maintainAspectRatio:false}});
        }
    }
    return {listError,pagination,renderOverview,renderReport,loadDay};
})();

async function searchDpaData() {
    const q=document.getElementById('dpaSearchQuery').value.trim();
    if (!q) { alert('Enter a plate or phone number.'); return; }
    const result=await apiFetch(`../api/admin/dpa.php?q=${encodeURIComponent(q)}`);
    if (result.stale) return;
    const target=document.getElementById('dpaResultsPre');
    target.textContent=result.ok ? JSON.stringify(result.data,null,2) : result.error;
    if (!result.ok) return;
    const url=URL.createObjectURL(new Blob([target.textContent],{type:'application/json'}));
    const link=document.createElement('a'); link.href=url; link.download='parking-records.json'; link.click(); setTimeout(()=>URL.revokeObjectURL(url),1000);
}

function openDestinationModal(data={}) {
    const fields={destinationId:data.id || 0,destinationName:data.name || '',destinationUnit:data.unit_code || '',destinationFloor:data.floor_level || 'Ground Floor',destinationCategory:data.category || 'Retail',destinationOrder:data.sort_order || 0,destinationIcon:data.icon || 'store',destinationDescription:data.description || ''};
    for (const [id,value] of Object.entries(fields)) document.getElementById(id).value=value;
    document.getElementById('destinationActive').checked=data.is_active === undefined || Boolean(Number(data.is_active));
    document.getElementById('destinationModal').classList.add('open');
}
async function saveDestination() {
    const value=id=>document.getElementById(id).value;
    const result=await apiFetch('../api/admin/destinations.php?action=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:value('destinationId'),name:value('destinationName'),unit_code:value('destinationUnit'),floor_level:value('destinationFloor'),category:value('destinationCategory'),sort_order:value('destinationOrder'),icon:value('destinationIcon'),description:value('destinationDescription'),is_active:document.getElementById('destinationActive').checked})});
    if (result.ok) { closeAdminModal('destinationModal'); loadDestinations(); } else alert(result.error);
}
