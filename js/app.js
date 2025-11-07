const AppState = {
    user: null,
    heartbeatTimer: null,
    peerConnection: null,
    localStream: null,
    callTarget: null,
    callId: null,
    isCaller: false,
    eventSource: null,
    eventReconnectTimer: null,
    eventStreamConnected: false,
    pendingOffer: null,
    pendingCandidates: []
};

document.addEventListener('DOMContentLoaded', () => {
    bindAuthForms();
    const loggedIn = document.getElementById('app').dataset.loggedIn === 'true';
    if (loggedIn) {
        bootstrapMessenger();
    }
});

function bindAuthForms() {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const logoutBtn = document.getElementById('logout-btn');

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(loginForm);
            const payload = Object.fromEntries(formData.entries());
            const res = await fetch('api/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            showFormMessage('login', data);
            if (data.success) {
                AppState.user = data.user;
                enterMessenger();
            }
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(registerForm);
            const payload = Object.fromEntries(formData.entries());
            const res = await fetch('api/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            showFormMessage('register', data);
        });
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            await fetch('api/logout.php', { method: 'POST' });
            window.location.reload();
        });
    }
}

function showFormMessage(form, result) {
    const el = document.querySelector(`.form-message[data-for="${form}"]`);
    if (!el) return;
    if (result.success) {
        el.style.color = '#28a745';
        el.textContent = 'Success!';
    } else {
        el.style.color = '#c00';
        el.textContent = result.error || 'Request failed';
    }
}

async function bootstrapMessenger() {
    await fetchCurrentUser();
    if (!AppState.user) return;
    enterMessenger();
}

function enterMessenger() {
    document.getElementById('auth-section')?.classList.add('hidden');
    document.getElementById('messenger')?.classList.remove('hidden');
    const startBtn = document.getElementById('start-call-btn');
    if (startBtn && !startBtn.dataset.bound) {
        startBtn.dataset.bound = 'true';
        startBtn.addEventListener('click', () => {
            if (AppState.callTarget) {
                startCall(AppState.callTarget);
            }
        });
    }
    const hangupBtn = document.getElementById('hangup-btn');
    if (hangupBtn && !hangupBtn.dataset.bound) {
        hangupBtn.dataset.bound = 'true';
        hangupBtn.addEventListener('click', () => {
            sendHangup();
        });
    }
    const updateStatusBtn = document.getElementById('update-status-btn');
    if (updateStatusBtn && !updateStatusBtn.dataset.bound) {
        updateStatusBtn.dataset.bound = 'true';
        updateStatusBtn.addEventListener('click', updateStatusMessage);
    }
    const passwordForm = document.getElementById('password-form');
    if (passwordForm && !passwordForm.dataset.bound) {
        passwordForm.dataset.bound = 'true';
        passwordForm.addEventListener('submit', handlePasswordChange);
    }
    scheduleHeartbeat();
    bindIncomingCallActions();
    initializeEventStream();
    updateState();
}

async function fetchCurrentUser() {
    const res = await fetch('api/me.php');
    const data = await res.json();
    AppState.user = data.user;
}

async function updateState() {
    const res = await fetch('api/state.php');
    if (!res.ok) return;
    const state = await res.json();
    AppState.user = state.currentUser;
    renderState(state);
    renderOnlineUsers(state.users || []);
}

function renderState(state) {
    const pre = document.getElementById('state-json');
    if (pre) {
        pre.textContent = JSON.stringify(state, null, 2);
    }
    if (state.currentUser) {
        const statusInput = document.getElementById('status-input');
        if (statusInput && statusInput !== document.activeElement) {
            statusInput.value = state.currentUser.statusMessage || '';
        }
    }
}

function renderOnlineUsers(users) {
    const list = document.getElementById('online-list');
    if (!list || !AppState.user) return;
    list.innerHTML = '';
    users
        .filter((u) => u.username !== AppState.user.username)
        .sort((a, b) => a.username.localeCompare(b.username))
        .forEach((user) => {
            const li = document.createElement('li');
            const name = document.createElement('span');
            name.textContent = `${user.displayName || user.username} ${user.online ? '(online)' : '(offline)'}`;
            const btn = document.createElement('button');
            btn.textContent = 'Call';
            btn.disabled = !user.online;
            btn.addEventListener('click', () => {
                AppState.callTarget = user.username;
                document.getElementById('start-call-btn').disabled = false;
                document.querySelectorAll('#online-list button').forEach((b) => b.classList.remove('calling'));
                btn.classList.add('calling');
                addLog(`Selected ${user.username} for a call.`);
            });
            li.appendChild(name);
            li.appendChild(btn);
            list.appendChild(li);
        });
}

function scheduleHeartbeat() {
    heartbeat();
    if (AppState.heartbeatTimer) clearInterval(AppState.heartbeatTimer);
    AppState.heartbeatTimer = setInterval(heartbeat, 5000);
}

async function heartbeat() {
    await fetch('api/heartbeat.php', { method: 'POST' });
}

async function handleSignal(signal) {
    switch (signal.type) {
        case 'offer':
            await handleIncomingOffer(signal);
            break;
        case 'answer':
            await handleIncomingAnswer(signal);
            break;
        case 'candidate':
            await handleIncomingCandidate(signal);
            break;
        case 'hangup':
            addLog(`Call ended by ${signal.from}`);
            await endCall();
            break;
    }
}

async function ensureLocalStream() {
    if (AppState.localStream) return AppState.localStream;
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
        AppState.localStream = stream;
        const video = document.getElementById('local-video');
        if (video) {
            video.srcObject = stream;
        }
        return stream;
    } catch (err) {
        addLog('Unable to access media devices: ' + err.message);
        throw err;
    }
}

function createPeerConnection() {
    if (AppState.peerConnection) {
        return AppState.peerConnection;
    }
    const pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
    });
    pc.onicecandidate = (event) => {
        if (event.candidate) {
            sendSignal(AppState.callTarget, 'candidate', event.candidate, AppState.callId);
        }
    };
    pc.ontrack = (event) => {
        const remoteVideo = document.getElementById('remote-video');
        if (remoteVideo) {
            remoteVideo.srcObject = event.streams[0];
        }
    };
    AppState.peerConnection = pc;
    return pc;
}

async function startCall(target) {
    try {
        AppState.callId = `call-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        AppState.callTarget = target;
        AppState.isCaller = true;
        AppState.pendingOffer = null;
        AppState.pendingCandidates = [];
        const stream = await ensureLocalStream();
        const pc = createPeerConnection();
        stream.getTracks().forEach((track) => pc.addTrack(track, stream));
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await sendSignal(target, 'offer', offer, AppState.callId);
        addLog(`Calling ${target}...`);
        setCallButtons(true);
    } catch (err) {
        addLog('Failed to start call: ' + err.message);
    }
}

async function handleIncomingOffer(signal) {
    if (AppState.peerConnection && AppState.callId && AppState.callId !== signal.callId) {
        addLog(`Already in another call. Ignoring offer from ${signal.from}.`);
        return;
    }
    AppState.callTarget = signal.from;
    AppState.callId = signal.callId;
    AppState.isCaller = false;
    AppState.pendingOffer = signal;
    AppState.pendingCandidates = [];
    showIncomingCall(signal.from);
}

async function handleIncomingAnswer(signal) {
    if (!AppState.peerConnection) return;
    if (signal.callId !== AppState.callId) return;
    await AppState.peerConnection.setRemoteDescription(new RTCSessionDescription(signal.payload));
    addLog(`Connected with ${signal.from}`);
}

async function handleIncomingCandidate(signal) {
    if (AppState.callId && signal.callId !== AppState.callId) {
        return;
    }
    if (!AppState.pendingOffer && !AppState.peerConnection) {
        return;
    }
    if (!AppState.peerConnection) {
        AppState.pendingCandidates.push(signal);
        return;
    }
    try {
        await AppState.peerConnection.addIceCandidate(new RTCIceCandidate(signal.payload));
    } catch (err) {
        addLog('Error adding ICE candidate: ' + err.message);
    }
}

function bindIncomingCallActions() {
    const acceptBtn = document.getElementById('accept-call-btn');
    if (acceptBtn && !acceptBtn.dataset.bound) {
        acceptBtn.dataset.bound = 'true';
        acceptBtn.addEventListener('click', acceptIncomingCall);
    }
    const declineBtn = document.getElementById('decline-call-btn');
    if (declineBtn && !declineBtn.dataset.bound) {
        declineBtn.dataset.bound = 'true';
        declineBtn.addEventListener('click', declineIncomingCall);
    }
}

function showIncomingCall(caller) {
    const container = document.getElementById('call-notification');
    const nameEl = document.getElementById('caller-name');
    if (!container || !nameEl) return;
    nameEl.textContent = caller;
    container.classList.remove('hidden');
    addLog(`Incoming call from ${caller}`);
}

function hideIncomingCall() {
    const container = document.getElementById('call-notification');
    if (!container) return;
    container.classList.add('hidden');
}

async function acceptIncomingCall() {
    if (!AppState.pendingOffer) return;
    const offer = AppState.pendingOffer;
    hideIncomingCall();
    try {
        await completeIncomingOffer(offer);
        AppState.pendingOffer = null;
        addLog(`Answering call from ${offer.from}`);
    } catch (err) {
        addLog('Failed to answer call: ' + err.message);
        await endCall();
    }
}

async function declineIncomingCall() {
    if (!AppState.pendingOffer) return;
    const offer = AppState.pendingOffer;
    hideIncomingCall();
    AppState.pendingOffer = null;
    AppState.pendingCandidates = [];
    AppState.callId = null;
    AppState.callTarget = null;
    AppState.isCaller = false;
    await sendSignal(offer.from, 'hangup', {}, offer.callId);
    addLog(`Declined call from ${offer.from}`);
}

async function completeIncomingOffer(signal) {
    AppState.callTarget = signal.from;
    AppState.callId = signal.callId;
    AppState.isCaller = false;
    const stream = await ensureLocalStream();
    const pc = createPeerConnection();
    stream.getTracks().forEach((track) => pc.addTrack(track, stream));
    await pc.setRemoteDescription(new RTCSessionDescription(signal.payload));
    const answer = await pc.createAnswer();
    await pc.setLocalDescription(answer);
    await sendSignal(signal.from, 'answer', answer, AppState.callId);
    await applyPendingCandidates();
    setCallButtons(true);
}

async function applyPendingCandidates() {
    if (!AppState.peerConnection) return;
    const candidates = [...AppState.pendingCandidates];
    AppState.pendingCandidates = [];
    for (const candidateSignal of candidates) {
        try {
            await AppState.peerConnection.addIceCandidate(new RTCIceCandidate(candidateSignal.payload));
        } catch (err) {
            addLog('Error adding buffered ICE candidate: ' + err.message);
        }
    }
}

function initializeEventStream() {
    if (AppState.eventSource) {
        return;
    }
    const source = new EventSource('api/events.php');
    AppState.eventSource = source;

    source.addEventListener('state', (event) => {
        try {
            const state = JSON.parse(event.data);
            AppState.user = state.currentUser;
            renderState(state);
            renderOnlineUsers(state.users || []);
        } catch (err) {
            console.error('Failed to parse state event', err);
        }
    });

    source.addEventListener('signal', (event) => {
        try {
            const signal = JSON.parse(event.data);
            handleSignal(signal);
        } catch (err) {
            console.error('Failed to parse signal event', err);
        }
    });

    source.addEventListener('ping', () => {});

    source.onopen = () => {
        if (!AppState.eventStreamConnected) {
            addLog('Connected to live updates.');
        }
        AppState.eventStreamConnected = true;
    };

    source.onerror = () => {
        if (AppState.eventSource) {
            AppState.eventSource.close();
            AppState.eventSource = null;
        }
        if (AppState.eventStreamConnected) {
            AppState.eventStreamConnected = false;
            addLog('Connection to live updates lost. Reconnecting...');
        }
        scheduleEventStreamReconnect();
    };
}

function scheduleEventStreamReconnect() {
    if (AppState.eventReconnectTimer) return;
    AppState.eventReconnectTimer = setTimeout(() => {
        AppState.eventReconnectTimer = null;
        initializeEventStream();
    }, 3000);
}

async function sendSignal(target, type, payload, callId) {
    if (!target) return;
    await fetch('api/signals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send', to: target, type, payload, callId })
    });
}

async function sendHangup() {
    if (!AppState.callTarget || !AppState.callId) return;
    await sendSignal(AppState.callTarget, 'hangup', {}, AppState.callId);
    addLog('Ending call...');
    await endCall();
}

async function endCall() {
    if (AppState.peerConnection) {
        AppState.peerConnection.onicecandidate = null;
        AppState.peerConnection.ontrack = null;
        AppState.peerConnection.close();
        AppState.peerConnection = null;
    }
    setCallButtons(false);
    AppState.callId = null;
    AppState.callTarget = null;
    AppState.isCaller = false;
    AppState.pendingOffer = null;
    AppState.pendingCandidates = [];
    document.getElementById('remote-video').srcObject = null;
    document.getElementById('start-call-btn').disabled = true;
    document.querySelectorAll('#online-list button').forEach((b) => b.classList.remove('calling'));
    hideIncomingCall();
}

window.addEventListener('beforeunload', () => {
    if (AppState.eventSource) {
        AppState.eventSource.close();
    }
});

function setCallButtons(active) {
    document.getElementById('start-call-btn').disabled = active;
    document.getElementById('hangup-btn').disabled = !active;
}

async function updateStatusMessage() {
    const input = document.getElementById('status-input');
    if (!input) return;
    const status = input.value;
    const res = await fetch('api/update_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status })
    });
    const data = await res.json();
    const statusResult = document.getElementById('status-result');
    if (data.success) {
        statusResult.textContent = 'Status updated!';
        statusResult.style.color = '#28a745';
        addLog('Status updated to: ' + status);
    } else {
        statusResult.textContent = data.error || 'Failed to update status';
        statusResult.style.color = '#c00';
    }
    setTimeout(() => (statusResult.textContent = ''), 3000);
}

async function handlePasswordChange(event) {
    event.preventDefault();
    const password = document.getElementById('new-password').value;
    const confirm = document.getElementById('confirm-password').value;
    const resultEl = document.getElementById('password-result');
    if (password !== confirm) {
        resultEl.textContent = 'Passwords do not match';
        resultEl.style.color = '#c00';
        return;
    }
    const res = await fetch('api/change_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ newPassword: password })
    });
    const data = await res.json();
    if (data.success) {
        resultEl.textContent = 'Password updated!';
        resultEl.style.color = '#28a745';
        document.getElementById('password-form').reset();
        addLog('Password changed successfully');
        updateState();
    } else {
        resultEl.textContent = data.error || 'Failed to update password';
        resultEl.style.color = '#c00';
    }
    setTimeout(() => (resultEl.textContent = ''), 3000);
}

function addLog(message) {
    const list = document.getElementById('call-log-list');
    if (!list) return;
    const li = document.createElement('li');
    li.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
    list.prepend(li);
}
