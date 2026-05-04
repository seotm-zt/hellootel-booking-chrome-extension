/**
 * Background service worker.
 *
 * Handles API requests on behalf of content scripts.
 * Fetch from a service worker is not subject to Chrome's Private Network
 * Access restriction that blocks non-secure-context pages from reaching
 * loopback addresses (tour.localhost).
 */

importScripts("toptravel-auth.js");

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === "SAVE_BOOKING") {
    (async () => {
      try {
        const token = await getToken();
        if (!token) {
          sendResponse({ ok: false, error: "No token. Sign in to the extension." });
          return;
        }

        const response = await fetch(`${TOPTRAVEL_API_BASE}/bookings`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify(message.payload),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
          sendResponse({ ok: false, error: data.error || data.message || `HTTP ${response.status}` });
        } else {
          sendResponse({ ok: true, data });
        }
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();

    return true;
  }

  // Parser loading is routed through the background SW so it bypasses
  // Chrome's Private Network Access restrictions that block content scripts
  // on public-IP pages from reaching tour.localhost (loopback).

  if (message.type === "LOAD_PARSERS") {
    (async () => {
      try {
        const resp = await fetch(`${TOPTRAVEL_API_BASE}/parsers`, {
          headers: { Accept: "application/json" },
        });
        const data = resp.ok ? await resp.json() : null;
        sendResponse({ ok: resp.ok, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "LOAD_RULES") {
    (async () => {
      try {
        const resp = await fetch(`${TOPTRAVEL_API_BASE}/parser-rules`, {
          headers: { Accept: "application/json" },
        });
        const data = resp.ok ? await resp.json() : null;
        sendResponse({ ok: resp.ok, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }
});
