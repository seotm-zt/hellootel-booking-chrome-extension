const API_BASE = "https://booking-configurator.hellootel.com/api/v1/extension";


const AUTH_STATE_KEY = "authState";

async function readAuthState() {
  try {
    const result = await chrome.storage.local.get(AUTH_STATE_KEY);
    return result[AUTH_STATE_KEY] || null;
  } catch {
    return null;
  }
}

async function writeAuthState(state) {
  try {
    await chrome.storage.local.set({ [AUTH_STATE_KEY]: state });
  } catch {}
}

async function clearAuthState() {
  try {
    await chrome.storage.local.remove(AUTH_STATE_KEY);
  } catch {}
}

// Mark the session as expired (token rotated/revoked server-side → 401). Unlike a
// manual logout (which removes the key), this writes an authorized:false state
// carrying reason:"expired", so the UI can tell the two apart and prompt re-login.
async function markSessionExpired() {
  try {
    await chrome.storage.local.set({
      [AUTH_STATE_KEY]: { authorized: false, reason: "expired", savedAt: new Date().toISOString() },
    });
  } catch {}
}

async function apiLogin(username, password) {
  const response = await fetch(`${API_BASE}/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", "Accept": "application/json" },
    body: JSON.stringify({ username, password }),
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
  // Best-effort server-side revocation (clears api_token in the DB) before
  // dropping local state. Ignore failures — local logout must always succeed.
  try {
    const token = await getToken();
    if (token) {
      await fetch(`${API_BASE}/logout`, {
        method: "POST",
        headers: { "Accept": "application/json", "Authorization": `Bearer ${token}` },
      });
    }
  } catch {}
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
