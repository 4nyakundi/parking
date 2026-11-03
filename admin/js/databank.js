let bankPage = 1, bankArchived = false;
async function openBankProfile(id, page = 1) {
    const result = await apiFetch(`../api/admin/databank.php?action=history&id=${id}&page=${page}`);
    if (!result.ok) { alert(result.error || 'Profile unavailable'); return; }
    const {profile, visits} = result.data;
    bankPage = page; bankArchived = profile.profile_archived;
    document.getElementById('bankId').value = id;
    document.getElementById('bankTitle').textContent = `Returning Visitor · ${profile.formatted_plate}`;
    document.getElementById('bankSummary').textContent = `${profile.visit_count} visits · Last seen: ${profile.last_seen || 'Never'} · ${bankArchived ? 'Profile reuse disabled' : 'Profile available for confirmation'}`;
    document.getElementById('bankDriverName').value = profile.driver_name || '';
    document.getElementById('bankDriverPhone').value = profile.driver_phone || '';
    document.getElementById('bankDestination').value = profile.default_destination || '';
    document.getElementById('bankArchiveButton').textContent = bankArchived ? 'Restore Profile Reuse' : 'Archive Profile';
    const list = document.getElementById('bankHistory'); list.replaceChildren();
    for (const visit of visits) {
        const row = document.createElement('p');
        row.textContent = `${visit.entry_time} · ${visit.ticket_id} · ${visit.driver_name} · ${visit.destination} · ${visit.status}`;
        list.appendChild(row);
    }
    if (!visits.length) list.textContent = 'No visits on this page.';
    document.getElementById('bankNotice').textContent = `Page ${page}. History is preserved when profiles are corrected or archived.`;
    document.getElementById('dataBankModal').classList.add('open');
}
async function bankAction(action, extra = {}) {
    const id = document.getElementById('bankId').value;
    const result = await apiFetch('../api/admin/databank.php', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify({action,id,...extra})});
    if (!result.ok) { document.getElementById('bankNotice').textContent = result.error || 'Profile change failed.'; return; }
    await openBankProfile(id); loadVehicles();
}
function saveBankProfile() { return bankAction('save', {driver_name:document.getElementById('bankDriverName').value, driver_phone:document.getElementById('bankDriverPhone').value, default_destination:document.getElementById('bankDestination').value}); }
function toggleBankArchive() { return bankAction(bankArchived ? 'restore' : 'archive'); }
function resetBankIdentity() { if (confirm('Clear saved driver details for this vehicle? Previous parking visits and access category will remain unchanged.')) return bankAction('reset'); }
function bankHistoryPage(delta) { return openBankProfile(document.getElementById('bankId').value, Math.max(1,bankPage+delta)); }

async function reviewBankRecords() {
    const result = await apiFetch('../api/admin/databank.php?action=review');
    const container = document.getElementById('bankReviewResults'); container.replaceChildren();
    if (!result.ok) { container.textContent = result.error || 'Review unavailable.'; return; }
    if (!result.data.length) { container.textContent = 'No uncertain profile links found.'; return; }
    for (const group of result.data) {
        const row = document.createElement('p');
        row.textContent = `${group.reason}: ${group.records.map(record => record.plate_number).join(', ')}`;
        container.appendChild(row);
    }
}
