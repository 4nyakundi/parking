/**
 * Mombasa Mall Basement Parking - Admin Panel Controller
 * Handles SPA navigation, Chart.js integrations, real-time metrics, filters, and reports.
 */

let hourlyChart = null;
let destChart = null;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Navigation routing
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const section = link.getAttribute('data-section');
            navigateTo(section);
        });
    });

    // 2. Initialize Overview on load
    navigateTo('overview');

    // 3. Periodic refresh for live stats
    setInterval(() => {
        const activeSec = document.querySelector('.section-content.active');
        if (activeSec && activeSec.id === 'sec-overview') {
            loadOverviewStats();
        }
    }, 5000);
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
        reconcile: 'Discrepancy Reconciliation & Drift Resolution',
        settings: 'System Configuration & Hardware Settings',
        backups: 'Database Backup & Recovery Manager',
        health: 'Hardware Diagnostics & Service Health',
        dpa: 'Data Protection & Privacy Compliance'
    };
    const titleEl = document.getElementById('topNavTitle');
    if (titleEl && titles[sectionId]) {
        titleEl.textContent = titles[sectionId];
    }

    // Load data for section
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
            loadReconcileList();
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
    try {
        const res = await fetch('../api/stats/live.php');
        const json = await res.json();
        if (json.ok && json.data) {
            const d = json.data;
            document.getElementById('ovTotal').textContent = d.capacity;
            document.getElementById('ovOccupied').textContent = d.occupied;
            document.getElementById('ovAvailable').textContent = d.available;
            document.getElementById('ovEntries').textContent = d.today_entries;
            document.getElementById('ovExits').textContent = d.today_exits;
            document.getElementById('ovOverstay').textContent = d.overstay_count;
            document.getElementById('ovAvgStay').textContent = `${d.avg_stay_mins}m`;
        }
    } catch (e) {
        console.error('Error loading live stats:', e);
    }
}

async function loadHourlyChart() {
    try {
        const res = await fetch('../api/stats/hourly.php');
        const json = await res.json();
        if (json.ok && json.data && window.Chart) {
            const ctx = document.getElementById('chartHourlyTraffic');
            if (!ctx) return;

            if (hourlyChart) hourlyChart.destroy();

            hourlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: json.data.labels,
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
    } catch (e) {
        console.error('Error loading hourly chart:', e);
    }
}

// --------------------------------------------------------------------
// 2. ALL SESSIONS SECTION
// --------------------------------------------------------------------
let currentSessionPage = 1;

async function loadSessions(page = 1) {
    currentSessionPage = page;
    const plate = document.getElementById('filterPlate')?.value || '';
    const ticket = document.getElementById('filterTicket')?.value || '';
    const status = document.getElementById('filterStatus')?.value || '';
    const dateFrom = document.getElementById('filterDateFrom')?.value || '';
    const dateTo = document.getElementById('filterDateTo')?.value || '';

    const url = `../api/admin/sessions.php?page=${page}&limit=15&plate=${encodeURIComponent(plate)}&ticket=${encodeURIComponent(ticket)}&status=${encodeURIComponent(status)}&date_from=${dateFrom}&date_to=${dateTo}`;

    try {
        const res = await fetch(url);
        const json = await res.json();
        const tbody = document.getElementById('sessionsTableBody');
        if (!tbody) return;

        if (!json.ok || json.data.sessions.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:30px; color:#64748b;">No matching parking records found.</td></tr>`;
            return;
        }

        tbody.innerHTML = '';
        json.data.sessions.forEach(s => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong style="color:var(--blue);">${s.ticket_id}</strong></td>
                <td><span style="display:inline-block; font-family:monospace; font-size:12px; font-weight:800; background:#eef5fc; color:#0d2b4e; border:1px solid #b0cfec; border-radius:4px; padding:2px 8px; letter-spacing:0.04em;">${s.formatted_plate}</span></td>
                <td>${s.driver_name}</td>
                <td>${s.destination}</td>
                <td>${s.entry_time}</td>
                <td>${s.exit_time || '<span class="badge active">Inside</span>'}</td>
                <td><span class="badge ${s.status === 'ACTIVE' ? 'active' : 'completed'}">${s.status}</span></td>
                <td>
                    <button class="btn-primary" style="padding:5px 12px; font-size:12px;" onclick="viewSessionDetail(${s.id})">View</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Pagination info
        const pag = document.getElementById('sessionsPagination');
        if (pag) {
            pag.innerHTML = `Page ${json.data.page} of ${json.data.pages} (${json.data.total} total sessions)
                ${json.data.page > 1 ? `<button class="btn-primary" style="padding:4px 8px; margin-left:10px;" onclick="loadSessions(${page - 1})">Prev</button>` : ''}
                ${json.data.page < json.data.pages ? `<button class="btn-primary" style="padding:4px 8px; margin-left:6px;" onclick="loadSessions(${page + 1})">Next</button>` : ''}
            `;
        }
    } catch (e) {
        console.error('Error loading sessions:', e);
    }
}

async function viewSessionDetail(sessionId) {
    try {
        const res = await fetch(`../api/admin/sessions.php?action=detail&id=${sessionId}`);
        const json = await res.json();
        if (json.ok && json.data) {
            const s = json.data.session;
            const modal = document.getElementById('sessionDetailModal');
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
            auditList.innerHTML = '';
            json.data.audits.forEach(a => {
                const li = document.createElement('li');
                li.style.marginBottom = '6px';
                li.innerHTML = `[${a.created_at}] <strong>${a.action}</strong> by ${a.user_name || 'System'}`;
                auditList.appendChild(li);
            });

            modal.classList.add('open');
        }
    } catch (e) {
        alert('Failed to load session details.');
    }
}

async function saveSessionNotes() {
    const btn = document.getElementById('btnSaveNotes');
    const id = btn.getAttribute('data-id');
    const notes = document.getElementById('detNotes').value;

    try {
        const res = await fetch('../api/admin/sessions.php?action=update_notes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: id, notes: notes })
        });
        const json = await res.json();
        if (json.ok) {
            alert('Notes saved successfully.');
            closeAdminModal('sessionDetailModal');
            loadSessions(currentSessionPage);
        }
    } catch (e) {
        alert('Failed to save notes.');
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
    try {
        const res = await fetch(`../api/admin/visitors.php?page=${page}`);
        const json = await res.json();
        const tbody = document.getElementById('visitorsTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';
        json.data.visitors.forEach(v => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span style="display:inline-block; font-family:monospace; font-size:12px; font-weight:800; background:#eef5fc; color:#0d2b4e; border:1px solid #b0cfec; border-radius:4px; padding:2px 8px; letter-spacing:0.04em;">${v.formatted_plate}</span></td>
                <td>${v.driver_name}</td>
                <td>${v.driver_phone}</td>
                <td>${v.destination}</td>
                <td>${v.created_at}</td>
                <td><span class="badge ${v.status === 'APPROVED' ? 'active' : (v.status === 'REJECTED' ? 'blacklisted' : 'staff')}">${v.status}</span></td>
                <td>${v.handled_by_name}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error('Error loading visitors:', e);
    }
}

// --------------------------------------------------------------------
// 4. REGISTERED VEHICLES
// --------------------------------------------------------------------
async function loadVehicles() {
    try {
        const res = await fetch('../api/admin/vehicles.php?action=list');
        const json = await res.json();
        const tbody = document.getElementById('vehiclesTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';
        json.data.forEach(v => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span style="display:inline-block; font-family:monospace; font-size:12px; font-weight:800; background:#eef5fc; color:#0d2b4e; border:1px solid #b0cfec; border-radius:4px; padding:2px 8px; letter-spacing:0.04em;">${v.formatted_plate}</span></td>
                <td>${v.owner_name || 'N/A'}</td>
                <td>${v.phone || 'N/A'}</td>
                <td><span class="badge ${v.category}">${v.category.toUpperCase()}</span></td>
                <td>${v.notes || ''}</td>
                <td>
                    <button class="btn-primary" style="padding:4px 10px; font-size:12px;" onclick="openVehicleModal(${JSON.stringify(v).replace(/"/g, '&quot;')})">Edit</button>
                    <button class="btn-primary ${v.is_active ? 'btn-danger' : 'btn-success'}" style="padding:4px 10px; font-size:12px; margin-left:4px;" onclick="toggleVehicleActive(${v.id})">
                        ${v.is_active ? 'Deactivate' : 'Activate'}
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error('Error loading vehicles:', e);
    }
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

    try {
        const res = await fetch('../api/admin/vehicles.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.ok) {
            closeAdminModal('vehicleModal');
            loadVehicles();
        } else {
            alert(json.error || 'Failed to save vehicle');
        }
    } catch (e) {
        alert('Network error.');
    }
}

async function toggleVehicleActive(id) {
    if (!confirm('Change active status of this vehicle?')) return;
    try {
        await fetch('../api/admin/vehicles.php?action=toggle_active', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        loadVehicles();
    } catch (e) {}
}

// --------------------------------------------------------------------
// 5. USERS & ROLES
// --------------------------------------------------------------------
async function loadUsers() {
    try {
        const res = await fetch('../api/admin/users.php?action=list');
        const json = await res.json();
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

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
                <td><strong>${u.full_name}</strong></td>
                <td>${u.username || '<span style="color:#64748b;">(PIN only)</span>'}</td>
                <td><span class="badge ${u.role === 'admin' ? 'vip' : 'active'}">${u.role.toUpperCase()}</span></td>
                <td>${u.phone || 'N/A'}</td>
                <td>${u.is_active ? '<span class="badge active" style="font-size:11px;">Active</span>' : '<span class="badge danger" style="font-size:11px;">Inactive</span>'}</td>
                <td>${u.last_login_at || 'Never'}</td>
                <td>${actionsHtml}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error('Error loading users:', e);
    }
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

    try {
        const res = await fetch('../api/admin/users.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.ok) {
            closeAdminModal('userModal');
            loadUsers();
        } else {
            alert(json.error || 'Failed to save user');
        }
    } catch (e) {
        alert('Network error.');
    }
}

async function toggleUserActive(id) {
    try {
        const res = await fetch('../api/admin/users.php?action=toggle_active', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const json = await res.json();
        if (json.ok) loadUsers();
        else alert(json.error);
    } catch (e) {}
}

// --------------------------------------------------------------------
// 6. DESTINATIONS
// --------------------------------------------------------------------
async function loadDestinations() {
    try {
        const res = await fetch('../api/admin/destinations.php?action=list');
        const json = await res.json();
        const tbody = document.getElementById('destinationsTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';
        json.data.forEach(d => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    ${d.unit_code ? `<span class="badge" style="background:#eef5fc; color:#116FC7; border:1px solid #b0cfec; margin-right:6px;">${d.unit_code}</span>` : ''}
                    <strong>${d.name}</strong>
                </td>
                <td><span class="badge" style="background:#f0f6fc; color:#2e5c8a; border:1px solid #b0cfec;">${d.floor_level || 'Ground Floor'}</span></td>
                <td><span style="color:#6b92b8; font-size:12px;">${d.category || 'Retail'}</span></td>
                <td>Order #${d.sort_order}</td>
                <td>${d.is_active ? '<span class="badge active">Active</span>' : '<span class="badge" style="background:#f1f5f9; color:#94a3b8; border:1px solid #cbd5e1;">Disabled</span>'}</td>
                <td>
                    <button class="btn-primary" style="padding:4px 10px; font-size:11px;" onclick="toggleDestActive(${d.id})">
                        ${d.is_active ? 'Disable' : 'Enable'}
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {}
}

async function toggleDestActive(id) {
    await fetch('../api/admin/destinations.php?action=toggle_active', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    });
    loadDestinations();
}

// --------------------------------------------------------------------
// 7. REPORTS
// --------------------------------------------------------------------
async function loadReports() {
    const from = document.getElementById('repDateFrom')?.value || new Date().toISOString().split('T')[0];
    const to = document.getElementById('repDateTo')?.value || new Date().toISOString().split('T')[0];

    try {
        const res = await fetch(`../api/admin/reports.php?type=summary&date_from=${from}&date_to=${to}`);
        const json = await res.json();
        if (json.ok && json.data) {
            const s = json.data.summary;
            document.getElementById('repTotalIn').textContent = s.total_entries;
            document.getElementById('repTotalOut').textContent = s.total_exits;
            document.getElementById('repAvgStay').textContent = `${s.avg_duration_mins}m`;
            document.getElementById('repPeak').textContent = s.peak_hour;

            // Destination Breakdown Pie
            if (window.Chart && json.data.destinations) {
                const ctx = document.getElementById('chartDestinations');
                if (ctx) {
                    if (destChart) destChart.destroy();
                    destChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: json.data.destinations.map(d => d.destination),
                            datasets: [{
                                data: json.data.destinations.map(d => d.count),
                                backgroundColor: ['#116FC7', '#609FDA', '#0a6b43', '#d97706', '#6d28d9', '#2e5c8a', '#B0CFEC'],
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { 
                                legend: { 
                                    position: 'bottom',
                                    labels: { color: '#0d2b4e', font: { weight: '600' } } 
                                } 
                            }
                        }
                    });
                }
            }
        }

        // Shift Handover
        const shiftRes = await fetch(`../api/admin/reports.php?type=shift&date_from=${from}&date_to=${to}`);
        const shiftJson = await shiftRes.json();
        const tbody = document.getElementById('shiftTableBody');
        if (tbody && shiftJson.ok) {
            tbody.innerHTML = '';
            shiftJson.data.forEach(g => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${g.guard_name}</strong></td>
                    <td style="color:var(--green-lt); font-weight:800;">${g.entries_approved}</td>
                    <td style="color:var(--blue); font-weight:800;">${g.exits_cleared}</td>
                    <td style="color:var(--red-lt); font-weight:800;">${g.rejections}</td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) {
        console.error('Reports error:', e);
    }
}

// --------------------------------------------------------------------
// 8. AUDIT LOG
// --------------------------------------------------------------------
async function loadAuditLogs(page = 1) {
    try {
        const res = await fetch(`../api/admin/audit.php?page=${page}`);
        const json = await res.json();
        const tbody = document.getElementById('auditTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';
        json.data.logs.forEach(l => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${l.created_at}</td>
                <td><strong>${l.user_name}</strong> (${l.user_role})</td>
                <td><span class="badge active">${l.action}</span></td>
                <td>${l.entity} (#${l.entity_id || '-'})</td>
                <td><code style="font-size:12px; color:var(--text-sub); background:#eef5fc; padding:2px 6px; border-radius:4px; border:1px solid #b0cfec;">${JSON.stringify(l.new_value || l.old_value || {})}</code></td>
                <td>${l.ip}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {}
}

// --------------------------------------------------------------------
// 9. RECONCILE
// --------------------------------------------------------------------
async function loadReconcileList() {
    try {
        const res = await fetch('../api/admin/reconcile.php?action=list');
        const json = await res.json();
        const tbody = document.getElementById('reconcileTableBody');
        if (!tbody) return;

        if (!json.ok || json.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--green-lt); font-weight:600;">All active sessions are within normal duration. Zero mismatches found.</td></tr>`;
            return;
        }

        tbody.innerHTML = '';
        json.data.forEach(s => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span style="display:inline-block; font-family:monospace; font-size:12px; font-weight:800; background:#eef5fc; color:#0d2b4e; border:1px solid #b0cfec; border-radius:4px; padding:2px 8px; letter-spacing:0.04em;">${s.formatted_plate}</span></td>
                <td><strong style="color:var(--blue);">${s.ticket_id}</strong></td>
                <td>${s.driver_name}</td>
                <td>${s.entry_time}</td>
                <td><span class="badge danger">${s.hours_active} hours</span></td>
                <td>
                    <button class="btn-primary btn-warning" style="padding:5px 12px; font-size:12px;" onclick="forceReconcileSession(${s.id})">Force Close & Clear</button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {}
}

async function forceReconcileSession(id) {
    const reason = prompt('Enter reconciliation audit reason (e.g., Physical count confirmed vehicle already left):');
    if (!reason) return;

    try {
        const res = await fetch('../api/admin/reconcile.php?action=force_close', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: id, reason: reason })
        });
        const json = await res.json();
        if (json.ok) {
            alert(json.message);
            loadReconcileList();
        } else {
            alert(json.error);
        }
    } catch (e) {
        alert('Network error.');
    }
}

// --------------------------------------------------------------------
// 10. SYSTEM SETTINGS
// --------------------------------------------------------------------
async function loadSettings() {
    try {
        const res = await fetch('../api/admin/settings.php?action=get');
        const json = await res.json();
        if (json.ok && json.data) {
            const d = json.data;
            document.getElementById('setCapacity').value = d.capacity || '60';
            document.getElementById('setOverstay').value = d.overstay_hours || '8';
            document.getElementById('setMallName').value = d.mall_name || 'Mombasa Mall Basement Parking';
            document.getElementById('setPrinterName').value = d.printer_name || 'POS80';
            document.getElementById('setPrinterWidth').value = d.printer_paper_width || '80';
            document.getElementById('setAlprConf').value = d.alpr_confidence_threshold || '0.70';
            document.getElementById('setAlprDebounce').value = d.alpr_debounce_seconds || '15';
            document.getElementById('setCloudUrl').value = d.cloud_sync_url || '';
        }
    } catch (e) {}
}

async function saveSettings() {
    const payload = {
        settings: {
            capacity: document.getElementById('setCapacity').value,
            overstay_hours: document.getElementById('setOverstay').value,
            mall_name: document.getElementById('setMallName').value,
            printer_name: document.getElementById('setPrinterName').value,
            printer_paper_width: document.getElementById('setPrinterWidth').value,
            alpr_confidence_threshold: document.getElementById('setAlprConf').value,
            alpr_debounce_seconds: document.getElementById('setAlprDebounce').value,
            cloud_sync_url: document.getElementById('setCloudUrl').value,
        }
    };

    try {
        const res = await fetch('../api/admin/settings.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.ok) alert('Settings saved successfully.');
    } catch (e) {
        alert('Network error.');
    }
}

// --------------------------------------------------------------------
// 11. BACKUPS
// --------------------------------------------------------------------
async function loadBackups() {
    try {
        const res = await fetch('../api/admin/backups.php?action=list');
        const json = await res.json();
        const tbody = document.getElementById('backupsTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';
        json.data.forEach(b => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${b.filename}</strong></td>
                <td>${(b.size / 1024).toFixed(1)} KB</td>
                <td>${b.created_at}</td>
                <td>${b.created_by_name || 'Nightly Auto'}</td>
                <td>
                    <a href="../api/admin/backups.php?action=download&id=${b.id}" class="btn-primary" style="padding:4px 10px; font-size:12px; text-decoration:none;">Download</a>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {}
}

async function triggerBackupNow() {
    const btn = document.getElementById('btnBackupNow');
    btn.disabled = true;
    btn.textContent = 'Creating Backup...';

    const defaultBtnHtml = '<svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>Backup Database Now';

    try {
        const res = await fetch('../api/admin/backups.php?action=create', { method: 'POST' });
        const json = await res.json();
        btn.disabled = false;
        btn.innerHTML = defaultBtnHtml;

        if (json.ok) {
            alert(json.data.message);
            loadBackups();
        } else {
            alert(json.error || 'Backup creation failed.');
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = defaultBtnHtml;
        alert('Failed to trigger backup.');
    }
}

// --------------------------------------------------------------------
// 12. HEALTH & ALPR SIMULATION
// --------------------------------------------------------------------
async function loadHealthStatus() {
    try {
        const res = await fetch('../api/health.php');
        const json = await res.json();
        if (json.ok && json.devices) {
            const dev = json.devices;
            document.getElementById('healthDb').textContent = `${dev.database.status} - ${dev.database.message}`;
            document.getElementById('healthPrinter').textContent = `${dev.printer.status} - ${dev.printer.message}`;
            document.getElementById('healthCamIn').textContent = `${dev.entrance_cam.status} - ${dev.entrance_cam.message}`;
            document.getElementById('healthCamOut').textContent = `${dev.exit_cam.status} - ${dev.exit_cam.message}`;
            document.getElementById('healthWorker').textContent = `${dev.alpr_worker.status} - ${dev.alpr_worker.message}`;
            document.getElementById('healthInternet').textContent = `${dev.internet.status} - ${dev.internet.message}`;
            document.getElementById('healthCloud').textContent = `${dev.cloud_sync.status} - ${dev.cloud_sync.message}`;
        }
    } catch (e) {}
}

async function simulateAlprDetection() {
    const cam = document.getElementById('simCam').value;
    const plate = document.getElementById('simPlate').value.trim();

    if (!plate) {
        alert('Enter a plate number to simulate');
        return;
    }

    try {
        const res = await fetch('../api/admin/simulate_alpr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ camera: cam, plate_number: plate, confidence: 0.94 })
        });
        const json = await res.json();
        if (json.ok) {
            alert(`✓ Simulated ${cam} camera detection for ${json.data.formatted_plate || plate} sent to system!`);
        } else {
            alert('Simulation error: ' + json.error);
        }
    } catch (e) {
        alert('Failed to simulate ALPR.');
    }
}

// --------------------------------------------------------------------
// 13. KENYA DATA PROTECTION ACT (DPA 2019)
// --------------------------------------------------------------------
async function searchDpaData() {
    const q = document.getElementById('dpaSearchQuery').value.trim();
    if (!q) {
        alert('Enter a phone number or plate number to lookup.');
        return;
    }

    try {
        const res = await fetch(`../api/admin/dpa.php?q=${encodeURIComponent(q)}`);
        const json = await res.json();
        const pre = document.getElementById('dpaResultsPre');
        if (!pre) return;

        if (json.ok && json.data) {
            pre.textContent = JSON.stringify(json.data, null, 2);
        } else {
            pre.textContent = json.error || 'No records found.';
        }
    } catch (e) {
        alert('DPA query error.');
    }
}

function closeAdminModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('open');
}
