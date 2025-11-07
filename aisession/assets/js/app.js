class AISessionApp {
    constructor() {
        this.historyElement = document.getElementById('chat-history');
        this.form = document.getElementById('composer-form');
        this.promptInput = document.getElementById('prompt');
        this.statusBadge = document.getElementById('status-badge');
        this.loadingIndicator = document.getElementById('loading-indicator');
        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            void this.submitMessage();
        });
        this.promptInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                void this.submitMessage();
            }
        });
        this.conversation = [];
        void this.bootstrap();
    }

    async bootstrap() {
        this.toggleComposer(false);
        try {
            const response = await fetch('api/session.php', { method: 'GET' });
            if (!response.ok) {
                throw new Error(`Failed to load conversation (${response.status})`);
            }
            const payload = await response.json();
            this.conversation = payload.messages ?? [];
            this.renderConversation();
            if (payload.chatgotConfigured) {
                this.statusBadge.textContent = 'Connected to Chatgot';
                this.statusBadge.classList.remove('bg-warning');
                this.statusBadge.classList.add('bg-success');
            }
        } catch (error) {
            console.error(error);
            this.pushSystemMessage('Unable to reach the session API. Offline mode is active.');
        } finally {
            this.toggleComposer(true);
        }
    }

    async submitMessage() {
        const content = this.promptInput.value.trim();
        if (!content) {
            return;
        }

        this.toggleComposer(false);
        this.appendMessage({
            role: 'user',
            content,
            timestamp: new Date().toISOString(),
            id: `user_${this.randomId()}`,
        });
        this.promptInput.value = '';
        this.scrollToBottom();
        this.setLoading(true);

        try {
            const response = await fetch('api/session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: content }),
            });
            if (!response.ok) {
                throw new Error(`Chat request failed (${response.status})`);
            }
            const payload = await response.json();
            this.conversation = payload.messages ?? this.conversation;
            this.renderConversation();
            this.scrollToBottom();
        } catch (error) {
            console.error(error);
            this.pushSystemMessage('Failed to submit your prompt. Please try again.');
        } finally {
            this.setLoading(false);
            this.toggleComposer(true);
            this.promptInput.focus();
        }
    }

    pushSystemMessage(content) {
        this.appendMessage({
            role: 'system',
            content,
            timestamp: new Date().toISOString(),
            id: `system_${this.randomId()}`,
        });
        this.renderConversation();
        this.scrollToBottom();
    }

    appendMessage(message) {
        this.conversation.push(message);
    }

    renderConversation() {
        this.historyElement.innerHTML = '';
        this.conversation.forEach((message) => {
            this.historyElement.appendChild(this.renderMessage(message));
        });
    }

    renderMessage(message) {
        const wrapper = document.createElement('div');
        wrapper.classList.add('chat-message', message.role ?? 'assistant');

        const meta = document.createElement('div');
        meta.classList.add('message-meta');
        const timestamp = message.timestamp ? new Date(message.timestamp) : new Date();
        meta.textContent = `${message.role?.toUpperCase() ?? 'ASSISTANT'} · ${timestamp.toLocaleString()}`;

        const body = document.createElement('div');
        body.classList.add('message-body');
        body.innerHTML = this.transformMarkdown(message.content ?? '');

        wrapper.appendChild(meta);
        wrapper.appendChild(body);
        return wrapper;
    }

    transformMarkdown(content) {
        const codeBlocks = [];
        let working = content.replace(/```([\s\S]*?)```/g, (match, block) => {
            let language = '';
            let code = block;
            const newlineIndex = block.indexOf('\n');
            if (newlineIndex !== -1) {
                const maybeLanguage = block.substring(0, newlineIndex).trim();
                if (/^[\w.+-]+$/.test(maybeLanguage)) {
                    language = maybeLanguage;
                    code = block.substring(newlineIndex + 1);
                }
            }
            const placeholder = `__CODE_BLOCK_${codeBlocks.length}__`;
            codeBlocks.push({ language, code });
            return placeholder;
        });

        const inlineCodes = [];
        working = working.replace(/`([^`]+)`/g, (match, snippet) => {
            const placeholder = `__INLINE_CODE_${inlineCodes.length}__`;
            inlineCodes.push(snippet);
            return placeholder;
        });

        const images = [];
        working = working.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, (match, alt, src) => {
            const placeholder = `__IMAGE_${images.length}__`;
            images.push({ alt, src });
            return placeholder;
        });

        const links = [];
        working = working.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (match, text, href) => {
            const placeholder = `__LINK_${links.length}__`;
            links.push({ text, href });
            return placeholder;
        });

        let html = this.escapeHtml(working);

        html = html.replace(/__CODE_BLOCK_(\d+)__/g, (match, index) => {
            const { language, code } = codeBlocks[Number(index)];
            return `<pre><code class="language-${this.escapeAttribute(language)}">${this.escapeHtml(code)}</code></pre>`;
        });

        html = html.replace(/__INLINE_CODE_(\d+)__/g, (match, index) => {
            const snippet = inlineCodes[Number(index)];
            return `<code>${this.escapeHtml(snippet)}</code>`;
        });

        html = html.replace(/__IMAGE_(\d+)__/g, (match, index) => {
            const { alt, src } = images[Number(index)];
            const safeSrc = this.escapeAttribute(src);
            const safeAlt = this.escapeHtml(alt);
            return `<figure><img src="${safeSrc}" alt="${safeAlt}"><figcaption class="small text-muted">${safeAlt}</figcaption></figure>`;
        });

        html = html.replace(/__LINK_(\d+)__/g, (match, index) => {
            const { text, href } = links[Number(index)];
            const safeHref = this.escapeAttribute(href);
            const safeText = this.escapeHtml(text);
            return `<a href="${safeHref}" target="_blank" rel="noopener">${safeText}</a>`;
        });

        html = html.replace(/\n\n/g, '<br><br>');
        html = html.replace(/\n/g, '<br>');

        return html;
    }

    escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    escapeAttribute(value) {
        return this.escapeHtml(value).replace(/"/g, '&quot;');
    }

    toggleComposer(enabled) {
        this.promptInput.disabled = !enabled;
        this.form.querySelector('button[type="submit"]').disabled = !enabled;
    }

    setLoading(isLoading) {
        if (isLoading) {
            this.loadingIndicator.classList.remove('d-none');
        } else {
            this.loadingIndicator.classList.add('d-none');
        }
    }

    scrollToBottom() {
        this.historyElement.scrollTo({ top: this.historyElement.scrollHeight, behavior: 'smooth' });
    }

    randomId() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }

        if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
            return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, (c) =>
                (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
            );
        }

        return `fallback_${Math.random().toString(16).slice(2)}`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.app = new AISessionApp();
});
