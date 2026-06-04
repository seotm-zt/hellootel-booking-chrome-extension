/**
 * "All bookings" extension page (opened in a tab from the popup).
 *
 * Lists every ProcessedBooking of the signed-in user (GET /processed-bookings?all=1)
 * with client-side filters. View is available for all; Edit and Delete only for
 * NOT-yet-sent bookings (sent ones are read-only). Auth reuses the extension token
 * from chrome.storage (auth.js). Reuses showSentDataModal from booking-modal.js for
 * the read-only view and the manual-booking window (edit mode) for editing.
 */

let ALL = [];
const PAGE_SIZE = 20;
let currentPage = 1;
// null = default order from the API (newest added first); otherwise sort by a date column.
let sortKey = null;
let sortDir = "asc";

const $ = (id) => document.getElementById(id);

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

function showFallback(message) {
  const fb = $("mb-fallback");
  fb.hidden = false;
  fb.textContent = message;
  $("page").hidden = true;
}

function safeHttpUrl(url) {
  try {
    const p = new URL(url);
    return (p.protocol === "https:" || p.protocol === "http:") ? url : null;
  } catch {
    return null;
  }
}

function fmtDate(v) {
  if (!v) return "";
  const s = String(v).slice(0, 10);
  const [y, m, d] = s.split("-");
  return (y && m && d) ? `${d}.${m}.${y}` : s;
}

function extractErrorMessage(resp) {
  if (!resp) return "";
  try {
    const obj = typeof resp === "string" ? JSON.parse(resp) : resp;
    return obj?.message || obj?.error || (typeof resp === "string" ? resp : JSON.stringify(obj));
  } catch {
    return String(resp);
  }
}

function statusOf(b) {
  if (b.hellootel_reservation_id) return { key: "sent",     label: "Sent",            cls: "bk-badge--sent" };
  if (b.hellootel_response)       return { key: "failed",   label: "Failed",          cls: "bk-badge--failed" };
  if (!b.hotel_id)                return { key: "notfound", label: "Hotel not found", cls: "bk-badge--notfound" };
  return { key: "notsent", label: "Not sent", cls: "bk-badge--notsent" };
}

async function loadAll() {
  const res = await apiFetch("/processed-bookings?all=1");
  if (!res.ok) throw new Error("Failed to load bookings");
  const json = await res.json();
  ALL = Array.isArray(json.data) ? json.data : [];
}

function fillSelect(sel, values, allLabel) {
  const prev = sel.value;
  sel.innerHTML = "";
  const optAll = document.createElement("option");
  optAll.value = "";
  optAll.textContent = allLabel;
  sel.appendChild(optAll);
  for (const v of values) {
    const o = document.createElement("option");
    o.value = v;
    o.textContent = v;
    sel.appendChild(o);
  }
  if ([...sel.options].some((o) => o.value === prev)) sel.value = prev;
}

function populateFilters() {
  const uniq = (vals) => [...new Set(vals.filter(Boolean))].sort((a, b) => a.localeCompare(b));
  fillSelect($("f-hotel"),    uniq(ALL.map((b) => b.hotel_name)),    "All hotels");
  fillSelect($("f-operator"), uniq(ALL.map((b) => b.operator_name)), "All operators");
}

function applyFilters() {
  const q    = $("f-search").value.trim().toLowerCase();
  const st   = $("f-status").value;
  const htl  = $("f-hotel").value;
  const op   = $("f-operator").value;
  const from = $("f-from").value;
  const to   = $("f-to").value;

  return ALL.filter((b) => {
    if (st && statusOf(b).key !== st) return false;
    if (htl && (b.hotel_name || "") !== htl) return false;
    if (op && (b.operator_name || "") !== op) return false;
    const arr = (b.arrival_at || "").slice(0, 10);
    if (from && (!arr || arr < from)) return false;
    if (to && (!arr || arr > to)) return false;
    if (q) {
      const hay = `${b.booking_code || ""} ${b.hotel_name || ""}`.toLowerCase();
      if (!hay.includes(q)) return false;
    }
    return true;
  });
}

// hotel_vote is stored 10-100 (stars × 10) → 10-star display.
function ratingStars(vote) {
  if (vote == null) return "";
  const score = Math.max(0, Math.min(10, Math.round(vote / 10)));
  return "★".repeat(score) + "☆".repeat(10 - score);
}

function guestsText(b) {
  return [
    b.person_count_adults   ? `${b.person_count_adults}a`  : null,
    b.person_count_children ? `${b.person_count_children}c` : null,
    b.person_count_teens    ? `${b.person_count_teens}i`   : null,
  ].filter(Boolean).join(" / ") || "—";
}

function td(text) {
  const cell = document.createElement("td");
  cell.textContent = text ?? "";
  return cell;
}

function buildRow(b) {
  const tr = document.createElement("tr");
  const st = statusOf(b);

  // Code
  const codeCell = document.createElement("td");
  const codeSpan = document.createElement("span");
  codeSpan.className = "bk-code";
  codeSpan.textContent = b.booking_code || "—";
  codeCell.appendChild(codeSpan);
  tr.appendChild(codeCell);

  // Source
  const srcCell = document.createElement("td");
  const srcTag = document.createElement("span");
  const isParser = !!b.source_booking_id;
  srcTag.className = `bk-src ${isParser ? "bk-src--parser" : "bk-src--manual"}`;
  srcTag.textContent = isParser ? "Parser" : "Manual";
  srcCell.appendChild(srcTag);
  tr.appendChild(srcCell);

  // Hotel
  const hotelCell = td(b.hotel_name || "—");
  hotelCell.className = "bk-hotel";
  tr.appendChild(hotelCell);

  // Rating (stars)
  const ratingCell = document.createElement("td");
  const stars = ratingStars(b.hotel_vote);
  if (stars) {
    ratingCell.className = "bk-rating";
    ratingCell.textContent = stars;
    ratingCell.title = `${Math.round(b.hotel_vote / 10)}/10`;
  } else {
    ratingCell.textContent = "—";
  }
  tr.appendChild(ratingCell);

  // Room type
  const roomCell = td(b.room_type_name || "—");
  roomCell.className = "bk-roomtype";
  tr.appendChild(roomCell);

  // Operator, dates, guests, price
  tr.appendChild(td(b.operator_name || "—"));
  tr.appendChild(td(fmtDate(b.arrival_at) || "—"));
  tr.appendChild(td(fmtDate(b.departure_at) || "—"));
  tr.appendChild(td(guestsText(b)));
  tr.appendChild(td(b.price ? `${b.price} ${b.currency_code || ""}`.trim() : "—"));

  // Added (created_at)
  tr.appendChild(td(fmtDate(b.created_at) || "—"));

  // Status
  const stCell = document.createElement("td");
  const badge = document.createElement("span");
  badge.className = `bk-badge ${st.cls}`;
  badge.textContent = st.label;
  if (b.hellootel_response) badge.title = extractErrorMessage(b.hellootel_response);
  stCell.appendChild(badge);
  tr.appendChild(stCell);

  // Actions
  const actCell = document.createElement("td");
  const actions = document.createElement("div");
  actions.className = "bk-actions";

  const viewBtn = document.createElement("button");
  viewBtn.className = "bk-act";
  viewBtn.type = "button";
  viewBtn.textContent = "View";
  viewBtn.addEventListener("click", () => showSentDataModal(b));
  actions.appendChild(viewBtn);

  const url = safeHttpUrl(b.source_url || "");
  if (url) {
    const link = document.createElement("a");
    link.className = "bk-act bk-act--goto";
    link.href = url;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.title = "Open the original page";
    link.textContent = "↗";
    actions.appendChild(link);
  }

  // Edit / Delete only for not-yet-sent bookings.
  if (st.key !== "sent") {
    const editBtn = document.createElement("button");
    editBtn.className = "bk-act bk-act--edit";
    editBtn.type = "button";
    editBtn.textContent = "Edit";
    editBtn.addEventListener("click", () => openEdit(b));
    actions.appendChild(editBtn);

    const delBtn = document.createElement("button");
    delBtn.className = "bk-act bk-act--delete";
    delBtn.type = "button";
    delBtn.textContent = "Delete";
    delBtn.addEventListener("click", () => removeBooking(b, delBtn));
    actions.appendChild(delBtn);
  }

  actCell.appendChild(actions);
  tr.appendChild(actCell);

  return tr;
}

// Sort by a date column (Check-in / Check-out); empty dates go last.
function applySort(rows) {
  if (!sortKey) return rows;
  const sorted = [...rows].sort((a, b) => {
    // Full ISO strings compare lexically (handles created_at time, not just date).
    const av = a[sortKey] || "";
    const bv = b[sortKey] || "";
    if (av === bv) return 0;
    if (!av) return 1;
    if (!bv) return -1;
    return av < bv ? -1 : 1;
  });
  if (sortDir === "desc") sorted.reverse();
  return sorted;
}

function updateSortIndicators() {
  for (const th of document.querySelectorAll("th[data-sort]")) {
    const arrow = (sortKey === th.dataset.sort) ? (sortDir === "asc" ? " ▲" : " ▼") : "";
    th.textContent = th.dataset.label + arrow;
  }
}

function bindSort() {
  for (const th of document.querySelectorAll("th[data-sort]")) {
    th.dataset.label = th.textContent;
    th.addEventListener("click", () => {
      const key = th.dataset.sort;
      if (sortKey === key) {
        sortDir = sortDir === "asc" ? "desc" : "asc";
      } else {
        sortKey = key;
        sortDir = "asc";
      }
      currentPage = 1;
      updateSortIndicators();
      renderRows();
    });
  }
  updateSortIndicators();
}

function renderPager(total) {
  const pager = $("bk-pager");
  pager.innerHTML = "";
  const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  if (pages <= 1) return;

  const prev = document.createElement("button");
  prev.className = "bk__btn bk__btn--ghost";
  prev.type = "button";
  prev.textContent = "‹ Prev";
  prev.disabled = currentPage <= 1;
  prev.addEventListener("click", () => { currentPage--; renderRows(); });

  const info = document.createElement("span");
  info.className = "bk__pager-info";
  info.textContent = `Page ${currentPage} / ${pages}`;

  const next = document.createElement("button");
  next.className = "bk__btn bk__btn--ghost";
  next.type = "button";
  next.textContent = "Next ›";
  next.disabled = currentPage >= pages;
  next.addEventListener("click", () => { currentPage++; renderRows(); });

  pager.append(prev, info, next);
}

function renderRows() {
  const rows = applySort(applyFilters());
  const total = rows.length;
  const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  currentPage = Math.min(Math.max(1, currentPage), pages);

  const start = (currentPage - 1) * PAGE_SIZE;
  const pageRows = rows.slice(start, start + PAGE_SIZE);

  $("bk-count").textContent = total
    ? `Showing ${start + 1}–${Math.min(start + PAGE_SIZE, total)} of ${total}`
    : "0 bookings";

  const tbody = $("bk-rows");
  tbody.innerHTML = "";

  if (!total) {
    const tr = document.createElement("tr");
    const cell = document.createElement("td");
    cell.className = "bk__empty";
    cell.colSpan = 13;
    cell.textContent = "No bookings match the filters.";
    tr.appendChild(cell);
    tbody.appendChild(tr);
    renderPager(0);
    return;
  }

  for (const b of pageRows) tbody.appendChild(buildRow(b));
  renderPager(total);
}

// Open the manual-booking window in edit mode for this record. The window
// matches the current browser window's height (full height), centered.
async function openEdit(b) {
  try {
    await new Promise((resolve) => chrome.storage.local.set({ ttbEditBooking: b }, resolve));
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
  } catch (e) {
    console.error("[TTB] openEdit failed", e);
  }
}

async function removeBooking(b, btn) {
  if (!confirm(`Delete booking ${b.booking_code || "#" + b.id}?`)) return;
  btn.disabled = true;
  try {
    const res = await apiFetch(`/processed-bookings/${b.id}`, { method: "DELETE" });
    if (!res.ok) throw new Error("delete failed");
    await reload();
  } catch {
    btn.disabled = false;
    alert("Failed to delete booking.");
  }
}

function bindFilters() {
  const onChange = () => { currentPage = 1; renderRows(); };
  for (const id of ["f-search", "f-status", "f-hotel", "f-operator", "f-from", "f-to"]) {
    const el = $(id);
    el.addEventListener("input", onChange);
    el.addEventListener("change", onChange);
  }
  $("f-clear").addEventListener("click", () => {
    for (const id of ["f-search", "f-status", "f-hotel", "f-operator", "f-from", "f-to"]) $(id).value = "";
    currentPage = 1;
    renderRows();
  });
}

async function reload() {
  try {
    await loadAll();
    populateFilters();
    renderRows();
  } catch {
    showFallback("Failed to load bookings from the server.");
  }
}

async function init() {
  if (!(await isAuthorized())) {
    showFallback("Please sign in to the extension (popup) first, then reopen this page.");
    return;
  }
  ensureOperators(); // operator names for the read-only view modal
  $("page").hidden = false;
  bindFilters();
  bindSort();
  $("bk-refresh").addEventListener("click", reload);
  // Refresh when returning to this tab (e.g. after editing in the popup window).
  window.addEventListener("focus", reload);
  await reload();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
  init();
}
