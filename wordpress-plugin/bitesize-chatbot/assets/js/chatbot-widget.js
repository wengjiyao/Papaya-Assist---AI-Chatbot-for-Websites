(function () {
  "use strict";

  var script = document.currentScript;
  var cfg = window.__bitesize || {};
  const API_URL = (script && script.getAttribute("data-api-url")) || cfg.apiUrl || "";
  const STREAM_URL = (script && script.getAttribute("data-stream-url")) || cfg.streamUrl || "";
  const PRIMARY_COLOR = (script && script.getAttribute("data-primary-color")) || cfg.primaryColor || "#4f46e5";
  const TITLE = (script && script.getAttribute("data-title")) || cfg.title || "Chat with us";
  const TENANT_ID = (script && script.getAttribute("data-tenant-id")) || cfg.tenantId || "";
  const USER_TOKEN = (script && script.getAttribute("data-user-token")) || cfg.userToken || "";
  const MAX_HISTORY = 10;

  const host = document.createElement("div");
  host.id = "chatbot-widget-host";
  document.body.appendChild(host);

  const shadow = host.attachShadow({ mode: "closed" });

  const styles = `
    * { margin: 0; padding: 0; box-sizing: border-box; }

    .cb-bubble {
      position: fixed;
      bottom: 24px;
      right: 24px;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: ${PRIMARY_COLOR};
      color: #fff;
      border: none;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 99999;
      transition: transform 0.2s;
    }
    .cb-bubble:hover { transform: scale(1.08); }
    .cb-bubble svg { width: 28px; height: 28px; fill: #fff; }

    .cb-label {
      position: fixed;
      bottom: 36px;
      right: 90px;
      background: #fff;
      color: #333;
      padding: 8px 14px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      box-shadow: 0 2px 10px rgba(0,0,0,0.15);
      z-index: 99999;
      white-space: nowrap;
      animation: cb-label-fade 1s ease-out;
    }
    .cb-label::after {
      content: "";
      position: absolute;
      top: 50%;
      right: -6px;
      transform: translateY(-50%);
      border: 6px solid transparent;
      border-left-color: #fff;
      border-right: none;
    }
    @keyframes cb-label-fade {
      from { opacity: 0; transform: translateX(8px); }
      to { opacity: 1; transform: translateX(0); }
    }

    .cb-window {
      position: fixed;
      bottom: 92px;
      right: 24px;
      width: 380px;
      height: 520px;
      border-radius: 12px;
      background: #fff;
      box-shadow: 0 8px 32px rgba(0,0,0,0.18);
      display: none;
      flex-direction: column;
      z-index: 99999;
      overflow: hidden;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .cb-window.open { display: flex; }

    .cb-header {
      background: ${PRIMARY_COLOR};
      color: #fff;
      padding: 14px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    .cb-header-title { font-size: 15px; font-weight: 600; }
    .cb-close {
      background: none;
      border: none;
      color: #fff;
      cursor: pointer;
      font-size: 20px;
      line-height: 1;
      padding: 4px;
    }

    .cb-messages {
      flex: 1;
      overflow-y: auto;
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .cb-msg {
      max-width: 80%;
      padding: 10px 14px;
      border-radius: 12px;
      font-size: 14px;
      line-height: 1.45;
      word-wrap: break-word;
      text-align: left;
    }
    .cb-msg.user {
      align-self: flex-end;
      background: ${PRIMARY_COLOR};
      color: #fff;
      border-bottom-right-radius: 4px;
    }
    .cb-msg.bot {
      align-self: flex-start;
      background: #f1f3f5;
      color: #1a1a1a;
      border-bottom-left-radius: 4px;
    }

    .cb-typing {
      align-self: flex-start;
      padding: 10px 14px;
      background: #f1f3f5;
      border-radius: 12px;
      display: none;
      gap: 4px;
      align-items: center;
    }
    .cb-typing.visible { display: flex; }
    .cb-typing span {
      width: 7px;
      height: 7px;
      background: #999;
      border-radius: 50%;
      animation: cb-bounce 1.2s infinite;
    }
    .cb-typing span:nth-child(2) { animation-delay: 0.2s; }
    .cb-typing span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes cb-bounce {
      0%, 60%, 100% { transform: translateY(0); }
      30% { transform: translateY(-4px); }
    }

    .cb-input-area {
      display: flex;
      padding: 12px;
      border-top: 1px solid #e5e7eb;
      flex-shrink: 0;
      overflow: hidden;
    }
    .cb-input {
      flex: 1;
      min-width: 0;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 10px 12px;
      font-size: 14px;
      outline: none;
      resize: none;
      font-family: inherit;
    }
    .cb-input:focus { border-color: ${PRIMARY_COLOR}; }
    .cb-send {
      margin-left: 8px;
      background: ${PRIMARY_COLOR};
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 0 14px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
    }
    .cb-send:disabled { opacity: 0.5; cursor: not-allowed; }

    @media (max-width: 480px) {
      .cb-window {
        top: 0;
        bottom: 0;
        left: 0;
        right: 0;
        width: auto;
        height: auto;
        border-radius: 0;
      }
      .cb-input { font-size: 16px; }
      .cb-bubble { bottom: 16px; right: 16px; }
      .cb-label { display: none; }
    }
  `;

  const html = `
    <style>${styles}</style>
    <div class="cb-label">${TITLE}</div>
    <button class="cb-bubble" aria-label="Open chat">
      <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.2L4 17.2V4h16v12z"/></svg>
    </button>
    <div class="cb-window">
      <div class="cb-header">
        <span class="cb-header-title">${TITLE}</span>
        <button class="cb-close" aria-label="Close chat">&times;</button>
      </div>
      <div class="cb-messages">
        <div class="cb-typing"><span></span><span></span><span></span></div>
      </div>
      <div class="cb-input-area">
        <input class="cb-input" type="text" placeholder="Type a message..." />
        <button class="cb-send">Send</button>
      </div>
    </div>
  `;

  shadow.innerHTML = html;

  const bubble = shadow.querySelector(".cb-bubble");
  const label = shadow.querySelector(".cb-label");
  const window_ = shadow.querySelector(".cb-window");
  const closeBtn = shadow.querySelector(".cb-close");
  const messagesEl = shadow.querySelector(".cb-messages");
  const typingEl = shadow.querySelector(".cb-typing");
  const inputEl = shadow.querySelector(".cb-input");
  const sendBtn = shadow.querySelector(".cb-send");

  let history = [];

  bubble.addEventListener("click", function () {
    window_.classList.add("open");
    bubble.style.display = "none";
    label.style.display = "none";
    inputEl.focus();
  });

  label.addEventListener("click", function () {
    window_.classList.add("open");
    bubble.style.display = "none";
    label.style.display = "none";
    inputEl.focus();
  });

  closeBtn.addEventListener("click", function () {
    window_.classList.remove("open");
    bubble.style.display = "flex";
    label.style.display = "block";
  });

  function addMessage(text, role) {
    var msg = document.createElement("div");
    msg.className = "cb-msg " + role;
    msg.textContent = text;
    messagesEl.insertBefore(msg, typingEl);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    return msg;
  }

  function setLoading(on) {
    typingEl.classList.toggle("visible", on);
    sendBtn.disabled = on;
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  async function sendBlockingMessage(text) {
    setLoading(true);
    try {
      var payload = {
          message: text,
          history: history.slice(-MAX_HISTORY),
          tenant_id: TENANT_ID,
        };
      if (USER_TOKEN) payload.user_token = USER_TOKEN;
      var res = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      var data = await res.json();
      var reply = data.reply || "Sorry, something went wrong.";
      addMessage(reply, "bot");
      history.push({ role: "assistant", content: reply });
    } catch (e) {
      addMessage("Unable to reach the server. Please try again.", "bot");
    }
    setLoading(false);
  }

  async function sendStreamingMessage(text) {
    setLoading(true);
    var botMsg = addMessage("", "bot");
    var fullReply = "";

    try {
      var payload = {
          message: text,
          history: history.slice(-MAX_HISTORY),
          tenant_id: TENANT_ID,
        };
      if (USER_TOKEN) payload.user_token = USER_TOKEN;
      var res = await fetch(STREAM_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        botMsg.textContent = "Sorry, something went wrong.";
        setLoading(false);
        return;
      }

      var reader = res.body.getReader();
      var decoder = new TextDecoder();
      var buffer = "";

      while (true) {
        var result = await reader.read();
        if (result.done) break;

        buffer += decoder.decode(result.value, { stream: true });
        var lines = buffer.split("\n");
        buffer = lines.pop();

        for (var i = 0; i < lines.length; i++) {
          var line = lines[i].trim();
          if (!line.startsWith("data: ")) continue;

          var payload = line.slice(6);
          if (payload === "[DONE]") continue;

          try {
            var parsed = JSON.parse(payload);
            if (parsed.token) {
              fullReply += parsed.token;
              botMsg.textContent = fullReply;
              messagesEl.scrollTop = messagesEl.scrollHeight;
            }
          } catch (e) {
            // skip malformed JSON
          }
        }
      }

      if (!fullReply) {
        botMsg.textContent = "Sorry, I didn't get a response. Please try again.";
      }
      history.push({ role: "assistant", content: fullReply });
    } catch (e) {
      if (!fullReply) {
        botMsg.textContent = "Unable to reach the server. Please try again.";
      }
    }
    setLoading(false);
  }

  async function sendMessage() {
    var text = inputEl.value.trim();
    if (!text) return;

    inputEl.value = "";
    addMessage(text, "user");
    history.push({ role: "user", content: text });

    if (STREAM_URL) {
      await sendStreamingMessage(text);
    } else {
      await sendBlockingMessage(text);
    }
  }

  sendBtn.addEventListener("click", sendMessage);
  inputEl.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
})();
