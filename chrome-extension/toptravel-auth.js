const TOPTRAVEL_API_BASE = "https://booking-configurator.hellootel.com/api/v1/extension";
//const TOPTRAVEL_API_BASE = "http://booking.localhost/api/v1/extension";

const TOPTRAVEL_AUTH_STATE_KEY = "toptravelAuthState";

async function readAuthState() {
  const result = await chrome.storage.local.get(TOPTRAVEL_AUTH_STATE_KEY);
  return result[TOPTRAVEL_AUTH_STATE_KEY] || null;
}

async function writeAuthState(state) {
  await chrome.storage.local.set({ [TOPTRAVEL_AUTH_STATE_KEY]: state });
}

async function clearAuthState() {
  await chrome.storage.local.remove(TOPTRAVEL_AUTH_STATE_KEY);
}

async function apiLogin(email, password) {
  const response = await fetch(`${TOPTRAVEL_API_BASE}/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", "Accept": "application/json" },
    body: JSON.stringify({ email, password }),
  });

  const data = await response.json();

  if (!response.ok) {
    throw new Error(data.error || "Login failed");
  }

  const state = {
    authorized: true,
    token: data.token,
    user: data.user,
    savedAt: new Date().toISOString(),
  };

  await writeAuthState(state);
  return state;
}

async function apiLogout() {
  await clearAuthState();
}

async function getAuthState() {
  return await readAuthState();
}

async function isAuthorized() {
  const state = await readAuthState();
  return !!(state && state.authorized && state.token);
}

async function getToken() {
  const state = await readAuthState();
  return state?.token || null;
}
