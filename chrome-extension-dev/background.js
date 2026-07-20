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

  // Token rotated/revoked server-side (ApiTokenAuth returns 401). The locally
  // stored token is dead — mark the session expired so the UI prompts re-login
  // instead of silently failing (e.g. parsers never load → no save buttons).
  if (response.status === 401) {
    await markSessionExpired();
    const err = new Error(data.error || data.message || "Session expired");
    err.status = 401;
    throw err;
  }

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

  if (message.type === "STORE_PROCESSED_DIRECT") {
    (async () => {
      try {
        const data = await authedFetch(`/processed-bookings/direct`, {
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

  if (message.type === "LOAD_PROCESSED") {
    (async () => {
      try {
        const data = await authedFetch(`/processed-bookings`);
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "UPDATE_PROCESSED_DIRECT") {
    (async () => {
      try {
        const data = await authedFetch(`/processed-bookings/${message.id}`, {
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

  if (message.type === "DELETE_PROCESSED") {
    (async () => {
      try {
        const data = await authedFetch(`/processed-bookings/${message.id}`, { method: "DELETE" });
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
        const qs = new URLSearchParams();
        if (message.arrivalAt)   qs.set("arrival_at",   message.arrivalAt);
        if (message.departureAt) qs.set("departure_at", message.departureAt);
        const suffix = qs.toString() ? `?${qs.toString()}` : "";
        const data = await authedFetch(`/hotels/${message.hotelId}/room-types${suffix}`);
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "GET_HOTEL_VOTE") {
    (async () => {
      try {
        const data = await authedFetch(`/hotels/${message.hotelId}/vote`);
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
        // Parsers now require auth — send the bearer token like the other calls.
        const data = await authedFetch("/parsers");
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "GET_OPERATORS") {
    (async () => {
      try {
        const data = await authedFetch("/operators");
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "GET_COUNTRIES") {
    (async () => {
      try {
        const data = await authedFetch("/countries");
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "GET_CITIES") {
    (async () => {
      try {
        const data = await authedFetch("/cities");
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "LOAD_CURRENCIES") {
    (async () => {
      try {
        const data = await authedFetch("/currencies");
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.type === "LOAD_RULES") {
    (async () => {
      try {
        const data = await authedFetch("/parser-rules");
        sendResponse({ ok: true, data });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }
});

// Dev build only: register the SEND_PAGE_REPORT service-worker handler.
importScripts("dev-reporter-bg.js");
