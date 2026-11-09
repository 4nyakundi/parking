const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const model = require('../guard/js/guard-live-model.js');

test('holding starts eight hours after the approved deadline, including extensions', () => {
    const entry = 1700000000000;
    assert.equal(model.inOverstayHolding(entry, entry + 7200000), false);
    assert.equal(model.inOverstayHolding(entry, entry + 28800000), false);
    assert.equal(model.inOverstayHolding(entry, entry + 35999000), false);
    assert.equal(model.inOverstayHolding(entry, entry + 36000000), true);
    assert.equal(model.inOverstayHolding(entry, entry + 64799000, 36000), false);
    assert.equal(model.inOverstayHolding(entry, entry + 64800000, 36000), true);
    assert.equal(model.inOverstayHolding(entry, entry - 1), false);
    assert.equal(model.inOverstayHolding(NaN, entry), false);
    // Classification is reconstructed from the saved timestamps on reload.
    const saved = JSON.parse(JSON.stringify({ entry, now: entry + 86400000 }));
    assert.equal(model.inOverstayHolding(saved.entry, saved.now), true);
});

function harness() {
    const element = () => ({ textContent: '', style: {}, hidden: false });
    const elements = new Map(['countPillAll', 'countPillCrit', 'countPillHolding',
        'rosterCountBadge', 'statOccupied', 'overstayHoldingNotice', 'rosterFilterEmpty']
        .map(id => [id, element()]));
    elements.get('rosterFilterEmpty').firstElementChild = element();
    const row = (level, held, plate) => ({ style: {}, getAttribute(name) {
        return ({ 'data-overstay-level': level, 'data-overstay-holding': String(held),
            'data-plate': plate, 'data-driver': 'driver', 'data-dest': 'store' })[name];
    } });
    const rows = [row('safe', false, 'KDA123A'), row('critical', false, 'KDB456B'), row('critical', true, 'KDC789C')];
    let filter = 'all'; let query = '';
    const context = { GuardHistory: { isSelected: () => false }, window: {},
        document: { getElementById: id => id === 'rosterSearchInput' ? { value: query } : elements.get(id),
            querySelectorAll: () => rows, querySelector: () => ({ getAttribute: () => filter }) } };
    vm.createContext(context);
    const source = fs.readFileSync(require.resolve('../guard/js/guard.js'), 'utf8');
    vm.runInContext(source.slice(source.indexOf('function updateRosterFilterCounters('), source.indexOf('function clearRosterSearch(')), context);
    return { context, rows, elements, select(value, search = '') { filter = value; query = search; context.applyRosterFilters(); } };
}

test('holding records leave both main views, remain searchable, and retain occupancy', () => {
    const h = harness();
    h.elements.get('statOccupied').textContent = '3';
    h.context.updateRosterFilterCounters(3, 1, 0, 2);
    assert.equal(h.elements.get('countPillAll').textContent, '2');
    assert.equal(h.elements.get('countPillCrit').textContent, '1');
    assert.equal(h.elements.get('countPillHolding').textContent, '1');
    assert.equal(h.elements.get('rosterCountBadge').textContent, '3');
    assert.equal(h.elements.get('statOccupied').textContent, '3');
    h.select('all'); assert.deepEqual(h.rows.map(row => row.style.display), ['', '', 'none']);
    h.select('critical'); assert.deepEqual(h.rows.map(row => row.style.display), ['none', '', 'none']);
    h.select('holding', 'kdc'); assert.deepEqual(h.rows.map(row => row.style.display), ['none', 'none', '']);
    h.select('holding', 'unknown'); assert.equal(h.elements.get('rosterFilterEmpty').hidden, false);
    // Live timer classification moves a second record without deleting either.
    h.rows[1].getAttribute = name => ({ 'data-overstay-level': 'critical', 'data-overstay-holding': 'true' })[name];
    h.context.updateRosterFilterCounters(3, 1, 0, 2);
    assert.equal(h.elements.get('countPillHolding').textContent, '2');
    assert.equal(h.elements.get('statOccupied').textContent, '3');
});
