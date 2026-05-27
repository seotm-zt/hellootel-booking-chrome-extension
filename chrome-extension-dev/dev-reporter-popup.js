// Dev Reporter — popup-side: adds the DEV badge and "Send to Developer" button.
// This file is only loaded by the dev build's popup.html.

(function () {
  // DEV badge in the popup title
  const titleEl = document.querySelector(".popup__title");
  if (titleEl && !titleEl.querySelector(".popup__dev-badge")) {
    const badge = document.createElement("span");
    badge.className = "popup__dev-badge";
    badge.textContent = "DEV";
    titleEl.appendChild(document.createTextNode(" "));
    titleEl.appendChild(badge);
  }

  // "Send to Developer" button inside the authenticated section
  const authSection  = document.getElementById("authenticatedSection");
  const bookingsList = document.getElementById("bookingsList");
  if (!authSection || document.getElementById("sendToDev")) return;

  const btn = document.createElement("button");
  btn.id        = "sendToDev";
  btn.type      = "button";
  btn.className = "popup__button popup__button--send";
  btn.textContent = "📤 Send to Developer";

  if (bookingsList && bookingsList.parentNode === authSection) {
    authSection.insertBefore(btn, bookingsList);
  } else {
    authSection.appendChild(btn);
  }

  btn.addEventListener("click", async () => {
    btn.disabled    = true;
    btn.textContent = "Sending...";
    if (typeof hideStatus === "function") hideStatus();

    try {
      const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
      if (!tab?.id) throw new Error("No active tab found.");

      const results = await chrome.scripting.executeScript({
        target: { tabId: tab.id },
        func:   () => document.documentElement.outerHTML,
      });

      const html = results?.[0]?.result || "";
      const response = await chrome.runtime.sendMessage({
        type: "SEND_PAGE_REPORT",
        url:   tab.url   || "",
        title: tab.title || "",
        html,
      });

      if (!response?.ok) throw new Error(response?.error || "Failed");
      if (typeof showStatus === "function") showStatus("✓ Page sent to developer.");
    } catch (err) {
      if (typeof showStatus === "function") showStatus(`Failed: ${err.message || "error"}`, true);
    } finally {
      btn.disabled    = false;
      btn.textContent = "📤 Send to Developer";
    }
  });
})();
