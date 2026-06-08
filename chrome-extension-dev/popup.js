const bookingCount        = document.getElementById("bookingCount");
const bookingsList        = document.getElementById("bookingsList");
const statusBox           = document.getElementById("status");

const loginSection        = document.getElementById("loginSection");
const loginForm           = document.getElementById("loginForm");
const loginUsername       = document.getElementById("loginUsername");
const loginPassword       = document.getElementById("loginPassword");
const loginSubmit         = document.getElementById("loginSubmit");

const authenticatedSection = document.getElementById("authenticatedSection");
const userNameLabel       = document.getElementById("userNameLabel");
const logoutButton        = document.getElementById("logoutButton");
const addBookingManual    = document.getElementById("addBookingManual");
const viewAllBookings     = document.getElementById("viewAllBookings");

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

function safeHttpUrl(url) {
  try {
    const parsed = new URL(url);
    return (parsed.protocol === "https:" || parsed.protocol === "http:") ? url : null;
  } catch {
    return null;
  }
}

function fmtDateShort(v) {
  if (!v) return "";
  const s = String(v).slice(0, 10); // ISO → YYYY-MM-DD
  const [y, m, d] = s.split("-");
  return (y && m && d) ? `${d}.${m}.${y}` : s;
}

function createMetaRow(label, value) {
  if (!normalizeText(value)) return null;
  const row = document.createElement("div");
  row.className = "booking-card__row";
  const strong = document.createElement("strong");
  strong.textContent = label;
  const span = document.createElement("span");
  span.className = "booking-card__value";
  span.textContent = value;
  row.append(strong, span);
  return row;
}

function renderEmptyState() {
  bookingsList.innerHTML =
    '<div class="popup__empty">No bookings to send. Use “+ Add booking manually”, or the “Send to HelloOtel” button on a booking page.</div>';
  bookingCount.hidden = true;
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

// The popup lists ProcessedBookings that have NOT been sent to HelloOtel yet
// (the endpoint already filters out sent ones). Manual bookings appear here too
// since they have no source ExtensionBooking.
async function loadProcessed() {
  const response = await apiFetch("/processed-bookings");
  if (!response.ok) throw new Error("Failed to load bookings");
  const json = await response.json();
  return Array.isArray(json.data) ? json.data : [];
}

async function deleteProcessed(id) {
  const response = await apiFetch(`/processed-bookings/${id}`, { method: "DELETE" });
  if (!response.ok) throw new Error("Failed to delete booking");
}

// ── Manual-booking window (shared by "Add" and "Edit") ──────────────────

function openManualWindow() {
  return (async () => {
    const width = 620;
    let height = 820;
    try {
      const win = await chrome.windows.getCurrent();
      if (win?.height) height = win.height;
    } catch { /* keep default height */ }
    const sw = window.screen?.availWidth  ?? width;
    const sh = window.screen?.availHeight ?? height;
    const left = Math.max(0, Math.round((sw - width)  / 2));
    const top  = Math.max(0, Math.round((sh - height) / 2));
    chrome.windows.create({
      url: chrome.runtime.getURL("manual-booking.html"),
      type: "popup",
      width, height, left, top,
    });
  })();
}

function setEditBooking(record) {
  return new Promise((resolve) => {
    try { chrome.storage.local.set({ ttbEditBooking: record }, resolve); }
    catch { resolve(); }
  });
}

function clearEditBooking() {
  return new Promise((resolve) => {
    try { chrome.storage.local.remove("ttbEditBooking", resolve); }
    catch { resolve(); }
  });
}

// ── Booking cards ───────────────────────────────────────────────────────

// Pull a human-readable message out of the stored HelloOtel API response
// (a JSON string like {"message":"..."} or {"error":"..."}).
function extractErrorMessage(resp) {
  if (!resp) return "";
  try {
    const obj = typeof resp === "string" ? JSON.parse(resp) : resp;
    return obj?.message || obj?.error || (typeof resp === "string" ? resp : JSON.stringify(obj));
  } catch {
    return String(resp);
  }
}

function getBookingStatus(b) {
  if (b.hellootel_response) return { label: "Failed to send", cls: "popup__status-badge--failed" };
  if (!b.hotel_id)          return { label: "Hotel not found in HelloOtel", cls: "popup__status-badge--notfound" };
  return { label: "Not sent yet", cls: "popup__status-badge--ready" };
}

function buildBookingCard(b) {
  const code  = normalizeText(b.booking_code || "No code");
  const title = normalizeText(b.hotel_name   || "Untitled");

  const article = document.createElement("article");
  article.className = "booking-card";

  const top = document.createElement("div");
  top.className = "booking-card__top";

  const info = document.createElement("div");

  const codeEl = document.createElement("div");
  codeEl.className = "booking-card__code";
  codeEl.textContent = code;
  info.appendChild(codeEl);

  const titleEl = document.createElement("div");
  titleEl.className = "booking-card__title";
  titleEl.textContent = title;
  info.appendChild(titleEl);

  top.appendChild(info);

  // Link to the original page (parser bookings only) for verifying parsed data.
  const safeUrl = safeHttpUrl(b.source_url || "");
  if (safeUrl) {
    const link = document.createElement("a");
    link.className = "booking-card__goto";
    link.href = safeUrl;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.title = "Open the original page";
    link.textContent = "↗";
    top.appendChild(link);
  }

  article.appendChild(top);

  const meta = document.createElement("div");
  meta.className = "booking-card__meta";
  const dates  = [fmtDateShort(b.arrival_at), fmtDateShort(b.departure_at)].filter(Boolean).join(" – ");
  const guests = [
    b.person_count_adults   ? `${b.person_count_adults} ad`  : null,
    b.person_count_children ? `${b.person_count_children} ch` : null,
    b.person_count_teens    ? `${b.person_count_teens} inf`  : null,
  ].filter(Boolean).join(", ");
  for (const row of [createMetaRow("Dates", dates), createMetaRow("Guests", guests)]) {
    if (row) meta.appendChild(row);
  }
  article.appendChild(meta);

  const status = getBookingStatus(b);
  if (status) {
    const badge = document.createElement("div");
    badge.className = `popup__status-badge ${status.cls}`;
    badge.textContent = status.label;
    if (b.hellootel_response) badge.title = extractErrorMessage(b.hellootel_response);
    article.appendChild(badge);
  }

  const footer = document.createElement("div");
  footer.className = "booking-card__footer";

  const price = document.createElement("div");
  price.className = "booking-card__price";
  price.textContent = b.price ? `${b.price} ${b.currency_code || ""}`.trim() : "—";
  footer.appendChild(price);

  const editBtn = document.createElement("button");
  editBtn.className = "booking-card__edit";
  editBtn.type = "button";
  editBtn.textContent = "Edit";
  footer.appendChild(editBtn);

  const removeBtn = document.createElement("button");
  removeBtn.className = "booking-card__remove";
  removeBtn.type = "button";
  removeBtn.textContent = "Remove";
  footer.appendChild(removeBtn);

  article.appendChild(footer);

  return { article, editBtn, removeBtn };
}

function renderBookings(list) {
  if (!list.length) { renderEmptyState(); return; }

  bookingCount.textContent = String(list.length);
  bookingCount.hidden = false;
  bookingsList.innerHTML = "";

  for (const booking of list) {
    const { article, editBtn, removeBtn } = buildBookingCard(booking);

    // Edit → stash the record and open the manual-booking window in edit mode.
    editBtn.addEventListener("click", async () => {
      await setEditBooking(booking);
      await openManualWindow();
    });

    removeBtn.addEventListener("click", async () => {
      removeBtn.disabled = true;
      try {
        await deleteProcessed(booking.id);
        await render();
        showStatus("Booking removed.");
      } catch {
        removeBtn.disabled = false;
        showStatus("Failed to remove booking.", true);
      }
    });

    bookingsList.appendChild(article);
  }
}

async function render() {
  hideStatus();
  const auth = await getAuthState();

  if (!auth || !auth.authorized || !auth.token) {
    loginSection.hidden = false;
    authenticatedSection.hidden = true;
    bookingCount.hidden = true;
    // Token rotated/revoked server-side (e.g. signed in on another device).
    if (auth?.reason === "expired") {
      showStatus("Session expired — please sign in again.", true);
    }
    return;
  }

  loginSection.hidden = true;
  authenticatedSection.hidden = false;
  const displayName = auth.user?.name || auth.user?.username || "";
  const loginNum    = auth.user?.username;
  userNameLabel.textContent = "";
  const nameNode = document.createTextNode(displayName);
  userNameLabel.appendChild(nameNode);
  if (loginNum) {
    userNameLabel.appendChild(document.createTextNode(" "));
    const loginSpan = document.createElement("span");
    loginSpan.className = "popup__user-login";
    loginSpan.textContent = `(${loginNum})`;
    userNameLabel.appendChild(loginSpan);
  }

  try {
    const list = await loadProcessed();
    renderBookings(list);
  } catch {
    showStatus("Failed to load bookings from the server.", true);
  }
}

loginForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  loginSubmit.disabled = true;
  loginSubmit.textContent = "Signing in...";
  hideStatus();
  try {
    await apiLogin(loginUsername.value.trim(), loginPassword.value);
    loginPassword.value = "";
    await render();
  } catch (err) {
    showStatus(err.message || "Invalid credentials.", true);
  } finally {
    loginSubmit.disabled = false;
    loginSubmit.textContent = "Sign in";
  }
});

logoutButton.addEventListener("click", async () => {
  await apiLogout();
  await render();
});

// Manual booking: open the form full-size in its own window (the action popup
// is too narrow). Clear any leftover edit record so "Add" always starts blank.
addBookingManual.addEventListener("click", async () => {
  await clearEditBooking();
  await openManualWindow();
});

// Open the full "All bookings" page (with filters) in a browser tab.
viewAllBookings.addEventListener("click", () => {
  chrome.tabs.create({ url: chrome.runtime.getURL("bookings.html") });
});

chrome.storage.onChanged.addListener((changes, area) => {
  if (area === "local" && changes[AUTH_STATE_KEY]) render().catch(console.error);
});

render().catch((err) => {
  console.error("popup render failed", err);
  showStatus("Failed to load data.", true);
});
