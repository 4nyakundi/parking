const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const model = require('../guard/js/guard-live-model.js');

test('cloud-only arrivals survive empty local snapshots and disappear when cloud removes them', () => {
    const cloud = [{ firestore_id: 'a', plate_number: 'KDA 123A' }];
    assert.equal(model.merge([], cloud).length, 1);
    assert.equal(model.merge([], []).length, 0);
});
test('queue merges normalized plates, preserves local authority, and links all cloud IDs', () => {
    const merged = model.merge([{ id: 7, plate_number: 'KDA123A', driver_name: 'Local', alpr_verified: true }],
        [{ firestore_id: 'a', plate_number: 'KDA 123A' }, { firestore_id: 'b', plate_number: 'kda-123a' }]);
    assert.equal(merged.length, 1);
    assert.equal(merged[0].id, 7);
    assert.equal(merged[0].alpr_verified, true);
    assert.deepEqual(merged[0].firestore_ids, ['a', 'b']);
});
test('active vehicles cannot reappear as pending through an old cloud request', () => {
    assert.equal(model.merge([], [{ firestore_id: 'a', plate_number: 'KDA 123A' }], [{ plate_number: 'KDA123A' }]).length, 0);
});
test('device freshness never turns missing reports or lost edge connectivity green', () => {
    assert.equal(model.device(null), 'Unknown');
    assert.equal(model.device({ device: 'entrance_cam', status: 'OK', age_seconds: 121 }), 'Stale');
    assert.equal(model.device({ device: 'printer', status: 'OK', age_seconds: 900 }), 'Online');
    assert.equal(model.device({ device: 'printer', status: 'OK' }, true), 'Unknown');
});
test('overstay boundaries and retry cap are deterministic', () => {
    assert.equal(model.level(1000, 1000 + 5399000), 'safe');
    assert.equal(model.level(1000, 1000 + 5400000), 'warning');
    assert.equal(model.level(1000, 1000 + 7200000), 'critical');
    assert.deepEqual([0, 1, 2, 3, 9].map(model.retryDelay), [2000, 4000, 8000, 16000, 30000]);
});
function harness(responder) {
    const elements = new Map();
    function element(id) {
        if (!elements.has(id)) elements.set(id, { dataset: {}, style: {}, textContent: '', innerHTML: '', hidden: false,
            classList: { toggle() {}, add() {}, remove() {} }, replaceChildren() {}, append() {}, closest() { return null; } });
        return elements.get(id);
    }
    const scheduled = new Map(); let nextTimer = 0; let loginShown = 0;
    const context = { console, Response, AbortController, GuardLiveModel: model,
        STATE: { user: { id: 1 }, lastEventId: 0 },
        window: { fetch: responder, addEventListener() {} },
        document: { hidden: false, body: element('body'), getElementById: element,
            querySelectorAll() { return []; }, addEventListener() {}, createElement() { return element(`new-${Math.random()}`); } },
        setTimeout(fn, ms) { const id = ++nextTimer; scheduled.set(id, {fn, ms}); return id; },
        clearTimeout(id) { scheduled.delete(id); }, setInterval() {},
        initFirestoreSync() {}, hidePinModal() {}, showPinModal() { loginShown++; },
        updateOccupancyDisplay() {}, updateRosterFilterCounters() {}, renderPendingRequestsDiff() {},
        renderActiveSessionsTable() {}, startRosterLiveTimers() {}, updateQueueFilterCounts() {},
        handleServerEvent() { context.events++; }, events: 0, playDingSound() {}, showToast() {}
    };
    context.fetch = (...args) => context.window.fetch(...args);
    vm.createContext(context);
    vm.runInContext(fs.readFileSync(require.resolve('../guard/js/guard-live.js'), 'utf8') + '\nglobalThis.live = GuardLive;', context);
    return { context, elements, scheduled, get loginShown() { return loginShown; } };
}
const snapshot = () => ({ ok: true, last_event_id: 100, server_timestamp: Math.floor(Date.now()/1000), pending: [],
    roster: { sessions: [] }, devices: [], print_failures: [], capacity: 60 });
const response = (body, status = 200) => new Response(JSON.stringify(body), { status, headers: {'Content-Type':'application/json'} });
async function settle() { for (let i = 0; i < 12; i++) await new Promise(resolve => setImmediate(resolve)); }

test('startup uses current snapshot cursor and coalesces simultaneous refreshes', async () => {
    let release; const calls = [];
    const h = harness(async url => {
        calls.push(url);
        if (url.includes('snapshot') && !release) return new Promise(resolve => { release = () => resolve(response(snapshot())); });
        if (url.includes('snapshot')) return response(snapshot());
        return response({ ok:true, last_event_id:100, server_timestamp:Math.floor(Date.now()/1000), events:[], stats:{}, devices:{} });
    });
    h.context.live.start();
    h.context.live.refresh(); h.context.live.snapshot(); h.context.live.refresh();
    assert.equal(calls.length, 1);
    release(); await settle();
    assert.ok(calls.some(url => url.includes('since=100')));
    assert.equal(h.context.events, 0);
    assert.equal(h.elements.get('edgeConnectionState').textContent, 'Connected');
});
test('failed refresh preserves roster and schedules capped recovery backoff', async () => {
    const h = harness(async () => { throw new Error('Network unavailable'); });
    h.elements.set('insideTableBody', { innerHTML: 'Existing vehicles' });
    h.context.live.start(); await settle();
    assert.equal(h.elements.get('insideTableBody').innerHTML, 'Existing vehicles');
    assert.equal(h.elements.get('edgeConnectionState').textContent, 'Reconnecting');
    assert.ok([...h.scheduled.values()].some(timer => timer.ms === 4000));
});
test('unauthorized refresh stops the loop and shows PIN instead of an empty roster', async () => {
    const h = harness(async () => response({ ok:false, need_login:true }, 401));
    h.context.live.start(); await settle();
    assert.equal(h.loginShown, 1);
    assert.equal(h.context.STATE.user, null);
    assert.equal(h.elements.get('edgeConnectionState').textContent, 'Session expired');
    assert.equal(h.scheduled.size, 0);
});
test('late responses from an expired session cannot update the new dashboard', async () => {
    let release;
    const h = harness(async () => new Promise(resolve => { release = resolve; }));
    h.context.live.start(); h.context.live.expire();
    release(response(snapshot())); await settle();
    assert.equal(h.context.STATE.lastEventId, 0);
    assert.equal(h.elements.get('edgeConnectionState').textContent, 'Session expired');
});

test('a replaced event stream reinitializes from the new snapshot cursor', async () => {
    let snapshots = 0;
    const h = harness(async url => {
        if (url.includes('snapshot')) { snapshots++; return response({ ...snapshot(), last_event_id: snapshots === 1 ? 100 : 3 }); }
        return response({ ok:true, reset_required:true, last_event_id:100, server_timestamp:Math.floor(Date.now()/1000), events:[], stats:{}, devices:{} });
    });
    h.context.live.start(); await settle();
    assert.equal(snapshots, 2);
    assert.equal(h.context.STATE.lastEventId, 3);
});

test('an old unauthorized response cannot expire a freshly signed-in session', async () => {
    let release;
    const h = harness(async () => new Promise(resolve => { release = resolve; }));
    h.context.live.start();
    h.context.live.expire();
    h.context.STATE.user = { id: 2 };
    h.context.live.start();
    release(response({ok:false, need_login:true}, 401)); await settle();
    assert.equal(h.context.STATE.user.id, 2);
    assert.equal(h.loginShown, 1);
});

test('history refresh runs after polling and repeated refresh clicks cannot overlap it', async () => {
    const calls = []; let release;
    const h = harness(async url => {
        calls.push(url.includes('snapshot') ? 'snapshot' : 'poll');
        return response(url.includes('snapshot') ? snapshot() : { ok:true, last_event_id:100,
            server_timestamp:Math.floor(Date.now()/1000), events:[], stats:{}, devices:{} });
    });
    h.context.GuardHistory = { reset() {}, refresh() {
        calls.push('history'); return new Promise(resolve => { release = resolve; });
    } };
    h.context.live.start(); await settle();
    assert.deepEqual(calls, ['snapshot','poll','history']);
    h.context.live.snapshot(); h.context.live.snapshot(); await settle();
    assert.deepEqual(calls, ['snapshot','poll','history']);
    release(); await settle();
});

test('daily overview comes from the snapshot, preserves totals offline, and clears on expiry', async () => {
    let offline = false;
    const h = harness(async url => {
        if (offline) throw Error('Offline');
        return response(url.includes('snapshot') ? { ...snapshot(), daily_overview: {
            date:'2026-11-03', total_arrivals:8, registered:5, unregistered:3,
            pending_registration:2, exited_without_details:1, exited:4 } }
            : { ok:true, last_event_id:100, server_timestamp:Math.floor(Date.now()/1000), events:[], stats:{}, devices:{} });
    });
    h.context.live.start(); await settle();
    assert.equal(h.elements.get('statTodayArrivals').textContent, '8');
    assert.equal(h.elements.get('statTodayRegistered').textContent, '5');
    assert.equal(h.elements.get('statTodayUnregistered').textContent, '3');
    assert.equal(h.elements.get('statTodayExited').textContent, '4');
    assert.match(h.elements.get('todayUnregisteredBreakdown').textContent, /2 awaiting/);
    offline = true; h.context.live.snapshot(); await settle();
    assert.equal(h.elements.get('statTodayArrivals').textContent, '8');
    assert.match(h.elements.get('todayOverviewFreshness').textContent, /Last known totals/);
    h.context.live.expire();
    assert.equal(h.elements.get('statTodayArrivals').textContent, '--');
    assert.equal(h.elements.get('statTodayRegistered').textContent, '--');
    assert.match(h.elements.get('todayOverviewFreshness').textContent, /Sign in/);
});
