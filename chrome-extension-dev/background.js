importScripts("auth.js");

chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (message.type === "GET_TOKEN") {
    getToken().then(token => sendResponse({ token }));
    return true;
  }
});
