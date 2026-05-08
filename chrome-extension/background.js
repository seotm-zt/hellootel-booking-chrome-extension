/**
 * Background service worker.
 *
 * Handles API requests on behalf of content scripts.
 * Fetch from a service worker is not subject to Chrome's Private Network
 * Access restriction that blocks non-secure-context pages from reaching
 * loopback addresses (tour.localhost).
 */

importScripts("auth.js");

async function authedFetch(path, options = {}) {
  const token = await getToken();
  if (!token) throw new Error("No token. Sign in to the extension.");

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
      ...(options.headers || {}),
    },
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(data.error || data.message || `HTTP ${response.status}`);
  }

  return data;
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === "SAVE_BOOKING") {
    (async () => {
      try {
        const data = await authedFetch("/bookings", {
          method: "POST",
          body: JSON.stringify(message.payload),
        });
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "LOAD_BOOKINGS") {
    (async () => {
      try {
        const data = await authedFetch("/bookings");
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "CONFIRM_BOOKING") {
    (async () => {
      try {
        const data = await authedFetch(`/bookings/${message.bookingId}/confirm`, {
          method: "PATCH",
          body: JSON.stringify(message.payload),
        });
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "SEARCH_HOTELS") {
    (async () => {
      try {
        const q = encodeURIComponent(message.query || "");
        const data = await authedFetch(`/hotels?q=${q}`);
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "GET_ROOM_TYPES") {
    (async () => {
      try {
        const data = await authedFetch(`/hotels/${message.hotelId}/room-types`);
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "DELETE_BOOKING") {
    (async () => {
      try {
        const data = await authedFetch(`/bookings/${message.bookingId}`, { method: "DELETE" });
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "LOAD_PARSERS") {
    (async () => {
      try {
        const resp = await fetch(`${API_BASE}/parsers`, {
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
        const resp = await fetch(`${API_BASE}/parser-rules`, {
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
