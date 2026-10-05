/**
 * Mombasa Mall Basement Parking - Firebase Configuration & Initialization
 * Project: mombasa-mall-parking
 *
 * Provides initialized Firebase app, analytics (with browser compatibility check),
 * and Firestore instances for real-time synchronization and telemetry.
 */

// Import modern modular Firebase SDK via CDN (compatible with standard browser modules)
import { initializeApp, getApps, getApp } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-app.js";
import { getAnalytics, isSupported, logEvent } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-analytics.js";
import { getFirestore } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js";

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

// Expose globally for vanilla JS access across pages
if (typeof window !== 'undefined') {
  window.firebaseApp = app;
  window.firebaseDb = db;
  window.firebaseConfig = firebaseConfig;
  window.logParkingEvent = logParkingEvent;
}
