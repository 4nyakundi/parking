const test = require('node:test'), assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
const settle = async () => { for (let i = 0; i < 30; i++) await Promise.resolve(); };
function bridge({authorized = true} = {}) {
  const state = {adds: [], updates: [], tokens: 0, signs: 0, signouts: 0, subscriptions: 0, unsubscribed: 0, intervals: [], requests: []};
  const auth = {currentUser: null};
  const context = {console: {log(){}, warn(){}, error(){}}, URL, Date, window: {},
    initializeApp: () => ({}), getApps: () => [], getApp: () => ({}),
    getAnalytics: () => null, isSupported: async () => false, logEvent(){},
    getFirestore: () => ({}), getAuth: () => auth, inMemoryPersistence: 'memory',
    setPersistence: async (a, storage) => assert.equal(storage, 'memory'),
    signInWithCustomToken: async (a, token) => { assert.equal(token, 'server-token'); state.signs++; auth.currentUser = {}; },
    signOut: async () => { state.signouts++; auth.currentUser = null; },
    fetch: async (url, options) => {
      state.requests.push({url: String(url), options});
      if (String(url).endsWith('me.php')) return {ok: authorized, json: async () => ({authenticated: authorized, csrf_token: authorized ? 'csrf' : null})};
      assert.equal(options.headers['X-CSRF-Token'], 'csrf'); state.tokens++;
      return {ok: true, json: async () => ({ok: true, token: 'server-token'})};
    },
    collection: () => ({}), doc: (db, collection, id) => ({collection, id}),
    addDoc: async (collection, data) => { state.adds.push(data); return {id: 'new-request'}; },
    updateDoc: async (ref, data) => { state.updates.push({ref, data}); }, setDoc: async () => {},
    query: () => ({}), where: () => ({}), orderBy: () => ({}), limit: () => ({}), serverTimestamp: () => 'server-time',
    onSnapshot: () => { state.subscriptions++; return () => state.unsubscribed++; },
    setInterval: fn => { state.intervals.push(fn); return state.intervals.length; }, clearInterval(){},
  };
  let source = fs.readFileSync(require.resolve('../public/assets/js/firebase-init.js'), 'utf8')
    .replace(/import\s+\{[\s\S]*?\}\s+from\s+["'][^"']+["'];/g, '')
    .replace(/\bexport\s+/g, '').replace(/import\.meta\.url/g, "'http://localhost/parking/public/assets/js/firebase-init.js'");
  vm.createContext(context); vm.runInContext(source + '\nglobalThis.bridge=firebaseBridge;', context);
  return {state, context, api: context.bridge};
}
test('public intake sends consent and does not request staff credentials', async () => {
  const h = bridge(); const result = await h.api.submitDriverRequest({plate_number: 'KDA123A', driver_name: 'Visitor', driver_phone: '254700000000', destination: 'Shop', consent: true});
  assert.equal(result.ok, true); assert.equal(h.state.adds[0].consent, true); assert.equal(h.state.requests.length, 0);
});
test('cloud listener obtains server authorization before reading visitor data', async () => {
  const h = bridge(); const stop = h.api.listenPendingRequests(() => {}); await settle();
  assert.equal(h.state.tokens, 1); assert.equal(h.state.signs, 1); assert.equal(h.state.subscriptions, 1);
  assert.match(h.state.requests[1].url, /^http:\/\/localhost\/parking\/api\/auth\/firebase-token.php$/);
  stop(); await settle(); assert.equal(h.state.unsubscribed, 1); assert.equal(h.state.signouts, 1);
});
test('unsigned users cannot start a staff listener or acknowledge a vehicle', async () => {
  const h = bridge({authorized: false}); let errors = 0;
  h.api.listenPendingRequests(() => {}, () => errors++); await settle();
  assert.equal(h.state.subscriptions, 0); assert.equal(errors, 1);
  const result = await h.api.admitDriverRequest('someone-else', 'TICKET');
  assert.equal(result.ok, false); assert.equal(h.state.updates.length, 0);
});
test('staff acknowledgement retains the existing guard workflow', async () => {
  const h = bridge(); const result = await h.api.admitDriverRequest('pending-one', 'TICKET-1');
  assert.equal(result.ok, true); assert.equal(h.state.updates[0].data.status, 'ADMITTED'); assert.equal(h.state.updates[0].data.ticket_id, 'TICKET-1');
});
test('a cancelled sign-in cannot attach a late cloud listener', async () => {
  const h = bridge(); const stop = h.api.listenPendingRequests(() => {}); stop(); await settle();
  assert.equal(h.state.subscriptions, 0); assert.equal(h.state.signs, 0);
});
