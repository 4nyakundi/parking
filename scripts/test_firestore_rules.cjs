const fs = require('node:fs');
const assert = require('node:assert/strict');
const { initializeTestEnvironment, assertSucceeds, assertFails } = require('@firebase/rules-unit-testing');
const { doc, setDoc, getDoc, updateDoc, serverTimestamp, collection, getDocs } = require('firebase/firestore');

async function main() {
  const env = await initializeTestEnvironment({projectId: 'demo-parking-security', firestore: {
    host: '127.0.0.1', port: 8089, rules: fs.readFileSync('firestore.rules', 'utf8')
  }});
  let passed = 0;
  const check = async (label, fn) => { await fn(); passed++; console.log('PASS: ' + label); };
  try {
    const guest = env.unauthenticatedContext().firestore();
    const ordinary = env.authenticatedContext('ordinary-user').firestore();
    const until = Math.floor(Date.now() / 1000) + 300;
    const staff = env.authenticatedContext('parking-staff-1', {parking_staff: true, parking_until: until}).firestore();
    const expired = env.authenticatedContext('parking-staff-2', {parking_staff: true, parking_until: 1}).firestore();
    const request = {plate_number: 'KDA123A', formatted_plate: 'KDA 123A', driver_name: 'Test Visitor', driver_phone: '254700000000', destination: 'Shop', status: 'PENDING', source: 'firebase_web', created_at: new Date().toISOString(), server_timestamp: serverTimestamp(), consent: true};
    await check('Public driver intake still accepts a valid consented submission', () => assertSucceeds(setDoc(doc(guest, 'driver_requests/request-one'), request)));
    await check('Visitor records cannot be read anonymously', () => assertFails(getDoc(doc(guest, 'driver_requests/request-one'))));
    await check('Signing in as an ordinary Firebase user does not grant staff access', () => assertFails(getDocs(collection(ordinary, 'driver_requests'))));
    await check('Server-authorized staff can read pending requests', () => assertSucceeds(getDocs(collection(staff, 'driver_requests'))));
    await check('Expired staff authorization cannot read visitor details', () => assertFails(getDoc(doc(expired, 'driver_requests/request-one'))));
    await check('Public callers cannot admit a vehicle', () => assertFails(updateDoc(doc(guest, 'driver_requests/request-one'), {status: 'ADMITTED'})));
    await check('Public callers cannot reject a vehicle', () => assertFails(updateDoc(doc(guest, 'driver_requests/request-one'), {status: 'REJECTED'})));
    await check('Authorized staff can acknowledge admission', () => assertSucceeds(updateDoc(doc(staff, 'driver_requests/request-one'), {status: 'ADMITTED', ticket_id: 'TEST-1', admitted_at: serverTimestamp()})));
    await check('Staff acknowledgement cannot replace a visitor identity', () => assertFails(updateDoc(doc(staff, 'driver_requests/request-one'), {driver_name: 'Someone else'})));
    await check('Intake rejects injected privileged fields', () => assertFails(setDoc(doc(guest, 'driver_requests/injected'), {...request, parking_staff: true})));
    await check('Intake requires consent', () => assertFails(setDoc(doc(guest, 'driver_requests/no-consent'), {...request, consent: false})));
    await check('Public callers cannot fabricate admitted requests', () => assertFails(setDoc(doc(guest, 'driver_requests/admitted'), {...request, status: 'ADMITTED'})));
    const telemetry = {capacity: 60, occupied: 10, available: 50, today_in: 10, today_out: 0, updated_at: serverTimestamp()};
    await check('Public callers cannot change occupancy', () => assertFails(setDoc(doc(guest, 'parking_telemetry/live'), telemetry)));
    await check('Authorized staff can publish valid occupancy', () => assertSucceeds(setDoc(doc(staff, 'parking_telemetry/live'), telemetry)));
    await check('Occupancy remains publicly readable', () => assertSucceeds(getDoc(doc(guest, 'parking_telemetry/live'))));
    await check('Inconsistent occupancy is rejected', () => assertFails(setDoc(doc(staff, 'parking_telemetry/live'), {...telemetry, available: 60})));
    await check('Expired staff cannot change occupancy', () => assertFails(setDoc(doc(expired, 'parking_telemetry/live'), telemetry)));
    assert.equal(passed, 17);
  } finally { await env.cleanup(); }
}
main().catch(error => { console.error(error.message); process.exitCode = 1; });
