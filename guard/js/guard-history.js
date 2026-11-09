/* Read-only history views, refreshed through the existing sequential live coordinator. */
const GuardHistory = (() => {
    let mode = 'all', page = 1, pages = 1, revision = 0, loadedAt = 0, query = '';
    let exitScope = 'current_shift';
    let exitedDate = '', dayDate = '', failures = 0, retryAt = 0, hasResults = false;
    const el = id => document.getElementById(id);
    const selected = () => ['exited', 'day'].includes(mode);
    const today = () => {
        const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Africa/Nairobi',
            year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date(GuardLive.now()));
        const value = type => parts.find(part => part.type === type).value;
        return `${value('year')}-${value('month')}-${value('day')}`;
    };
    const currentDate = () => mode === 'day' ? dayDate : exitedDate;
    function setStatus(message) { if (el('trafficHistoryStatus')) el('trafficHistoryStatus').textContent = message; }
    function clearRecords() {
        hasResults = false;
        el('trafficHistoryBody')?.replaceChildren();
        el('trafficSummary')?.replaceChildren();
        if (el('trafficSummary')) el('trafficSummary').hidden = true;
        if (el('trafficPageInfo')) el('trafficPageInfo').textContent = '';
        if (el('trafficPrev')) el('trafficPrev').disabled = true;
        if (el('trafficNext')) el('trafficNext').disabled = true;
    }
    function invalidate(clear = false) {
        revision++; loadedAt = 0; retryAt = 0; failures = 0;
        if (clear) clearRecords();
        if (selected()) setStatus('Loading recorded visits…');
    }
    function select(value) {
        mode = value; page = 1;
        if (!dayDate) dayDate = today();
        query = (el('rosterSearchInput')?.value || '').trim();
        invalidate(true);
    }
    function show() {
        if (!selected()) return;
        el('viewBasementRoster').style.display = 'none';
        el('viewTrafficHistory').style.display = 'flex';
        el('centerStageTitle').textContent = mode === 'exited' ? 'Exited Cars — Cleared Visits' : 'Daily Traffic — Vehicles & Movement';
        el('centerStageSubtitle').textContent = mode === 'exited'
            ? 'Latest departures first • Includes departures without driver details'
            : 'Cars present on your selected date • Includes overnight stays • Nairobi time';
        el('trafficDate').max = today();
        el('trafficDate').value = currentDate();
        el('trafficAllDates').hidden = mode !== 'exited';
        if(el('trafficShift'))el('trafficShift').hidden=mode!=='exited';
        el('trafficHistoryNote').textContent = mode === 'exited'
            ? (exitScope==='current_shift'?'Departures during your open shift. If you have no open shift, shows today. Includes departures without driver details.':'All recorded departures, including visits without captured driver details. Choose a date to filter by departure day, or view all dates.')
            : 'Recorded parking visits present at any time that day, including earlier arrivals. Totals cover the full day and are not reduced by search. Repeat visits count separately; movements = arrivals + exits. Departures without driver details are included and marked.';
    }
    function search() {
        if (!selected()) return;
        const value = (el('rosterSearchInput')?.value || '').trim();
        const clear = el('btnClearRosterSearch');
        if (clear) clear.style.display = value ? 'block' : 'none';
        if (value !== query) { query = value; page = 1; invalidate(true); }
    }
    function dateChanged(value) {
        if (!selected()) return;
        if (mode === 'day') dayDate = value || today(); else {exitedDate = value; exitScope='date';}
        page = 1; invalidate(true); show(); GuardLive.snapshot();
    }
    function cell(row, text, className = '') {
        const td = document.createElement('td'); td.textContent = text || '—';
        if (className) td.className = className;
        row.appendChild(td); return td;
    }
    function render(data) {
        page = data.page; pages = data.pages;
        const body = el('trafficHistoryBody'); body.replaceChildren();
        for (const item of data.sessions) {
            const row = document.createElement('tr');
            const plate = cell(row, '', 'traffic-vehicle');
            const label = document.createElement('strong'); label.textContent = item.plate_number;
            const ticket = document.createElement('small'); ticket.textContent = item.ticket_id;
            plate.replaceChildren(label, ticket);
            const missing = item.driver_name === 'Uncollected Driver';
            const driver = cell(row, '', 'traffic-driver');
            const name = document.createElement('span'); name.textContent = missing ? 'Details not captured' : item.driver_name;
            const phone = document.createElement('small'); phone.textContent = item.driver_phone || '';
            driver.replaceChildren(name, phone);
            cell(row, item.destination);
            cell(row, item.entry_time, 'traffic-datetime');
            cell(row, item.exit_time || 'Still inside', 'traffic-datetime');
            const seconds = Math.max(0, Number(item.duration_seconds) || 0);
            cell(row, `${Math.floor(seconds / 3600)}h ${Math.floor(seconds % 3600 / 60)}m`);
            const status = cell(row, '');
            const badge = document.createElement('span');
            badge.className = `roster-status-badge ${missing ? 'warning' : item.status === 'COMPLETED' ? 'safe' : ''}`;
            badge.textContent = missing ? 'Exited · No details' : item.status === 'COMPLETED' ? 'Cleared' : 'Currently inside';
            status.replaceChildren(badge);
            cell(row, item.cleared_by_name);
            body.appendChild(row);
        }
        if (!data.sessions.length) {
            const row = document.createElement('tr');
            const td = cell(row, 'No recorded visits match this view.', 'guard-empty'); td.colSpan = 8;
            body.appendChild(row);
        }
        const summary = el('trafficSummary'); summary.replaceChildren(); summary.hidden = mode !== 'day';
        if (mode === 'day' && data.summary) {
            const metrics = [['unique_cars', 'Unique cars'], ['present_visits', 'Visits present'],
                ['carried_in', 'From earlier days'], ['arrivals', 'Arrivals'], ['exits', 'Exits'],
                ['movements', 'Total movements'], ['remaining', data.date === data.today ? 'Inside now' : 'At day close'],
                ['missing_details', 'Without driver details']];
            for (const [key, label] of metrics) {
                const card = document.createElement('div'); card.className = 'traffic-stat';
                const value = document.createElement('strong'); value.textContent = String(data.summary[key]);
                const caption = document.createElement('span'); caption.textContent = label;
                card.append(value, caption); summary.appendChild(card);
            }
        }
        el('trafficPageInfo').textContent = `${data.total} matching visits · Page ${page} of ${pages}`;
        el('trafficPrev').disabled = page <= 1; el('trafficNext').disabled = page >= pages;
        const time = new Intl.DateTimeFormat('en-GB', { timeZone: 'Africa/Nairobi', hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date(data.server_timestamp * 1000));
        setStatus(`Updated ${time} · Nairobi time`);
    }
    async function refresh() {
        if (!selected() || !STATE.user || el('viewTrafficHistory')?.style.display === 'none') return;
        if (Date.now() < retryAt || (loadedAt && Date.now() - loadedAt < 10000)) return;
        const token = revision;
        const params = new URLSearchParams({ scope:mode==='exited'?exitScope:'date', mode, date: currentDate(), q: query, page: String(page) });
        try {
            const response = await fetch(`../api/guard/history.php?${params}`);
            const body = await response.json();
            if (token !== revision || !STATE.user) return;
            if (!response.ok || !body.ok) throw new Error(body.error || 'History unavailable');
            render(body.data); loadedAt = Date.now(); hasResults = true; failures = 0; retryAt = 0;
        } catch (error) {
            if (token !== revision || !STATE.user) return;
            failures++; retryAt = Date.now() + Math.min(30000, 2000 * 2 ** Math.min(failures, 4));
            setStatus(`${hasResults ? 'Showing previous results. ' : ''}${error.message || 'History unavailable'}. Retrying…`);
        }
    }
    function reset() { if(typeof ParkingOperations!=='undefined')ParkingOperations.close(); revision++; loadedAt = 0; clearRecords(); setStatus('Sign in to view recorded visits.'); }
    document.addEventListener('DOMContentLoaded', () => {
        el('trafficDate')?.addEventListener('change', event => dateChanged(event.target.value));
        el('trafficToday')?.addEventListener('click', () => dateChanged(today()));
        el('trafficShift')?.addEventListener('click',()=>{exitScope='current_shift';exitedDate='';page=1;invalidate(true);show();GuardLive.snapshot();});
        el('trafficAllDates')?.addEventListener('click', () => dateChanged(''));
        for (const [id, increment] of [['trafficPrev', -1], ['trafficNext', 1]]) {
            el(id)?.addEventListener('click', () => {
                page = Math.max(1, Math.min(pages, page + increment)); invalidate(true); GuardLive.snapshot();
            });
        }
    });
    return { select, show, search, refresh, reset, invalidate, isSelected: selected };
})();
