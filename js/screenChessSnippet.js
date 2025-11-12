(function () {
  const OVERLAY_ID = "screen-chess-snippet-overlay";
  const STYLE_ID = "screen-chess-snippet-style";
  const DEFAULT_CONFIG_PATH = "/config/screen-chess-config.json";

  let mediaStream = null;
  let videoEl = null;
  let canvasEl = null;
  let ctx = null;
  let intervalHandle = null;
  let isAnalyzing = false;
  let config = null;
  let lastFen = null;
  let chessModule = null;
  let chessConstructor = null;

  const state = {
    pendingFrames: 0,
    analysesInFlight: 0,
    lastMove: null,
    lastConfidence: null,
    totalFrames: 0,
    lastError: null
  };

  function ensureOverlay() {
    if (document.getElementById(OVERLAY_ID)) {
      return document.getElementById(OVERLAY_ID);
    }

    const style = document.createElement("style");
    style.id = STYLE_ID;
    style.textContent = `
      #${OVERLAY_ID} {
        position: fixed;
        top: 1rem;
        right: 1rem;
        width: 320px;
        max-height: 90vh;
        z-index: 2147483647;
        background: rgba(15, 23, 42, 0.9);
        color: #f8fafc;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        border-radius: 12px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.45);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        backdrop-filter: blur(8px);
      }

      #${OVERLAY_ID} header {
        padding: 0.75rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(30, 41, 59, 0.85);
      }

      #${OVERLAY_ID} h1 {
        font-size: 1rem;
        margin: 0;
      }

      #${OVERLAY_ID} button {
        border: none;
        border-radius: 999px;
        padding: 0.4rem 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease, opacity 0.2s ease;
      }

      #${OVERLAY_ID} button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }

      #${OVERLAY_ID} button.primary {
        background: #38bdf8;
        color: #0f172a;
      }

      #${OVERLAY_ID} button.secondary {
        background: rgba(148, 163, 184, 0.25);
        color: #e2e8f0;
      }

      #${OVERLAY_ID} main {
        padding: 1rem;
        overflow-y: auto;
        display: grid;
        gap: 0.5rem;
      }

      #${OVERLAY_ID} .status {
        font-size: 0.85rem;
        line-height: 1.4;
        white-space: pre-wrap;
        word-break: break-word;
      }

      #${OVERLAY_ID} .status strong {
        color: #facc15;
      }

      #${OVERLAY_ID} footer {
        padding: 0.75rem 1rem;
        background: rgba(30, 41, 59, 0.65);
        font-size: 0.75rem;
      }

      #${OVERLAY_ID} code {
        font-family: "Fira Code", "SFMono-Regular", ui-monospace, monospace;
        font-size: 0.8rem;
        background: rgba(148, 163, 184, 0.2);
        padding: 0.15rem 0.3rem;
        border-radius: 6px;
      }

      #${OVERLAY_ID} .log {
        max-height: 160px;
        overflow-y: auto;
        font-size: 0.75rem;
        background: rgba(15, 23, 42, 0.6);
        border-radius: 8px;
        padding: 0.5rem;
      }
    `;
    document.head.appendChild(style);

    const overlay = document.createElement("section");
    overlay.id = OVERLAY_ID;
    overlay.innerHTML = `
      <header>
        <h1>Screen Chess Assistant</h1>
        <div>
          <button class="secondary" data-action="load-config">Load Config</button>
          <button class="primary" data-action="toggle">Start</button>
        </div>
      </header>
      <main>
        <div class="status" data-role="status">Idle. Load the configuration and press Start.</div>
        <div class="log" data-role="log"></div>
      </main>
      <footer>
        Capturing every <code data-role="interval">—</code> ms. Analyses in flight: <span data-role="in-flight">0</span>.
      </footer>
    `;

    document.body.appendChild(overlay);
    return overlay;
  }

  function updateStatus(text) {
    const overlay = ensureOverlay();
    const statusEl = overlay.querySelector('[data-role="status"]');
    if (statusEl) {
      statusEl.textContent = text;
    }
  }

  function updateLog(message) {
    const overlay = ensureOverlay();
    const logEl = overlay.querySelector('[data-role="log"]');
    if (logEl) {
      const time = new Date().toLocaleTimeString();
      const entry = document.createElement("div");
      entry.textContent = `[${time}] ${message}`;
      logEl.appendChild(entry);
      logEl.scrollTop = logEl.scrollHeight;
    }
  }

  function updateFooter() {
    const overlay = ensureOverlay();
    const intervalEl = overlay.querySelector('[data-role="interval"]');
    const inFlightEl = overlay.querySelector('[data-role="in-flight"]');
    if (intervalEl && config) {
      intervalEl.textContent = String(config.pollIntervalMs || 1000);
    }
    if (inFlightEl) {
      inFlightEl.textContent = `${state.analysesInFlight}/${config?.maxConcurrentAnalyses || 1}`;
    }
  }

  async function loadConfig(path) {
    const target = path || DEFAULT_CONFIG_PATH;
    updateStatus(`Loading configuration from ${target} …`);
    try {
      const response = await fetch(target, { cache: "no-store" });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      config = await response.json();
      updateStatus("Configuration loaded. Ready to capture.");
      updateLog(`Configuration loaded from ${target}`);
      updateFooter();
    } catch (error) {
      updateStatus(`Failed to load configuration: ${error.message}`);
      updateLog(`Configuration error: ${error.message}`);
      throw error;
    }
  }

  async function ensureChessModule() {
    if (chessModule) {
      return;
    }
    updateLog("Loading chess.js module …");
    chessModule = await import("https://cdn.jsdelivr.net/npm/chess.js@1.0.0/+esm");
    chessConstructor = chessModule.Chess;
    updateLog("chess.js module loaded.");
  }

  function stopCapture() {
    if (intervalHandle) {
      clearInterval(intervalHandle);
      intervalHandle = null;
    }
    if (mediaStream) {
      mediaStream.getTracks().forEach((track) => track.stop());
      mediaStream = null;
    }
    if (videoEl) {
      videoEl.srcObject = null;
      videoEl.remove();
      videoEl = null;
    }
    if (canvasEl) {
      canvasEl.remove();
      canvasEl = null;
      ctx = null;
    }
    isAnalyzing = false;
    state.pendingFrames = 0;
    state.analysesInFlight = 0;
    updateFooter();
    updateStatus("Capture stopped. Press Start to resume.");
    updateLog("Capture stopped.");
  }

  async function startCapture() {
    if (!config) {
      updateStatus("Load configuration before starting.");
      return;
    }
    if (isAnalyzing) {
      updateStatus("Already capturing.");
      return;
    }

    try {
      await ensureChessModule();
      mediaStream = await navigator.mediaDevices.getDisplayMedia({
        video: { frameRate: 1 },
        audio: false
      });
      videoEl = document.createElement("video");
      videoEl.style.position = "fixed";
      videoEl.style.top = "-9999px";
      videoEl.style.left = "-9999px";
      videoEl.style.width = "1px";
      videoEl.style.height = "1px";
      videoEl.autoplay = true;
      videoEl.srcObject = mediaStream;
      document.body.appendChild(videoEl);

      await videoEl.play();

      canvasEl = document.createElement("canvas");
      canvasEl.width = videoEl.videoWidth || 1280;
      canvasEl.height = videoEl.videoHeight || 720;
      canvasEl.style.position = "fixed";
      canvasEl.style.top = "-9999px";
      canvasEl.style.left = "-9999px";
      document.body.appendChild(canvasEl);
      ctx = canvasEl.getContext("2d");

      const interval = config.pollIntervalMs || 1000;
      intervalHandle = setInterval(captureFrame, interval);
      isAnalyzing = true;
      updateFooter();
      updateStatus("Screen capture running. Waiting for frames …");
      updateLog("Capture started.");
    } catch (error) {
      updateStatus(`Failed to start capture: ${error.message}`);
      updateLog(`Capture error: ${error.message}`);
      stopCapture();
    }
  }

  function captureFrame() {
    if (!isAnalyzing || !ctx || !videoEl) {
      return;
    }
    if (state.analysesInFlight >= (config.maxConcurrentAnalyses || 1)) {
      updateLog("Skipping frame; analysis in flight.");
      return;
    }

    ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
    state.totalFrames += 1;
    const dataUrl = canvasEl.toDataURL("image/png");
    analyzeFrame(dataUrl).catch((error) => {
      state.lastError = error;
      updateLog(`Analysis error: ${error.message}`);
    });
  }

  async function analyzeFrame(dataUrl) {
    state.analysesInFlight += 1;
    updateFooter();
    try {
      const boardInfo = await recognizeBoard(dataUrl);
      if (!boardInfo || !boardInfo.fen) {
        updateLog("Board not recognized in this frame.");
        return;
      }

      state.lastConfidence = boardInfo.confidence ?? null;

      if (boardInfo.fen === lastFen) {
        updateLog("Board unchanged; skipping suggestion.");
        return;
      }

      const move = await suggestMove(boardInfo.fen);
      if (move) {
        state.lastMove = move;
        const moveDiff = await detectMove(lastFen, boardInfo.fen);
        lastFen = boardInfo.fen;
        updateStatus(`Best move: ${move.bestMove || "?"}\nReason: ${move.reasoning || "—"}`);
        updateLog(`Detected move: ${moveDiff || "(none)"}; Suggested: ${move.bestMove}`);
      }
    } finally {
      state.analysesInFlight -= 1;
      updateFooter();
    }
  }

  async function recognizeBoard(imageDataUrl) {
    const prompt = config.boardRecognitionPrompt || "Return JSON with fen and confidence.";
    const response = await callChatCompletion(
      config.visionModel || config.analysisModel || config.model,
      [
        {
          role: "system",
          content: "You convert chessboard images into exact FEN notation. Respond with strict JSON."
        },
        {
          role: "user",
          content: [
            { type: "text", text: prompt },
            { type: "image_url", image_url: { url: imageDataUrl } }
          ]
        }
      ]
    );

    try {
      const json = JSON.parse(response);
      return json;
    } catch (error) {
      updateLog(`Failed to parse board JSON: ${error.message}`);
      return null;
    }
  }

  async function suggestMove(fen) {
    const prompt = config.moveSuggestionPrompt || "Return JSON with bestMove and reasoning.";
    const response = await callChatCompletion(
      config.analysisModel || config.visionModel || config.model,
      [
        {
          role: "system",
          content: "You are a chess analyst who returns JSON responses."
        },
        {
          role: "user",
          content: `${prompt}\nFEN: ${fen}`
        }
      ]
    );

    try {
      return JSON.parse(response);
    } catch (error) {
      updateLog(`Failed to parse suggestion JSON: ${error.message}`);
      return null;
    }
  }

  async function detectMove(previousFen, currentFen) {
    if (!previousFen) {
      return null;
    }
    try {
      const Chess = chessConstructor;
      if (!Chess) {
        return null;
      }
      const prev = new Chess(previousFen);
      const curr = new Chess(currentFen);
      const legal = prev.moves({ verbose: true });
      for (const move of legal) {
        prev.move(move);
        if (stripFen(prev.fen()) === stripFen(currentFen)) {
          return move.san || `${move.from}${move.to}${move.promotion || ""}`;
        }
        prev.undo();
      }
    } catch (error) {
      updateLog(`Move detection failed: ${error.message}`);
    }
    return null;
  }

  function stripFen(fen) {
    return fen.split(" ").slice(0, 4).join(" ");
  }

  async function callChatCompletion(model, messages) {
    const apiKey = config?.openAiApiKey || window.__SCREEN_CHESS_CONFIG__?.openAiApiKey;
    if (!apiKey) {
      throw new Error("Missing OpenAI API key in configuration.");
    }

    const containsImage = messages.some((message) =>
      Array.isArray(message.content) &&
      message.content.some((part) => part?.type === "image_url")
    );

    const body = {
      model,
      messages,
      temperature: 0.2
    };

    if (!containsImage) {
      body.response_format = { type: "json_object" };
    }

    const response = await fetch("https://api.openai.com/v1/chat/completions", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${apiKey}`
      },
      body: JSON.stringify(body)
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`OpenAI API error: ${response.status} ${errorText}`);
    }

    const data = await response.json();
    return data?.choices?.[0]?.message?.content?.trim();
  }

  function attachEventHandlers(overlay) {
    overlay.addEventListener("click", async (event) => {
      const action = event.target?.dataset?.action;
      if (!action) {
        return;
      }
      if (action === "toggle") {
        if (isAnalyzing) {
          stopCapture();
          event.target.textContent = "Start";
          event.target.classList.add("primary");
        } else {
          try {
            await startCapture();
            event.target.textContent = "Stop";
            event.target.classList.remove("primary");
          } catch (error) {
            updateStatus(`Unable to start: ${error.message}`);
          }
        }
      }
      if (action === "load-config") {
        const manualPath = prompt("Enter config URL", DEFAULT_CONFIG_PATH) || DEFAULT_CONFIG_PATH;
        try {
          await loadConfig(manualPath);
        } catch (error) {
          // Already handled inside loadConfig
        }
      }
    });
  }

  async function bootstrap() {
    const overlay = ensureOverlay();
    attachEventHandlers(overlay);
    updateStatus("Ready. Load configuration to begin.");
    updateLog("Snippet initialized.");

    if (window.__SCREEN_CHESS_CONFIG__) {
      config = window.__SCREEN_CHESS_CONFIG__;
      updateStatus("Configuration loaded from window.__SCREEN_CHESS_CONFIG__");
      updateFooter();
    } else {
      try {
        await loadConfig(DEFAULT_CONFIG_PATH);
      } catch (error) {
        updateStatus("Click ‘Load Config’ to retry with a custom URL.");
      }
    }
  }

  bootstrap().catch((error) => {
    console.error("Screen Chess Assistant failed to initialize", error);
    updateStatus(`Initialization error: ${error.message}`);
  });
})();
