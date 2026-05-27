// Dev Reporter — service-worker side: handles SEND_PAGE_REPORT messages.
// This file is only imported by the dev build's background.js.
//
// Relies on `authedFetch` defined in background.js. The listener fires
// asynchronously after the service worker has finished its top-level setup,
// so authedFetch is guaranteed to be in scope when a message arrives.

chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (message.type !== "SEND_PAGE_REPORT") return;
  (async () => {
    try {
      const data = await authedFetch("/page-report", {
        method: "POST",
        body: JSON.stringify({
          url:   message.url   || "",
          title: message.title || "",
          html:  message.html  || "",
        }),
      });
      sendResponse({ ok: true, data });
    } catch (err) {
      sendResponse({ ok: false, error: err.message });
    }
  })();
  return true;
});
