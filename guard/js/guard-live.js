/* Dashboard display and refresh coordination. Does not control cameras or barriers. */
const GuardLive = (() => {
    let generation = 0, busy = false, timer = null, failures = 0, started = false;
    let initialized = false, snapshotNeeded = true, lastSnapshot = 0, lastSuccess = 0;
    let local = [], cloud = [], roster = [], deviceState = {}, offset = 0;
    let cloudState = 'Connecting', connection = 'Connecting';
    let printIssues = new Map();
    const printAttempts = new Set();
    let mutationVersion = 0;
    const nativeFetch = window.fetch.bind(window);
    const getRequests = new Map();

    // Existing action handlers keep their payloads; shared handling recognizes expired sessions.
    window.fetch = async function(input, options = {}) {
        const url = typeof input === 'string' ? input : input.url;
        const protectedApi = /\/api\//.test(url) && !/\/api\/auth\/(login|me)\.php/.test(url)
            && !/\/api\/driver\//.test(url);
        const epoch = generation;
        if (protectedApi && options.method?.toUpperCase() === 'POST' && /(?:approve-session|manual-checkin|clear-exit|reprint-ticket|requests\/action|gate\/reject)\.php/.test(url) && connection !== 'Connected') {
            throw new Error('Reconnect to the local server before recording this action.');
        }
        const isGet = !options.method || options.method.toUpperCase() === 'GET';
        const key = isGet && protectedApi ? url : null;
        if (key && getRequests.has(key)) return (await getRequests.get(key)).clone();
        const request = (async () => {
            const controller = new AbortController();
            const timeout = isGet ? setTimeout(() => controller.abort(), 12000) : null;
            try {
                const response = await nativeFetch(input, { ...options, cache: isGet ? 'no-store' : options.cache,
                    signal: options.signal || (isGet ? controller.signal : undefined) });
                if (protectedApi) {
                    let body;
                    try { body = await response.clone().json(); } catch (_) { /* normal error handling follows */ }
                    if (epoch !== generation) throw new Error('Outdated dashboard response');
                    if (response.status === 401 || body?.need_login) {
                        expire();
                        throw new Error('Session expired. Please sign in.');
                    }
                    if (!isGet && body?.ok) { mutationVersion++; snapshotNeeded = true; }
                }
                return response;
            } finally { clearTimeout(timeout); }
        })();
        if (key) getRequests.set(key, request);
        try { return (await request).clone(); }
        finally { if (key && getRequests.get(key) === request) getRequests.delete(key); }
    };

    async function json(url) {
        const response = await fetch(url);
        const body = await response.json();
        if (!response.ok || !body.ok) throw new Error(body.error || 'Refresh unavailable');
        return body;
    }
    function setText(id, text) { const el = document.getElementById(id); if (el) el.textContent = text; }
    function now() { return Date.now() + offset; }
    function setConnection(value) {
        connection = value;
        const strip = document.getElementById('dashboardLiveStatus');
        if (strip) strip.dataset.state = value.toLowerCase().replace(/ /g, '-');
        setText('edgeConnectionState', value);
        const banner = document.getElementById('offlineBanner');
        if (banner) {
            banner.classList.toggle('active', ['Reconnecting', 'Unavailable'].includes(value));
            banner.textContent = 'Local server unavailable. Showing last known data. Registration and exit recording need a connection.';
        }
        document.body.classList.toggle('guard-session-expired', value === 'Session expired');
        document.body.classList.toggle('guard-edge-unavailable', ['Reconnecting', 'Unavailable'].includes(value));
        devices(deviceState);
        freshness();
    }
    function freshness() {
        const seconds = lastSuccess ? Math.max(0, Math.floor((Date.now() - lastSuccess) / 1000)) : null;
        setText('dashboardFreshness', seconds === null ? 'Waiting for first update' : `Updated ${seconds}s ago`);
        if (started && seconds !== null && seconds > 15 && connection === 'Connected') setConnection('Reconnecting');
    }
    function expire() {
        if (!started && connection === 'Session expired') return;
        generation++;
        started = false;
        initialized = false;
        STATE.user = null;
        clearTimeout(timer);
        clearTimeout(STATE.firestoreRetryTimer);
        if (STATE.firestoreUnsubscribe) STATE.firestoreUnsubscribe();
        STATE.firestoreUnsubscribe = null;
        cloud = [];
        STATE.pendingRequestsMap = {};
        STATE.activeSessionsList = [];
        document.getElementById('insideTableBody')?.replaceChildren();
        document.getElementById('pendingCardsGrid')?.replaceChildren();
        printIssues.clear(); renderPrintIssues();
        document.querySelectorAll('.modal-overlay.open').forEach(el => el.classList.remove('open'));
        setConnection('Session expired');
        showPinModal();
        setText('pinSessionNotice', 'Your session ended. Enter your PIN and press Enter.');
    }
    function renderQueue() {
        const items = GuardLiveModel.merge(local, cloud, roster);
        STATE.latestPendingItems = items;
        STATE.pendingRequestsMap = Object.fromEntries(items.map(item => [String(item.id), item]));
        const grid = document.getElementById('pendingCardsGrid');
        if (!grid) return;
        if (items.length) renderPendingRequestsDiff(items);
        else grid.innerHTML = '<div class="queue-empty-box"><strong>No vehicles waiting</strong><p>New pending arrivals will appear here.</p></div>';
        const count = items.length;
        const badge = document.getElementById('pendingCountBadge');
        if (badge) { badge.textContent = String(count); badge.style.display = count ? 'inline-block' : 'none'; }
        setText('pnlPending', count);
        if (!count) updateQueueFilterCounts(0, 0, 0);
    }
    function renderRoster(items) {
        roster = items;
        STATE.activeSessionsList = items;
        // Server dwell fields tick continuously; only structural content needs rendering.
        const signature = JSON.stringify(items.map(({ dwell_minutes, dwell_seconds, dwell_string,
            remaining_minutes, overdue_minutes, ...item }) => item));
        if (signature !== STATE.rosterSignature || !initialized) {
            STATE.rosterSignature = signature;
            if (items.length) { renderActiveSessionsTable(items); startRosterLiveTimers(); }
            else {
                document.getElementById('insideTableBody').innerHTML = '<tr><td colspan="8" class="guard-empty">No vehicles currently parked.</td></tr>';
                updateRosterFilterCounters(0, 0, 0, 0);
            }
        }

    }
    function devices(incoming) {
        deviceState = incoming || {};
        const staleConnection = connection !== 'Connected';
        const entries = [
            ['printer', 'pnlPrinterStatus', 'dotPrinter', 'txtPrinter', 'Printer'],
            ['entrance_cam', 'pnlCam1Status', 'dotCamIn', 'txtCamIn', 'Entry'],
            ['exit_cam', 'pnlCam2Status', 'dotCamOut', 'txtCamOut', 'Exit'],
            ['alpr_worker', 'pnlAlprStatus', null, null, 'Recognition']
        ];
        for (const [key, panel, dotId, labelId, label] of entries) {
            const info = deviceState[key] ? { ...deviceState[key], device: key } : null;
            const state = GuardLiveModel.device(info, staleConnection);
            const value = key === 'printer' && state === 'Online' ? 'Last job OK' : state;
            setText(panel, value);
            const panelEl = document.getElementById(panel);
            if (panelEl) {
                panelEl.dataset.status = state.toLowerCase();
                panelEl.style.color = '';
                panelEl.title = info ? `${info.message || ''} · Checked ${info.last_checked_at || 'unknown'}` : 'No device report';
                const chip = panelEl.closest('.device-chip');
                if (chip) chip.dataset.status = state.toLowerCase();
            }
            if (dotId) {
                const dot = document.getElementById(dotId);
                if (dot) dot.className = `status-dot ${state === 'Online' ? 'online' : state === 'Unknown' ? 'unknown' : 'warning'}`;
                setText(labelId, `${label}: ${value}`);
            }
            if (key.endsWith('_cam')) setText(key === 'entrance_cam' ? 'camEntranceStatusLabel' : 'camExitStatusLabel', `${label} camera: ${state}`);
        }
        const edgeDot = document.getElementById('dotNet');
        if (edgeDot) edgeDot.className = `status-dot ${connection === 'Connected' ? 'online' : 'unknown'}`;
        setText('txtNet', `Edge: ${connection}`);
        setText('txtCloud', `Cloud queue: ${cloudState}`);
        setText('cloudQueueState', cloudState);
        const cloudDot = document.getElementById('dotCloud');
        if (cloudDot) cloudDot.className = `status-dot ${cloudState === 'Connected' ? 'online' : 'unknown'}`;
        setText('livePrinterState', document.getElementById('pnlPrinterStatus')?.textContent || 'Unknown');
        setText('liveEntryState', GuardLiveModel.device(deviceState.entrance_cam ? { ...deviceState.entrance_cam, device: 'entrance_cam' } : null, staleConnection));
        setText('liveExitState', GuardLiveModel.device(deviceState.exit_cam ? { ...deviceState.exit_cam, device: 'exit_cam' } : null, staleConnection));
    }
    function renderPrintIssues() {
        const panel = document.getElementById('printIssuesPanel');
        const list = document.getElementById('printIssuesList');
        if (!panel || !list) return;
        panel.hidden = printIssues.size === 0;
        setText('printIssueCount', printIssues.size);
        list.replaceChildren();
        for (const [id, issue] of printIssues) {
            const row = document.createElement('div'); row.className = 'print-issue-row';
            const label = document.createElement('span');
            label.textContent = `${issue.formatted_plate || issue.plate_number || 'Vehicle'} · ${issue.ticket_id || `Session ${id}`} · ${issue.updated_at || 'Just reported'}`;
            const btn = document.createElement('button'); btn.type = 'button'; btn.textContent = 'Reprint';
            btn.disabled = printAttempts.has(id);
            btn.onclick = async () => {
                if (printAttempts.has(id)) return;
                printAttempts.add(id); btn.disabled = true;
                try { await reprintTicket(id); } finally { printAttempts.delete(id); renderPrintIssues(); }
            };
            row.append(label, btn); list.append(row);
        }
    }
    function printFailed(id, issue = {}) {
        if (!id) return;
        printIssues.set(Number(id), { ...printIssues.get(Number(id)), ...issue });
        snapshotNeeded = true;
        renderPrintIssues();
    }
    function printResolved(id) { printIssues.delete(Number(id)); snapshotNeeded = true; renderPrintIssues(); }
    function setCloud(items) {
        if (!started || !STATE.user) return;
        cloudState = 'Connected';
        cloud = items || [];
        renderQueue(); devices(deviceState);
    }
    function cloudFailed() {
        cloudState = 'Unavailable'; devices(deviceState);
        if (STATE.firestoreUnsubscribe) STATE.firestoreUnsubscribe();
        STATE.firestoreUnsubscribe = null;
        clearTimeout(STATE.firestoreRetryTimer);
        if (started) STATE.firestoreRetryTimer = setTimeout(initFirestoreSync, 15000);
    }
    async function admitCloud(item, ticket) {
        const ids = item.firestore_ids || [item.firestore_id];
        const results = await Promise.all(ids.filter(Boolean).map(id => window.firebaseBridge.admitDriverRequest(id, ticket)));
        if (results.some(result => !result?.ok)) { cloudFailed(); showToast('Entry saved. Cloud queue acknowledgement will need a retry.'); }
    }
    async function takeSnapshot(epoch) {
        const version = mutationVersion;
        const data = await json('../api/guard/snapshot.php');
        if (epoch !== generation) return;
        if (version !== mutationVersion) { snapshotNeeded = true; return; }
        offset = data.server_timestamp * 1000 - Date.now();
        local = data.pending;
        renderRoster(data.roster.sessions);
        renderQueue();
        printIssues = new Map(data.print_failures.map(item => [Number(item.id), item]));
        renderPrintIssues();
        deviceState = Object.fromEntries(data.devices.map(item => [item.device, item]));
        updateOccupancyDisplay({ capacity: data.capacity, occupied: roster.length,
            available: Math.max(0, data.capacity - roster.length) });
        if (!initialized) STATE.lastEventId = Number(data.last_event_id);
        initialized = true;
        lastSnapshot = Date.now();
    }
    async function cycle() {
        if (!started || busy) return;
        clearTimeout(timer);
        busy = true;
        const epoch = generation;
        try {
            if (!initialized || snapshotNeeded || Date.now() - lastSnapshot > 10000) {
                snapshotNeeded = false;
                await takeSnapshot(epoch);
            }
            if (epoch !== generation) return;
            if (!initialized) { snapshotNeeded = true; return; }
            const data = await json(`../api/poll.php?guard=1&since=${STATE.lastEventId}`);
            if (epoch !== generation) return;
            if (data.reset_required) { initialized = false; snapshotNeeded = true; }
            offset = data.server_timestamp * 1000 - Date.now();
            updateOccupancyDisplay(data.stats);
            deviceState = data.devices || {};
            for (const event of data.events || []) {
                if (Number(event.id) <= STATE.lastEventId) continue;
                // Queue and roster changes are reconciled from authoritative snapshots.
                if (['new_request', 'request_updated', 'request_rejected', 'request_dismissed', 'queue_cleared',
                    'session_created', 'session_completed'].includes(event.type)) {
                    snapshotNeeded = true;
                    if (event.type === 'new_request') playDingSound();
                } else handleServerEvent(event);
                STATE.lastEventId = Number(event.id);
            }
            STATE.lastEventId = Math.max(STATE.lastEventId, Number(data.last_event_id));
            if ((data.events || []).length >= 50) snapshotNeeded = true;
            failures = 0;
            lastSuccess = Date.now();
            setConnection('Connected');
            if (snapshotNeeded) { snapshotNeeded = false; await takeSnapshot(epoch); }
        } catch (error) {
            if (epoch === generation && started) {
                failures++;
                snapshotNeeded = true;
                setConnection(failures > 2 ? 'Unavailable' : 'Reconnecting');
                setText('dashboardResourceNotice', error.message || 'Refresh failed. Previous data preserved.');
            }
        } finally {
            busy = false;
            if (started) timer = setTimeout(cycle, failures ? GuardLiveModel.retryDelay(failures) : (document.hidden ? 10000 : 2000));
        }
        if (!failures) setText('dashboardResourceNotice', '');
    }
    function start() {
        generation++;
        started = true; initialized = false; snapshotNeeded = true; failures = 0;
        local = []; cloud = []; roster = []; STATE.rosterSignature = null;
        cloudState = 'Connecting'; STATE.firestoreRetries = 0; lastSuccess = 0;
        setConnection('Connecting');
        hidePinModal();
        initFirestoreSync();
        cycle();
    }
    function snapshot() { snapshotNeeded = true; return cycle(); }
    document.addEventListener('visibilitychange', () => { if (!document.hidden && started) snapshot(); });
    window.addEventListener('online', () => { if (started) snapshot(); });
    document.addEventListener('DOMContentLoaded', () => {
        const measure = () => {
            const top = document.getElementById('guardMainLayout')?.getBoundingClientRect().top;
            if (top !== undefined) document.documentElement.style.setProperty('--guard-workspace-top', `${Math.max(0, top)}px`);
        };
        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(measure);
            ['dashboardLiveStatus', 'printIssuesPanel'].forEach(id => { const el = document.getElementById(id); if (el) observer.observe(el); });
        }
        window.addEventListener('resize', measure);
        window.addEventListener('scroll', measure, { passive: true });
        measure();
    });
    setInterval(freshness, 1000);
    return { start, snapshot, refresh: cycle, setCloud, cloudFailed, devices, now, printFailed, printResolved, admitCloud, expire };
})();
