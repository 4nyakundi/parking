/**
 * Mombasa Mall Basement Parking - Firebase Configuration & Initialization
 * Project: mombasa-mall-parking
 *
 * Provides initialized Firebase app, analytics (with browser compatibility check),
 * Firestore real-time synchronization bridge, and telemetry logging.
 */

// Import modern modular Firebase SDK via CDN (compatible with standard browser ES modules)
import { initializeApp, getApps, getApp } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-app.js";
import { getAnalytics, isSupported, logEvent } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-analytics.js";
import { 
  getFirestore, 
  collection, 
  addDoc, 
  onSnapshot, 
  query, 
  where, 
  orderBy, 
  doc, 
  updateDoc, 
  setDoc,
  serverTimestamp, 
  limit 
} from "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js";

// Web app's Firebase configuration
export const firebaseConfig = {
  apiKey: "AIzaSyCG5AYDFC1YmFp5mN0OA3eCVXQVQwGGQss",
  authDomain: "mombasa-mall-parking.firebaseapp.com",
  projectId: "mombasa-mall-parking",
  storageBucket: "mombasa-mall-parking.firebasestorage.app",
  messagingSenderId: "1037359720174",
  appId: "1:1037359720174:web:332b7b9468414332ebb5ff",
  measurementId: "G-YMRLE3DLC5"
};

// Initialize or retrieve existing Firebase app singleton
export const app = getApps().length === 0 ? initializeApp(firebaseConfig) : getApp();

// Initialize Firestore
export const db = getFirestore(app);

// Initialize Analytics safely (guards against environments where IndexedDB/cookies are disabled)
export let analytics = null;
isSupported().then(supported => {
  if (supported) {
    analytics = getAnalytics(app);
    window.firebaseAnalytics = analytics;
    console.log('[Firebase] Analytics initialized successfully (mombasa-mall-parking)');
  }
}).catch(err => {
  console.warn('[Firebase] Analytics not supported in this environment:', err);
});

/**
 * Universal telemetry event logger
 * @param {string} eventName
 * @param {Record<string, any>} eventParams
 */
export function logParkingEvent(eventName, eventParams = {}) {
  if (analytics) {
    try {
      logEvent(analytics, eventName, eventParams);
    } catch (e) {
      console.warn('[Firebase] Failed to log event:', eventName, e);
    }
  }
}

/**
 * Firebase Real-time Bridge for Driver Intake & Guard Sync
 */
export const firebaseBridge = {
  /**
   * Submit driver sign-in request directly to Cloud Firestore
   */
  async submitDriverRequest(data) {
    try {
      const colRef = collection(db, "driver_requests");
      const docRef = await addDoc(colRef, {
        plate_number:    (data.plate_number || '').toUpperCase().trim(),
        formatted_plate: (data.formatted_plate || data.plate_number || '').toUpperCase().trim(),
        driver_name:     (data.driver_name || 'Visitor').trim(),
        driver_phone:    (data.driver_phone || '').trim(),
        destination:     (data.destination || 'Mombasa Mall').trim(),
        status:          'PENDING',
        source:          data.source || 'firebase_web',
        created_at:      new Date().toISOString(),
        server_timestamp: serverTimestamp()
      });
      logParkingEvent('cloud_driver_request_created', {
        plate_number: data.plate_number,
        destination: data.destination
      });
      return { ok: true, id: docRef.id };
    } catch (err) {
      console.error('[Firebase] submitDriverRequest failed:', err);
      return { ok: false, error: err.message };
    }
  },

  /**
   * Listen for incoming pending driver sign-in requests in real-time
   */
  listenPendingRequests(callback) {
    try {
      const colRef = collection(db, "driver_requests");
      const q = query(
        colRef,
        where("status", "==", "PENDING"),
        limit(50)
      );

      return onSnapshot(q, (snapshot) => {
        const requests = [];
        snapshot.forEach(docSnap => {
          const item = docSnap.data();
          item.firestore_id = docSnap.id;
          item.id = item.id || `fb_${docSnap.id}`;
          item.source = item.source || 'firebase_web';
          requests.push(item);
        });
        callback(requests);
      }, (err) => {
        console.warn('[Firebase] listenPendingRequests listener error:', err);
      });
    } catch (e) {
      console.warn('[Firebase] listenPendingRequests setup error:', e);
      return () => {};
    }
  },

  /**
   * Mark request as ADMITTED once guard prints the ticket
   */
  async admitDriverRequest(firestoreId, ticketId = null) {
    if (!firestoreId) return;
    try {
      const docRef = doc(db, "driver_requests", firestoreId);
      await updateDoc(docRef, {
        status: 'ADMITTED',
        ticket_id: ticketId,
        admitted_at: serverTimestamp()
      });
      logParkingEvent('cloud_driver_request_admitted', { firestore_id: firestoreId });
      return { ok: true };
    } catch (e) {
      console.warn('[Firebase] admitDriverRequest error:', e);
      return { ok: false, error: e.message };
    }
  },

  /**
   * Mark request as REJECTED
   */
  async rejectDriverRequest(firestoreId, reason = '') {
    if (!firestoreId) return;
    try {
      const docRef = doc(db, "driver_requests", firestoreId);
      await updateDoc(docRef, {
        status: 'REJECTED',
        rejection_reason: reason,
        rejected_at: serverTimestamp()
      });
      return { ok: true };
    } catch (e) {
      console.warn('[Firebase] rejectDriverRequest error:', e);
      return { ok: false, error: e.message };
    }
  },

  /**
   * Update live mall occupancy counters in Firestore
   */
  async updateOccupancyTelemetry(stats) {
    try {
      const docRef = doc(db, "parking_telemetry", "live");
      await setDoc(docRef, {
        capacity:  stats.capacity  || 60,
        occupied:  stats.occupied  || 0,
        available: stats.available || 60,
        today_in:  stats.today_entries || 0,
        today_out: stats.today_exits   || 0,
        updated_at: serverTimestamp()
      }, { merge: true });
    } catch (e) {
      // Non-blocking telemetry
    }
  }
};

// Expose globally for vanilla JS access across pages
if (typeof window !== 'undefined') {
  window.firebaseApp = app;
  window.firebaseDb = db;
  window.firebaseConfig = firebaseConfig;
  window.logParkingEvent = logParkingEvent;
  window.firebaseBridge = firebaseBridge;
}
