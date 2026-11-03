const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function harness(responder) {
    const elements = new Map(), listeners = new Map();
    function element(id) {
        if (!elements.has(id)) elements.set(id, { style: {}, value: '', textContent: '', hidden: false, children: [],
            replaceChildren(...children) { this.children = children; },
            appendChild(child) { this.children.push(child); }, append(...children) { this.children.push(...children); },
            addEventListener(type, fn) { this[type] = fn; } });
        return elements.get(id);
    }
    const context = { console, Intl, Date, URLSearchParams, STATE: { user: { id: 1 } },
        GuardLive: { now: () => Date.parse('2026-11-03T10:00:00+03:00'), snapshot() {} },
        document: { getElementById: element, createElement: tag => element(`created-${elements.size}-${tag}`),
            addEventListener(type, fn) { listeners.set(type, fn); } }, fetch: responder };
    vm.createContext(context);
    vm.runInContext(fs.readFileSync(require.resolve('../guard/js/guard-history.js'), 'utf8') + '\nglobalThis.history = GuardHistory;', context);
    listeners.get('DOMContentLoaded')();
    return { context, elements, element, history: context.history };
}
const report = (overrides = {}) => ({ mode: 'exited', date: '', today: '2026-11-03', summary: null,
    sessions: [], total: 0, page: 1, pages: 1, server_timestamp: 1793689200, ...overrides });
const response = data => ({ ok: true, json: async () => ({ ok: true, data }) });

test('exited view starts with all dates, daily view chooses Nairobi today', async () => {
    const urls = [];
    const h = harness(async url => { urls.push(url); return response(report()); });
    h.history.select('exited'); h.history.show(); await h.history.refresh();
    assert.equal(new URLSearchParams(urls[0].split('?')[1]).get('date'), '');
    h.history.select('day'); h.history.show(); await h.history.refresh();
    assert.equal(new URLSearchParams(urls[1].split('?')[1]).get('date'), '2026-11-03');
    assert.equal(h.element('trafficAllDates').hidden, true);
    assert.equal(h.element('viewBasementRoster').style.display, 'none');
});
test('a late response for another tab cannot populate history', async () => {
    let release;
    const h = harness(() => new Promise(resolve => { release = resolve; }));
    h.history.select('exited'); h.history.show();
    const pending = h.history.refresh();
    h.history.select('day'); h.history.show();
    release(response(report({ total: 99 }))); await pending;
    assert.equal(h.element('trafficPageInfo').textContent, '');
    assert.equal(h.element('trafficHistoryBody').children.length, 0);
});
test('session expiry clears results and ignores a pending history response', async () => {
    let release;
    const h = harness(() => new Promise(resolve => { release = resolve; }));
    h.history.select('exited'); h.history.show();
    const pending = h.history.refresh();
    h.context.STATE.user = null; h.history.reset();
    release(response(report({ total: 50 }))); await pending;
    assert.equal(h.element('trafficHistoryBody').children.length, 0);
    assert.match(h.element('trafficHistoryStatus').textContent, /Sign in/);
});
test('history failures back off and leave previous results available', async () => {
    let requests = 0;
    const h = harness(async () => { requests++; if (requests === 1) return response(report()); throw Error('Offline'); });
    h.history.select('exited'); h.history.show(); await h.history.refresh();
    assert.match(h.element('trafficPageInfo').textContent, /0 matching/);
    h.history.invalidate(); await h.history.refresh(); await h.history.refresh();
    assert.equal(requests, 2);
    assert.match(h.element('trafficHistoryStatus').textContent, /Showing previous results/);
});
test('search safely encodes the query and invalidates an older response', async () => {
    const urls = []; let release;
    const h = harness(url => { urls.push(url); return new Promise(resolve => { release = resolve; }); });
    h.history.select('exited'); h.history.show(); const pending = h.history.refresh();
    h.element('rosterSearchInput').value = "<driver>&'"; h.history.search();
    release(response(report({ total: 99 }))); await pending;
    assert.equal(h.element('trafficPageInfo').textContent, '');
    const next = h.history.refresh();
    assert.equal(new URLSearchParams(urls[1].split('?')[1]).get('q'), "<driver>&'");
    release(response(report())); await next;
});
test('history displays personal values as text and daily totals separately', async () => {
    const h = harness(async () => response(report({ mode: 'day', date: '2026-11-03',
        summary: { unique_cars: 1, present_visits: 2, carried_in: 0, arrivals: 2, exits: 1, movements: 3, remaining: 1, missing_details: 0 },
        sessions: [{ plate_number: 'KDA123A', ticket_id: 'TEST-1', driver_name: '<img onerror=bad()>', driver_phone: '254700000000',
            destination: '<script>bad()</script>', entry_time: '2026-11-03 10:00:00', exit_time: null, duration_seconds: 3600, status: 'ACTIVE' }] })));
    h.history.select('day'); h.history.show(); await h.history.refresh();
    const row = h.element('trafficHistoryBody').children[0];
    assert.equal(row.children[1].children[0].textContent, '<img onerror=bad()>');
    assert.equal(row.children[2].textContent, '<script>bad()</script>');
    assert.equal(h.element('trafficSummary').children.length, 8);
    assert.equal(h.element('trafficSummary').children[5].children[0].textContent, '3');
});
