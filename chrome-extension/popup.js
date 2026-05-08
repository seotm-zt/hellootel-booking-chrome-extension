
const sendToDevButton = document.getElementById("sendToDev");

const bookingCount = document.getElementById("bookingCount");
const bookingsList = document.getElementById("bookingsList");
const statusBox = document.getElementById("status");

const loginSection = document.getElementById("loginSection");
const loginForm = document.getElementById("loginForm");
const loginEmail = document.getElementById("loginEmail");
const loginPassword = document.getElementById("loginPassword");
const loginSubmit = document.getElementById("loginSubmit");

const authenticatedSection = document.getElementById("authenticatedSection");
const userNameLabel = document.getElementById("userNameLabel");
const logoutButton = document.getElementById("logoutButton");
const exportButton = document.getElementById("exportJson");
const clearButton = document.getElementById("clearBookings");

function normalizeText(value) {
  return (value || "").replace(/\s+/g, " ").trim();
}

function showStatus(message, isError = false) {
  statusBox.hidden = false;
  statusBox.textContent = message;
  statusBox.className = `popup__status${isError ? " popup__status--error" : ""}`;
}

function hideStatus() {
  statusBox.hidden = true;
  statusBox.textContent = "";
}

function formatStatuses(statuses) {
  return Array.isArray(statuses) ? statuses.filter(Boolean).join(", ") : "";
}

function createMetaRow(label, value) {
  if (!normalizeText(value)) return "";
  return `
    <div class="booking-card__row">
      <strong>${label}</strong>
      <span class="booking-card__value">${value}</span>
    </div>
  `;
}

function renderEmptyState(allConfirmed = false) {
  bookingsList.innerHTML = allConfirmed
    ? '<div class="popup__empty popup__empty--done">✓ All bookings confirmed</div>'
    : '<div class="popup__empty">No bookings. Click the save button on a booking page.</div>';
  bookingCount.textContent = "0";
}

async function apiFetch(path, options = {}) {
  const token = await getToken();
  const headers = {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(options.headers || {}),
  };

  return fetch(`${API_BASE}${path}`, { ...options, headers });
}

async function loadBookings() {
  const response = await apiFetch("/bookings");

  if (!response.ok) {
    throw new Error("Failed to load bookings");
  }

  const json = await response.json();
  return Array.isArray(json.data) ? json.data : [];
}

async function deleteBookingFromServer(id) {
  const response = await apiFetch(`/bookings/${id}`, { method: "DELETE" });

  if (!response.ok) {
    throw new Error("Failed to delete booking");
  }
}

function renderBookings(bookings) {
  const unconfirmed = bookings.filter(b => !b.processed_booking?.confirmed_at);

  if (!bookings.length) {
    renderEmptyState(false);
    return;
  }

  if (!unconfirmed.length) {
    renderEmptyState(true);
    return;
  }

  bookingCount.textContent = String(unconfirmed.length);
  bookingsList.innerHTML = unconfirmed
    .map((booking) => {
      const code     = normalizeText(booking.booking_code || "No code");
      const title    = normalizeText(booking.hotel_name || "Untitled");
      const subtitle = normalizeText(booking.subtitle);
      const sourceUrl = booking.source_url || "";

      return `
        <article class="booking-card" data-booking-id="${booking.id}">
          <div class="booking-card__top">
            <div>
              <div class="booking-card__code">${code}</div>
              <div class="booking-card__title">${title}</div>
              ${subtitle ? `<div class="booking-card__subtitle">${subtitle}</div>` : ""}
            </div>
            ${sourceUrl ? `<a class="booking-card__goto" href="${sourceUrl}" target="_blank" title="Go to booking page">↗</a>` : ""}
          </div>
          <div class="booking-card__meta">
            ${createMetaRow("Dates", booking.stay_dates)}
            ${createMetaRow("Guests", booking.guests)}
            ${createMetaRow("Meal plan", booking.meal_plan)}
            ${createMetaRow("Status", formatStatuses(booking.statuses))}
          </div>
          <div class="booking-card__footer">
            <div class="booking-card__price">${normalizeText(booking.total_price || "—")}</div>
            <button class="booking-card__remove" data-remove-id="${booking.id}" type="button">Remove</button>
          </div>
        </article>
      `;
    })
    .join("");

  for (const button of bookingsList.querySelectorAll("[data-remove-id]")) {
    button.addEventListener("click", async () => {
      const id = button.dataset.removeId;
      try {
        await deleteBookingFromServer(id);
        await render();
        showStatus("Booking deleted.");
      } catch {
        showStatus("Failed to delete booking.", true);
      }
    });
  }
}

async function exportJson() {
  try {
    const bookings = await loadBookings();
    if (!bookings.length) {
      showStatus("Nothing to export: database is empty.");
      return;
    }

    const blob = new Blob([JSON.stringify(bookings, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `bookings-${new Date().toISOString().slice(0, 10)}.json`;
    link.click();
    URL.revokeObjectURL(url);
    showStatus("JSON exported.");
  } catch {
    showStatus("Failed to export.", true);
  }
}

async function render() {
  hideStatus();
  const auth = await getAuthState();

  if (!auth || !auth.authorized || !auth.token) {
    loginSection.hidden = false;
    authenticatedSection.hidden = true;
    bookingCount.textContent = "0";
    return;
  }

  loginSection.hidden = true;
  authenticatedSection.hidden = false;
  userNameLabel.textContent = auth.user?.name || auth.user?.email || "";

  try {
    const bookings = await loadBookings();
    renderBookings(bookings);
  } catch {
    showStatus("Failed to load bookings from the server.", true);
  }
}

loginForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  loginSubmit.disabled = true;
  loginSubmit.textContent = "Signing in...";
  hideStatus();

  try {
    await apiLogin(loginEmail.value.trim(), loginPassword.value);
    loginPassword.value = "";
    await render();
  } catch (error) {
    showStatus(error.message || "Invalid email or password.", true);
  } finally {
    loginSubmit.disabled = false;
    loginSubmit.textContent = "Sign in";
  }
});

logoutButton.addEventListener("click", async () => {
  await apiLogout();
  await render();
});

exportButton.addEventListener("click", exportJson);

clearButton.addEventListener("click", async () => {
  if (!confirm("Delete all bookings from the server database?")) return;

  try {
    const bookings = await loadBookings();
    await Promise.all(bookings.map((b) => deleteBookingFromServer(b.id)));
    await render();
    showStatus("Database cleared.");
  } catch {
    showStatus("Failed to clear the database.", true);
  }
});

async function sendPageReport() {
  sendToDevButton.disabled = true;
  sendToDevButton.textContent = "Sending...";
  hideStatus();

  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    if (!tab || !tab.id) {
      showStatus("No active tab found.", true);
      return;
    }

    const results = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      func: () => document.documentElement.outerHTML,
    });

    const html = results?.[0]?.result || "";

    const response = await fetch(`${API_BASE}/page-report`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        url: tab.url || "",
        title: tab.title || "",
        html,
      }),
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    showStatus("Page sent to developer. Thank you!");
  } catch (err) {
    showStatus(`Failed to send: ${err.message || "error"}`, true);
  } finally {
    sendToDevButton.disabled = false;
    sendToDevButton.textContent = "Send to Developer";
  }
}

sendToDevButton.addEventListener("click", sendPageReport);


chrome.storage.onChanged.addListener((changes, areaName) => {
  if (areaName === "local" && changes[AUTH_STATE_KEY]) {
    render().catch(console.error);
  }
});

render().catch((error) => {
  console.error("popup render failed", error);
  showStatus("Failed to load data.", true);
});
