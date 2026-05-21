const loginSection        = document.getElementById("loginSection");
const loginForm           = document.getElementById("loginForm");
const loginUsername       = document.getElementById("loginUsername");
const loginPassword       = document.getElementById("loginPassword");
const loginSubmit         = document.getElementById("loginSubmit");
const authenticatedSection = document.getElementById("authenticatedSection");
const userNameLabel       = document.getElementById("userNameLabel");
const logoutButton        = document.getElementById("logoutButton");
const sendToDevButton     = document.getElementById("sendToDev");
const statusBox           = document.getElementById("status");

function showStatus(message, isError = false) {
  statusBox.hidden = false;
  statusBox.textContent = message;
  statusBox.className = `popup__status${isError ? " popup__status--error" : ""}`;
}

function hideStatus() {
  statusBox.hidden = true;
  statusBox.textContent = "";
}

async function render() {
  hideStatus();
  const auth = await getAuthState();

  if (!auth || !auth.authorized || !auth.token) {
    loginSection.hidden = false;
    authenticatedSection.hidden = true;
    return;
  }

  loginSection.hidden = true;
  authenticatedSection.hidden = false;
  userNameLabel.textContent = auth.user?.name || auth.user?.username || "";
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

sendToDevButton.addEventListener("click", async () => {
  sendToDevButton.disabled = true;
  sendToDevButton.textContent = "Sending...";
  hideStatus();

  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    if (!tab?.id) {
      showStatus("No active tab found.", true);
      return;
    }

    const results = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      func: () => document.documentElement.outerHTML,
    });

    const html = results?.[0]?.result || "";
    const token = await getToken();

    const response = await fetch(`${API_BASE}/page-report`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        ...(token ? { "Authorization": `Bearer ${token}` } : {}),
      },
      body: JSON.stringify({
        url:   tab.url   || "",
        title: tab.title || "",
        html,
      }),
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    showStatus("Page sent! Thank you.");
  } catch (err) {
    showStatus(`Failed: ${err.message || "error"}`, true);
  } finally {
    sendToDevButton.disabled = false;
    sendToDevButton.textContent = "📤 Send to Developer";
  }
});

chrome.storage.onChanged.addListener((changes, area) => {
  if (area === "local" && changes[AUTH_STATE_KEY]) render().catch(console.error);
});

render().catch(console.error);
