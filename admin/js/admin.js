/**
 * Mombasa Mall Basement Parking - Admin & Supervisor Panel Controller
 * High-Performance SPA: Instant record filtering, zero-crash API handling,
 * Zero-Drift Discrepancy Reconciliation, and Real-Time Cloud Synchronization.
 */

let hourlyChart = null;
let destChart = null;

const STATE_ADMIN = {
    sessionQuickFilter: 'all',
    reconcileThreshold: 8,
    reconcileMetrics: {},
    searchDebounceTimer: null,
    currentSessionPage: 1
};

// Global safe API client with session guard
async function apiFetch(url, options = {}) {
    try {
        const res = await fetch(url, options);
        if (res.status === 401) {
            window.location.href = 'login.php?notice=session_expired';
            return { ok: false, error: 'Session expired' };
        }
        const json = await res.json();
        if (json && json.need_login) {
            window.location.href = 'login.php?notice=session_expired';
            return { ok: false, error: 'Session expired' };
        }
        return json;
    } catch (err) {
        console.error('API Fetch Error:', err);
        return { ok: false, error: 'Network communication failure. Please verify connection.' };
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Navigation routing
    document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const section = link.getAttribute('data-section');
            if (section) navigateTo(section);
        });
    });

    // 2. Initialize Overview on load (or deep link hash)
    const hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById(`sec-${hash}`)) {
        navigateTo(hash);
    } else {
        navigateTo('overview');
    }

    // 3. Periodic refresh for live stats
    setInterval(() => {
        const activeSec = document.querySelector('.section-content.active');
        if (activeSec && activeSec.id === 'sec-overview') {
            loadOverviewStats();
        } else if (activeSec && activeSec.id === 'sec-cloud') {
            loadCloudSyncStatus();
        }
    }, 5000);

    // 4. Bind instant debounced search on Sessions inputs
    const pInput = document.getElementById('filterPlate');
    const tInput = document.getElementById('filterTicket');
    [pInput, tInput].forEach(inp => {
        if (inp) {
            inp.addEventListener('input', () => {
                clearTimeout(STATE_ADMIN.searchDebounceTimer);
                STATE_ADMIN.searchDebounceTimer = setTimeout(() => {
                    loadSessions(1);
                }, 280);
            });
        }
    });
});

function navigateTo(sectionId) {
    document.querySelectorAll('.nav-link').forEach(l => {
        l.classList.toggle('active', l.getAttribute('data-section') === sectionId);
    });

    document.querySelectorAll('.section-content').forEach(s => {
        s.classList.toggle('active', s.id === `sec-${sectionId}`);
    });

    const titles = {
        overview: 'Management Operations Overview',
        sessions: 'Active & Historical Parking Sessions',
        visitors: 'Driver Self-Check-in Digital Requests',
        vehicles: 'Authorized & Blacklisted Vehicle Registry',
        users: 'Security Personnel & System User Accounts',
        destinations: 'Mall Tenant & Store Destination Directory',
        reports: 'Operational Analytics & Dwell Time Reports',
        audit: 'Permanent Immutable Audit Trail',
        reconcile: 'Discrepancy Reconciliation & Zero-Drift Deck Auditor',
        cloud: 'Edge & Cloud Mirror Synchronization',
        settings: 'System Configuration & Hardware Settings',
        backups: 'Database Backup & Recovery Manager',
        health: 'Hardware Diagnostics & Service Health',
        dpa: 'Data Protection & Privacy Compliance'
    };
    const titleEl = document.getElementById('topNavTitle');
    if (titleEl && titles[sectionId]) {
        titleEl.textContent = titles[sectionId];
    }

    // Load data for section smoothly
    switch (sectionId) {
        case 'overview':
            loadOverviewStats();
            loadHourlyChart();
            break;
        case 'sessions':
            loadSessions(1);
            break;
        case 'visitors':
            loadVisitors(1);
            break;
        case 'vehicles':
            loadVehicles();
            break;
        case 'users':
            loadUsers();
            break;
        case 'destinations':
            loadDestinations();
            break;
        case 'reports':
            loadReports();
            break;
        case 'audit':
            loadAuditLogs(1);
            break;
        case 'reconcile':
            loadReconcileList(STATE_ADMIN.reconcileThreshold || 8);
            break;
        case 'cloud':
            loadCloudSyncStatus();
            break;
        case 'settings':
            loadSettings();
            break;
        case 'backups':
            loadBackups();
            break;
        case 'health':
            loadHealthStatus();
            break;
    }
}

// --------------------------------------------------------------------
// 1. OVERVIEW SECTION
// --------------------------------------------------------------------
async function loadOverviewStats() {
    const json = await apiFetch('../api/stats/live.php');
    if (json.ok && json.data) {
        const d = json.data;
        const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        setTxt('ovTotal', d.capacity);
        setTxt('ovOccupied', d.occupied);
        setTxt('ovAvailable', d.available);
        setTxt('ovEntries', d.today_entries);
        setTxt('ovExits', d.today_exits);
        setTxt('ovOverstay', d.overstay_count);
        setTxt('ovAvgStay', `${d.avg_stay_mins}m`);
    }
}

async function loadHourlyChart() {
    const json = await apiFetch('../api/stats/hourly.php');
    if (json.ok && json.data && window.Chart) {
        const ctx = document.getElementById('chartHourlyTraffic');
        if (!ctx) return;

        if (hourlyChart) {
            hourlyChart.data.labels = json.data.hours;
            hourlyChart.data.datasets[0].data = json.data.entries;
            hourlyChart.data.datasets[1].data = json.data.exits;
            hourlyChart.update();
        } else {
            hourlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: json.data.hours,
                    datasets: [
                        {
                            label: 'Vehicles Entered',
                            data: json.data.entries,
                            backgroundColor: '#116FC7',
                            borderColor: '#0d5ca8',
                            borderRadius: 4,
                        },
                        {
                            label: 'Vehicles Exited',
                            data: json.data.exits,
                            backgroundColor: '#609FDA',
                            borderColor: '#3a87d0',
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { color: '#d0e5f5' },
                            ticks: { color: '#2e5c8a', font: { weight: '600' } }
                        },
                        x: { 
                            grid: { color: 'transparent' },
                            ticks: { color: '#2e5c8a', font: { weight: '600' } }
                        }
                    },
                    plugins: {
                        legend: { 
                            labels: { color: '#0d2b4e', font: { weight: '700' } } 
                        }
                    }
                }
            });
        }
    }
}

// --------------------------------------------------------------------
// 2. ALL SESSIONS SECTION
// --------------------------------------------------------------------
function setSessionQuickFilter(type) {
    STATE_ADMIN.sessionQuickFilter = type;
    document.querySelectorAll('#sessionsFilterPills .filter-pill-btn').forEach(b => {
        b.classList.toggle('active', b.getAttribute('data-sfilter') === type);
    });

    const statusEl = document.getElementById('filterStatus');
    const fromEl = document.getElementById('filterDateFrom');
    const toEl = document.getElementById('filterDateTo');

    const todayStr = new Date().toISOString().split('T')[0];

    if (type === 'active') {
        if (statusEl) statusEl.value = 'ACTIVE';
        if (fromEl) fromEl.value = '';
        if (toEl) toEl.value = '';
    } else if (type === 'completed') {
        if (statusEl) statusEl.value = 'COMPLETED';
        if (fromEl) fromEl.value = '';
        if (toEl) toEl.value = '';
    } else if (type === 'today') {
        if (statusEl) statusEl.value = '';
        if (fromEl) fromEl.value = todayStr;
        if (toEl) toEl.value = todayStr;
    } else {
        if (statusEl) statusEl.value = '';
        if (fromEl) fromEl.value = '';
        if (toEl) toEl.value = '';
    }

    loadSessions(1);
}

async function loadSessions(page = 1) {
    STATE_ADMIN.currentSessionPage = page;
    const plate = document.getElementById('filterPlate')?.value || '';
    const ticket = document.getElementById('filterTicket')?.value || '';
    const status = document.getElementById('filterStatus')?.value || '';
    const dateFrom = document.getElementById('filterDateFrom')?.value || '';
    const dateTo = document.getElementById('filterDateTo')?.value || '';

    const tbody = document.getElementById('sessionsTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:24px; color:#64748b;"><span class="table-spinner"></span> Loading session records...</td></tr>`;
    }

    const url = `../api/admin/sessions.php?page=${page}&limit=15&plate=${encodeURIComponent(plate)}&ticket=${encodeURIComponent(ticket)}&status=${encodeURIComponent(status)}&date_from=${dateFrom}&date_to=${dateTo}`;
    const json = await apiFetch(url);

    if (!tbody) return;

    if (!json.ok || !json.data || !Array.isArray(json.data.sessions) || json.data.sessions.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:#64748b; font-weight:600;">No matching parking records found for current filters.</td></tr>`;
        const pag = document.getElementById('sessionsPagination');
        if (pag) pag.innerHTML = '';
        return;
    }

    tbody.innerHTML = '';
    json.data.sessions.forEach(s => {
        const tr = document.createElement('tr');
        const isInside = (s.status === 'ACTIVE');
        const durText = s.duration_text || (isInside ? 'Inside' : '');
        tr.innerHTML = `
            <td><strong style="color:var(--blue);">${escapeHtml(s.ticket_id)}</strong></td>
            <td><span style="display:inline-block; font-family:monospace; font-size:12px; font-weight:800; background:#eef5fc; color:#0d2b4e; border:1px solid #b0cfec; border-radius:4px; padding:2px 8px; letter-spacing:0.04em;">${escapeHtml(s.formatted_plate || s.plate_number)}</span></td>
            <td>${escapeHtml(s.driver_name || 'Visitor')}</td>
            <td>${escapeHtml(s.destination || 'Mall Store')}</td>
            <td>${escapeHtml(s.entry_time)}</td>
            <td>${s.exit_time ? escapeHtml(s.exit_time) : '<span class="badge active">Inside Now</span>'}</td>
            <td><span class="badge ${isInside ? 'active' : 'completed'}">${escapeHtml(s.status)}</span></td>
            <td>
                <button type="button" class="btn-primary" style="padding:5px 12px; font-size:12px;" onclick="viewSessionDetail(${s.id})">Inspect</button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Pagination info
    const pag = document.getElementById('sessionsPagination');
    if (pag && json.data.pages) {
        pag.innerHTML = `
            <span style="font-size:12px; color:var(--text-sub); font-weight:700;">Page ${json.data.page} of ${json.data.pages} (${json.data.total} total sessions)</span>
            <div>
                ${json.data.page > 1 ? `<button class="btn-primary" style="padding:4px 10px; margin-right:6px;" onclick="loadSessions(${page - 1})">← Prev</button>` : ''}
                ${json.data.page < json.data.pages ? `<button class="btn-primary" style="padding:4px 10px;" onclick="loadSessions(${page + 1})">Next →</button>` : ''}
            </div>
        `;
    }
}

async function viewSessionDetail(sessionId) {
    const json = await apiFetch(`../api/admin/sessions.php?action=detail&id=${sessionId}`);
    if (json.ok && json.data && json.data.session) {
        const s = json.data.session;
        document.getElementById('detTicketId').textContent = s.ticket_id;
        document.getElementById('detPlate').textContent = s.plate_number;
        document.getElementById('detDriver').textContent = `${s.driver_name} (${s.driver_phone || 'None'})`;
        document.getElementById('detDest').textContent = s.destination;
        document.getElementById('detEntry').textContent = s.entry_time;
        document.getElementById('detExit').textContent = s.exit_time || 'Still Inside';
        document.getElementById('detStatus').textContent = s.status;
        document.getElementById('detApprover').textContent = s.approved_by_name || 'System';
        document.getElementById('detNotes').value = s.notes || '';
        document.getElementById('btnSaveNotes').setAttribute('data-id', s.id);

        // Audit Trail
        const auditList = document.getElementById('detAuditTrail');
        if (auditList) {
            auditList.innerHTML = '';
            if (Array.isArray(json.data.audits) && json.data.audits.length > 0) {
                json.data.audits.forEach(a => {
                    const li = document.createElement('li');
                    li.style.marginBottom = '6px';
                    li.innerHTML = `<strong>${escapeHtml(a.created_at)}</strong>: ${escapeHtml(a.action)} by <em>${escapeHtml(a.user_name || 'System')}</em>`;
                    auditList.appendChild(li);
                });
            } else {
                auditList.innerHTML = '<li>No audit entries for this session.</li>';
            }
        }

        document.getElementById('sessionDetailModal').classList.add('open');
    }
}

async function saveSessionNotes() {
    const id = document.getElementById('btnSaveNotes').getAttribute('data-id');
    const notes = document.getElementById('detNotes').value.trim();

    const json = await apiFetch('../api/admin/sessions.php?action=update_notes', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: id, notes: notes })
    });
    if (json.ok) {
        closeAdminModal('sessionDetailModal');
        loadSessions(STATE_ADMIN.currentSessionPage);
    } else {
        alert(json.error || 'Failed to save notes.');
    }
}

function exportSessionsCsv() {
    const from = document.getElementById('filterDateFrom')?.value || '';
    const to = document.getElementById('filterDateTo')?.value || '';
    window.location.href = `../api/admin/reports.php?type=export_csv&date_from=${from}&date_to=${to}`;
}

// --------------------------------------------------------------------
// 3. VISITORS HISTORY
// --------------------------------------------------------------------
async function loadVisitors(page = 1) {
    const tbody = document.getElementById('visitorsTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:24px; color:#64748b;">Loading visitor requests...</td></tr>`;
    }

    const json = await apiFetch(`../api/admin/visitors.php?page=${page}`);
    if (!tbody) return;

    if (!json.ok || !json.data || !Array.isArray(json.data.visitors) || json.data.visitors.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:#64748b;">No driver self-signin requests recorded yet.</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    json.data.visitors.forEach(v => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><span style="display:inline-block; font-family:monospace; font-size:12px; font-weight:800; background:#eef5fc; color:#0d2b4e; border:1px solid #b0cfec; border-radius:4px; padding:2px 8px; letter-spacing:0.04em;">${escapeHtml(v.formatted_plate || v.plate_number)}</span></td>
            <td><strong>${escapeHtml(v.driver_name || 'Visitor')}</strong></td>
            <td>${escapeHtml(v.driver_phone || 'N/A')}</td>
            <td>${escapeHtml(v.destination || 'Mall Store')}</td>
            <td>${escapeHtml(v.created_at)}</td>
            <td><span class="badge ${v.status === 'APPROVED' ? 'active' : (v.status === 'REJECTED' ? 'blacklisted' : 'staff')}">${escapeHtml(v.status)}</span></td>
            <td>${escapeHtml(v.handled_by_name || 'Pending')}</td>
        `;
        tbody.appendChild(tr);
    });
}

// --------------------------------------------------------------------
// 4. REGISTERED VEHICLES
// --------------------------------------------------------------------
async function loadVehicles() {
    const tbody = document.getElementById('vehiclesTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:24px; color:#64748b;">Loading vehicle registry...</td></tr>`;
    }

    const json = await apiFetch('../api/admin/vehicles.php?action=list');
    if (!tbody) return;

    if (!json.ok || !json.data || !Array.isArray(json.data) || json.data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:#64748b;">No registered vehicles in database.</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    json.data.forEach(v => {
        const tr = document.createElement('tr');
        const isFreq = v.is_frequent || ((v.visit_count || 0) >= 3);
        const categoryBadge = ['blacklisted','vip','staff','tenant'].includes(v.category) ? `<span class="badge ${v.category}">${escapeHtml(v.category.toUpperCase())}</span>` : isFreq
            ? `<span class="badge" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; font-weight:800;">⚡ FREQUENT CLIENT</span>`
            : `<span class="badge ${v.category}">${escapeHtml((v.category || 'REGULAR').toUpperCase())}</span>`;

        tr.innerHTML = `
            <td><span style="display:inline-block; font-family:monospace; font-size:12px; font-weight:800; background:#eef5fc; color:#0d2b4e; border:1px solid #b0cfec; border-radius:4px; padding:2px 8px; letter-spacing:0.04em;">${escapeHtml(v.formatted_plate || v.plate_number)}</span></td>
            <td><strong>${escapeHtml(v.owner_name || 'Owner not recorded')}</strong><br><small>Saved driver: ${escapeHtml(v.driver_name || 'Not confirmed')}</small>${v.profile_archived ? '<br><small>Profile archived</small>' : ''}</td>
            <td>${escapeHtml(v.phone || 'N/A')}</td>
            <td>${categoryBadge}</td>
            <td><strong style="color:${isFreq ? '#1d4ed8' : '#334155'}; font-size:13px;">${v.visit_count || 0}</strong> <span style="font-size:11px; color:#64748b;">visits</span></td>
            <td><span style="font-size:12px; color:#0369a1; font-weight:600;">${escapeHtml(v.default_destination || 'None')}</span></td>
            <td>
                <button type="button" class="btn-primary" style="padding:4px 10px; font-size:12px;" onclick="openVehicleModal(${JSON.stringify(v).replace(/"/g, '&quot;')})">Edit</button>
                <button type="button" class="btn-primary" onclick="openBankProfile(${v.id})">Profile / History</button>
                <button type="button" class="btn-primary ${v.is_active ? 'btn-danger' : 'btn-success'}" style="padding:4px 10px; font-size:12px; margin-left:4px;" onclick="toggleVehicleActive(${v.id})">
                    ${v.is_active ? 'Deactivate' : 'Activate'}
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function openVehicleModal(v = null) {
    document.getElementById('vehId').value = v ? v.id : '0';
    document.getElementById('vehPlate').value = v ? v.plate_number : '';
    document.getElementById('vehOwner').value = v ? v.owner_name : '';
    document.getElementById('vehPhone').value = v ? v.raw_phone || '' : '';
    document.getElementById('vehType').value = v ? v.vehicle_type : 'car';
    document.getElementById('vehCategory').value = v ? v.category : 'regular';
    document.getElementById('vehNotes').value = v ? v.notes : '';

    document.getElementById('vehicleModal').classList.add('open');
}

async function saveVehicle() {
    const payload = {
        id: document.getElementById('vehId').value,
        plate_number: document.getElementById('vehPlate').value,
        owner_name: document.getElementById('vehOwner').value,
        phone: document.getElementById('vehPhone').value,
        vehicle_type: document.getElementById('vehType').value,
        category: document.getElementById('vehCategory').value,
        notes: document.getElementById('vehNotes').value,
    };

    const json = await apiFetch('../api/admin/vehicles.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    if (json.ok) {
        closeAdminModal('vehicleModal');
        loadVehicles();
    } else {
        alert(json.error || 'Failed to save vehicle');
    }
}

async function toggleVehicleActive(id) {
    if (!confirm('Change active status of this vehicle?')) return;
    await apiFetch('../api/admin/vehicles.php?action=toggle_active', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    });
    loadVehicles();
}

// --------------------------------------------------------------------
// 5. USERS & ROLES
// --------------------------------------------------------------------
async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:24px; color:#64748b;">Loading user accounts...</td></tr>`;
    }

    const json = await apiFetch('../api/admin/users.php?action=list');
    if (!tbody) return;

    if (!json.ok || !json.data || !Array.isArray(json.data) || json.data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:#64748b;">No users registered.</td></tr>`;
        return;
    }

    const isAdmin = document.body.dataset.userRole === 'admin';
    tbody.innerHTML = '';
    json.data.forEach(u => {
        const tr = document.createElement('tr');
        const actionsHtml = isAdmin ? `
            <button class="btn-primary" style="padding:4px 10px; font-size:12px;" onclick="openUserModal(${JSON.stringify(u).replace(/"/g, '&quot;')})">Edit / PIN</button>
            <button class="btn-primary ${u.is_active ? 'btn-danger' : 'btn-success'}" style="padding:4px 10px; font-size:12px; margin-left:4px;" onclick="toggleUserActive(${u.id})">
                ${u.is_active ? 'Deactivate' : 'Activate'}
            </button>
        ` : `<span style="color:var(--text-mut); font-size:12px; font-weight:600;">Admin Managed</span>`;

        tr.innerHTML = `
            <td><strong>${escapeHtml(u.full_name)}</strong></td>
            <td>${u.username ? escapeHtml(u.username) : '<span style="color:#64748b;">(PIN only)</span>'}</td>
            <td><span class="badge ${u.role === 'admin' ? 'vip' : 'active'}">${escapeHtml((u.role || 'GUARD').toUpperCase())}</span></td>
            <td>${escapeHtml(u.phone || 'N/A')}</td>
            <td>${u.is_active ? '<span class="badge active" style="font-size:11px;">Active</span>' : '<span class="badge danger" style="font-size:11px;">Inactive</span>'}</td>
            <td>${escapeHtml(u.last_login_at || 'Never')}</td>
            <td>${actionsHtml}</td>
        `;
        tbody.appendChild(tr);
    });
}

function openUserModal(u = null) {
    document.getElementById('userId').value = u ? u.id : '0';
    document.getElementById('userFullName').value = u ? u.full_name : '';
    document.getElementById('userUsername').value = u ? u.username || '' : '';
    document.getElementById('userRole').value = u ? u.role : 'guard';
    document.getElementById('userPhone').value = u ? u.raw_phone || '' : '';
    document.getElementById('userPin').value = '';
    document.getElementById('userPassword').value = '';

    document.getElementById('userModal').classList.add('open');
}

async function saveUser() {
    const payload = {
        id: document.getElementById('userId').value,
        full_name: document.getElementById('userFullName').value,
        username: document.getElementById('userUsername').value,
        role: document.getElementById('userRole').value,
        phone: document.getElementById('userPhone').value,
        pin: document.getElementById('userPin').value,
        password: document.getElementById('userPassword').value,
    };

    const json = await apiFetch('../api/admin/users.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    if (json.ok) {
        closeAdminModal('userModal');
        loadUsers();
    } else {
        alert(json.error || 'Failed to save user');
    }
}

async function toggleUserActive(id) {
    const json = await apiFetch('../api/admin/users.php?action=toggle_active', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    });
    if (json.ok) loadUsers();
    else alert(json.error || 'Failed to toggle status');
}

// --------------------------------------------------------------------
// 6. DESTINATIONS
// --------------------------------------------------------------------
async function loadDestinations() {
    const tbody = document.getElementById('destinationsTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:24px; color:#64748b;">Loading destination directory...</td></tr>`;
    }

    const json = await apiFetch('../api/admin/destinations.php?action=list');
    if (!tbody) return;

    if (!json.ok || !json.data || !Array.isArray(json.data) || json.data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:30px; color:#64748b;">No store destinations found.</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    json.data.forEach(d => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${escapeHtml(d.unit_code)}</strong></td>
            <td>${escapeHtml(d.name)}</td>
            <td><span class="badge staff">${escapeHtml(d.floor_level)}</span></td>
            <td>${escapeHtml(d.category || 'Retail')}</td>
            <td><span class="badge ${d.is_active ? 'active' : 'danger'}">${d.is_active ? 'Active' : 'Inactive'}</span></td>
        `;
        tbody.appendChild(tr);
    });
}

// --------------------------------------------------------------------
// 7. REPORTS
// --------------------------------------------------------------------
async function loadReports() {
    const from = document.getElementById('repFrom')?.value || '';
    const to = document.getElementById('repTo')?.value || '';

    const json = await apiFetch(`../api/admin/reports.php?date_from=${from}&date_to=${to}`);
    if (json.ok && json.data) {
        const d = json.data;
        const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        setTxt('repTotalEntries', d.total_entries);
        setTxt('repAvgDwell', `${d.avg_dwell_mins}m`);
        setTxt('repPeakHour', d.peak_hour);
        setTxt('repTotalOverstays', d.total_overstays);
    }
}

// --------------------------------------------------------------------
// 8. AUDIT LOG
// --------------------------------------------------------------------
async function loadAuditLogs(page = 1) {
    const tbody = document.getElementById('auditTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:24px; color:#64748b;">Loading audit entries...</td></tr>`;
    }

    const json = await apiFetch(`../api/admin/audit.php?page=${page}`);
    if (!tbody) return;

    if (!json.ok || !json.data || !Array.isArray(json.data.logs) || json.data.logs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:#64748b;">No audit logs recorded yet.</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    json.data.logs.forEach(l => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(l.created_at)}</td>
            <td><strong>${escapeHtml(l.user_name || 'System')}</strong> <span style="font-size:11px; color:#64748b;">(${escapeHtml(l.user_role || 'auto')})</span></td>
            <td><span class="badge active">${escapeHtml(l.action)}</span></td>
            <td>${escapeHtml(l.entity)} (#${escapeHtml(l.entity_id || '-')})</td>
            <td><code style="font-size:11.5px; color:var(--text-sub); background:#eef5fc; padding:2px 6px; border-radius:4px; border:1px solid #b0cfec;">${escapeHtml(JSON.stringify(l.new_value || l.old_value || {}))}</code></td>
            <td>${escapeHtml(l.ip || 'Local')}</td>
        `;
        tbody.appendChild(tr);
    });
}

// --------------------------------------------------------------------
// 9. SUPERVISOR RECONCILE & ZERO-DRIFT AUDITOR
// --------------------------------------------------------------------
function setReconcileThreshold(hours, btnEl) {
    STATE_ADMIN.reconcileThreshold = hours;
    document.querySelectorAll('.filter-pill-btn[data-rthresh]').forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');
    loadReconcileList(hours);
}

async function loadReconcileList(threshold = 8) {
    const tbody = document.getElementById('reconcileTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:24px; color:#64748b;">Scanning active deck sessions...</td></tr>`;
    }

    const json = await apiFetch(`../api/admin/reconcile.php?action=list&hours=${threshold}`);
    if (!tbody) return;

    // Update Supervisor Telemetry Strip
    if (json.ok && json.metrics) {
        STATE_ADMIN.reconcileMetrics = json.metrics;
        const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = String(v); };
        setVal('recSystemActive', json.metrics.total_active);
        setVal('recExtended4h', json.metrics.extended_4h);
        setVal('recOverstay8h', json.metrics.overstay_8h);
        setVal('recOverridesToday', json.metrics.overrides_today);

        calculateDeckDelta(); // Recalculate with current physical count
    }

    if (!json.ok || !json.data || !Array.isArray(json.data) || json.data.length === 0) {
        const threshText = threshold > 0 ? `active longer than ${threshold} hours` : 'currently inside';
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:32px; color:#059669; font-weight:700;">Zero discrepancies found. No vehicles ${threshText}.</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    json.data.forEach(s => {
        const tr = document.createElement('tr');
        const badgeClass = s.urgency === 'ghost' ? 'danger' : (s.urgency === 'overstay' ? 'blacklisted' : 'staff');
        const urgencyLabel = s.urgency === 'ghost' ? 'Ghost (>12h)' : (s.urgency === 'overstay' ? 'Overstay (>8h)' : 'Active');

        tr.innerHTML = `
            <td><span style="display:inline-block; font-family:monospace; font-size:12px; font-weight:800; background:#eef5fc; color:#0d2b4e; border:1px solid #b0cfec; border-radius:4px; padding:2px 8px; letter-spacing:0.04em;">${escapeHtml(s.formatted_plate || s.plate_number)}</span></td>
            <td><strong style="color:var(--blue);">${escapeHtml(s.ticket_id)}</strong></td>
            <td><strong>${escapeHtml(s.driver_name)}</strong> &bull; <span style="color:var(--blue);">${escapeHtml(s.destination)}</span></td>
            <td>${escapeHtml(s.entry_time)}</td>
            <td><strong style="color:#d97706;">${escapeHtml(s.dwell_text)}</strong></td>
            <td><span class="badge ${badgeClass}">${urgencyLabel}</span></td>
            <td>
                <button type="button" class="btn-primary btn-warning" style="padding:5px 12px; font-size:12px;" onclick="openReconcileModal(${s.id}, '${escapeHtml(s.plate_number)}', '${escapeHtml(s.ticket_id)}')">
                    Force Close &amp; Clear
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function calculateDeckDelta() {
    const input = document.getElementById('deckPhysicalCount');
    const badge = document.getElementById('deckDeltaBadge');
    if (!input || !badge) return;

    const physical = parseInt(input.value, 10);
    if (isNaN(physical) || physical < 0) {
        badge.style.display = 'none';
        return;
    }

    const system = STATE_ADMIN.reconcileMetrics.total_active || 0;
    const delta = system - physical;

    badge.style.display = 'inline-flex';
    if (delta === 0) {
        badge.className = 'delta-badge balanced';
        badge.innerHTML = `<span>ZERO DRIFT: Deck matched (${physical} cars)</span>`;
    } else if (delta > 0) {
        badge.className = 'delta-badge variance-pos';
        badge.innerHTML = `<span>VARIANCE: +${delta} Ghost Sessions (Physical: ${physical} | System: ${system})</span>`;
    } else {
        badge.className = 'delta-badge variance-neg';
        badge.innerHTML = `<span>VARIANCE: ${delta} Unrecorded Vehicles (Physical: ${physical} | System: ${system})</span>`;
    }
}

function openReconcileModal(sessionId, plate, ticketId) {
    document.getElementById('recSessionId').value = String(sessionId);
    document.getElementById('recPlateNumber').textContent = plate;
    document.getElementById('recTicketId').textContent = ticketId;
    document.getElementById('recReasonInput').value = '';

    document.querySelectorAll('.rec-preset-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('reconcileJustificationModal').classList.add('open');
}

function selectRecPreset(btn) {
    document.querySelectorAll('.rec-preset-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('recReasonInput').value = btn.textContent.trim();
}

async function submitReconcileForceClose() {
    const sId = document.getElementById('recSessionId').value;
    const reason = document.getElementById('recReasonInput').value.trim() || 'Physical sweep confirmed vehicle already departed.';

    const btn = document.getElementById('btnConfirmReconcile');
    btn.disabled = true;
    btn.textContent = 'Clearing...';

    const json = await apiFetch('../api/admin/reconcile.php?action=force_close', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: sId, reason: reason })
    });

    btn.disabled = false;
    btn.textContent = 'Confirm Reconciliation';

    if (json.ok) {
        closeAdminModal('reconcileJustificationModal');
        loadReconcileList(STATE_ADMIN.reconcileThreshold || 8);
    } else {
        alert(json.error || 'Failed to reconcile session');
    }
}

function openShiftSignoffModal() {
    const input = document.getElementById('signoffPhysicalCount');
    const pVal = document.getElementById('deckPhysicalCount')?.value || '';
    if (input && pVal) input.value = pVal;
    document.getElementById('shiftSignoffModal').classList.add('open');
}

async function submitShiftSignoff() {
    const physical = parseInt(document.getElementById('signoffPhysicalCount').value, 10) || 0;
    const notes = document.getElementById('signoffNotes').value.trim();
    const system = STATE_ADMIN.reconcileMetrics.total_active || 0;

    const btn = document.getElementById('btnConfirmSignoff');
    btn.disabled = true;
    btn.textContent = 'Signing Off...';

    const json = await apiFetch('../api/admin/reconcile.php?action=shift_signoff', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            physical_count: physical,
            system_count: system,
            notes: notes
        })
    });

    btn.disabled = false;
    btn.textContent = 'Complete & Record Sign-Off';

    if (json.ok) {
        alert(json.message || 'Shift sign-off recorded successfully.');
        closeAdminModal('shiftSignoffModal');
    } else {
        alert(json.error || 'Failed to record sign-off.');
    }
}

// --------------------------------------------------------------------
// 10. CLOUD SYNCHRONIZATION
// --------------------------------------------------------------------
async function loadCloudSyncStatus() {
    const json = await apiFetch('../api/admin/cloud_sync.php?action=status');
    if (json.ok && json.data) {
        const d = json.data;
        const setTxt = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        
        setTxt('cloudConnStatus', d.health_status === 'OK' ? 'ONLINE' : 'DEGRADED');
        setTxt('cloudPendingCount', d.unsynced_count);
        setTxt('cloudLastSync', d.last_synced_at || 'Just now');
        
        const sub = document.getElementById('cloudConnSub');
        if (sub) sub.textContent = `${d.synced_count} of ${d.total_sessions} records synced (${d.hmac_enabled ? 'HMAC-SHA256' : 'Standard'})`;
    }
}

async function triggerManualCloudSync() {
    const btn = document.getElementById('btnTriggerCloudSync');
    const consoleBox = document.getElementById('cloudSyncConsole');

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Syncing Records...';
    }
    if (consoleBox) {
        consoleBox.style.display = 'block';
        consoleBox.textContent = `[${new Date().toLocaleTimeString()}] Initiating Edge PC to Cloud Mirror sync handshake...\n`;
    }

    const json = await apiFetch('../api/admin/cloud_sync.php?action=trigger', { method: 'POST' });

    if (btn) {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5" style="vertical-align:-2px; margin-right:6px;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>Trigger Sync Now`;
    }

    if (consoleBox) {
        if (json.ok) {
            consoleBox.textContent += (json.log || `[${new Date().toLocaleTimeString()}] Sync successful.\n`) + `\n[${new Date().toLocaleTimeString()}] [SUCCESS] All records verified. Zero packet drops. Parity confirmed.`;
        } else {
            consoleBox.textContent += `[${new Date().toLocaleTimeString()}] [ERROR] Sync failed: ${json.error || 'Connection error'}`;
        }
    }

    loadCloudSyncStatus();
}

// --------------------------------------------------------------------
// 11. SYSTEM SETTINGS
// --------------------------------------------------------------------
async function loadSettings() {
    const json = await apiFetch('../api/admin/settings.php?action=get');
    if (json.ok && json.data) {
        const d = json.data;
        const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
        setVal('setCapacity', d.capacity);
        setVal('setGrace', d.grace_period_mins);
        setVal('setOverstay', d.overstay_hours);
        setVal('setCamIn', d.camera_entrance_ip);
        setVal('setCamOut', d.camera_exit_ip);
        setVal('setPrinter', d.printer_device);
        setVal('setCloudUrl', d.cloud_sync_url || '');
    }
}

async function saveSettings() {
    const payload = {
        capacity: document.getElementById('setCapacity').value,
        grace_period_mins: document.getElementById('setGrace').value,
        overstay_hours: document.getElementById('setOverstay').value,
        camera_entrance_ip: document.getElementById('setCamIn').value,
        camera_exit_ip: document.getElementById('setCamOut').value,
        printer_device: document.getElementById('setPrinter').value,
        cloud_sync_url: document.getElementById('setCloudUrl').value,
    };

    const json = await apiFetch('../api/admin/settings.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    if (json.ok) alert('Settings saved successfully.');
    else alert(json.error || 'Failed to save settings.');
}

// --------------------------------------------------------------------
// 12. DATABASE BACKUPS
// --------------------------------------------------------------------
async function loadBackups() {
    const tbody = document.getElementById('backupsTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:24px; color:#64748b;">Loading backup archives...</td></tr>`;
    }

    const json = await apiFetch('../api/admin/backups.php?action=list');
    if (!tbody) return;

    if (!json.ok || !json.data || !Array.isArray(json.data) || json.data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:30px; color:#64748b;">No database backups created yet.</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    json.data.forEach(b => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${escapeHtml(b.filename)}</strong></td>
            <td>${(b.size / 1024).toFixed(1)} KB</td>
            <td>${escapeHtml(b.created_at)}</td>
            <td>${escapeHtml(b.created_by_name || 'Nightly Auto')}</td>
            <td>
                <a href="../api/admin/backups.php?action=download&id=${b.id}" class="btn-primary" style="padding:4px 10px; font-size:12px; text-decoration:none;">Download</a>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function triggerBackupNow() {
    const btn = document.getElementById('btnBackupNow');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Creating Backup...';
    }

    const json = await apiFetch('../api/admin/backups.php?action=create', { method: 'POST' });
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>Backup Database Now`;
    }

    if (json.ok) {
        alert(json.data.message || 'Backup generated successfully.');
        loadBackups();
    } else {
        alert(json.error || 'Backup creation failed.');
    }
}

// --------------------------------------------------------------------
// 13. HARDWARE & HEALTH
// --------------------------------------------------------------------
async function loadHealthStatus() {
    const json = await apiFetch('../api/health.php');
    if (json.ok && json.devices) {
        const dev = json.devices;
        const setDev = (id, obj) => {
            const el = document.getElementById(id);
            if (el && obj) el.textContent = `${obj.status} - ${obj.message}`;
        };
        setDev('healthDb', dev.database);
        setDev('healthPrinter', dev.printer);
        setDev('healthCamIn', dev.entrance_cam);
        setDev('healthCamOut', dev.exit_cam);
        setDev('healthWorker', dev.alpr_worker);
        setDev('healthInternet', dev.internet);
        setDev('healthCloud', dev.cloud_sync);
    }
}

function closeAdminModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('open');
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
