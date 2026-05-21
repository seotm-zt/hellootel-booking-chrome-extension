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

const STATUS_MAP = {
  "Не подтверждено": "Unconfirmed",
  "Подтверждено":    "Confirmed",
  "Оплачено":        "Paid",
  "Не оплачено":     "Unpaid",
  "Отменено":        "Cancelled",
  "Ожидает":         "Pending",
  "Обработано":      "Processed",
};

function translateStatus(s) {
  return STATUS_MAP[s?.trim()] ?? s;
}

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
  return Array.isArray(statuses)
    ? statuses.filter(Boolean).map(translateStatus).join(", ")
    : "";
}

function safeHttpUrl(url) {
  try {
    const parsed = new URL(url);
    return (parsed.protocol === "https:" || parsed.protocol === "http:") ? url : null;
  } catch {
    return null;
  }
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

function renderEmptyState(allConfirmed = false) {
  bookingsList.innerHTML = allConfirmed
    ? '<div class="popup__empty popup__empty--done">✓ All bookings confirmed</div>'
    : '<div class="popup__empty">No bookings yet. Click the Save button on a booking page.</div>';
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

async function loadBookings() {
  const response = await apiFetch("/bookings");
  if (!response.ok) throw new Error("Failed to load bookings");
  const json = await response.json();
  return Array.isArray(json.data) ? json.data : [];
}

async function deleteBookingFromServer(id) {
  const response = await apiFetch(`/bookings/${id}`, { method: "DELETE" });
  if (!response.ok) throw new Error("Failed to delete booking");
}

function buildBookingCard(booking) {
  const code     = normalizeText(booking.booking_code || "No code");
  const title    = normalizeText(booking.hotel_name   || "Untitled");
  const subtitle = normalizeText(booking.subtitle);
  const safeUrl  = safeHttpUrl(booking.source_url || "");

  const article = document.createElement("article");
  article.className = "booking-card";

  // Top row
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

  if (subtitle) {
    const subEl = document.createElement("div");
    subEl.className = "booking-card__subtitle";
    subEl.textContent = subtitle;
    info.appendChild(subEl);
  }

  top.appendChild(info);

  if (safeUrl) {
    const link = document.createElement("a");
    link.className = "booking-card__goto";
    link.href = safeUrl;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.title = "Go to booking page";
    link.textContent = "↗";
    top.appendChild(link);
  }

  article.appendChild(top);

  // Meta rows
  const meta = document.createElement("div");
  meta.className = "booking-card__meta";
  for (const row of [
    createMetaRow("Dates",  booking.stay_dates),
    createMetaRow("Guests", booking.guests),
    createMetaRow("Status", formatStatuses(booking.statuses)),
  ]) {
    if (row) meta.appendChild(row);
  }
  article.appendChild(meta);

  // Footer
  const footer = document.createElement("div");
  footer.className = "booking-card__footer";

  const price = document.createElement("div");
  price.className = "booking-card__price";
  price.textContent = normalizeText(booking.total_price || "—");
  footer.appendChild(price);

  const removeBtn = document.createElement("button");
  removeBtn.className = "booking-card__remove";
  removeBtn.dataset.removeId = booking.id;
  removeBtn.type = "button";
  removeBtn.textContent = "Remove";
  footer.appendChild(removeBtn);

  article.appendChild(footer);

  return article;
}

function renderBookings(bookings) {
  const unconfirmed = bookings.filter(b => !b.processed_booking?.confirmed_at);

  if (!bookings.length) { renderEmptyState(false); return; }
  if (!unconfirmed.length) { renderEmptyState(true); return; }

  bookingCount.textContent = String(unconfirmed.length);
  bookingCount.hidden = false;

  bookingsList.innerHTML = "";

  for (const booking of unconfirmed) {
    bookingsList.appendChild(buildBookingCard(booking));
  }

  for (const button of bookingsList.querySelectorAll("[data-remove-id]")) {
    button.addEventListener("click", async () => {
      try {
        await deleteBookingFromServer(button.dataset.removeId);
        await render();
        showStatus("Booking removed.");
      } catch {
        showStatus("Failed to remove booking.", true);
      }
    });
  }
}

async function render() {
  hideStatus();
  const auth = await getAuthState();

  if (!auth || !auth.authorized || !auth.token) {
    loginSection.hidden = false;
    authenticatedSection.hidden = true;
    bookingCount.hidden = true;
    return;
  }

  loginSection.hidden = true;
  authenticatedSection.hidden = false;
  userNameLabel.textContent = auth.user?.name || auth.user?.username || "";

  try {
    const bookings = await loadBookings();
    renderBookings(bookings);
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

chrome.storage.onChanged.addListener((changes, area) => {
  if (area === "local" && changes[AUTH_STATE_KEY]) render().catch(console.error);
});

render().catch((err) => {
  console.error("popup render failed", err);
  showStatus("Failed to load data.", true);
});
