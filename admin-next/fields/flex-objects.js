/**
 * flex-objects — custom web component field for the plugin-settings form.
 *
 * The field's value is an array of blueprint URLs (e.g.
 * "blueprints://flex-objects/grav-pages.yaml") identifying which flex
 * directories the plugin should expose. Renders one row per available
 * blueprint with a segmented Enabled/Disabled toggle, matching the
 * admin-classic UI semantics. Old saved values may still use legacy URLs
 * (pages.yaml vs grav-pages.yaml etc.) — the API includes the legacy alias
 * for each entry so we match either form.
 */

const TAG = window.__GRAV_FIELD_TAG;

class FlexObjectsField extends HTMLElement {
    constructor() {
        super();
        this._value = [];        // current array of enabled blueprint URLs
        this._field = null;      // blueprint field definition
        this._items = null;      // available blueprints fetched from API
        this._loading = false;
        this._error = null;
    }

    set field(v) { this._field = v; }
    get field() { return this._field; }

    set value(v) {
        const arr = Array.isArray(v) ? v.filter((x) => typeof x === 'string') : [];
        this._value = arr;
        if (this.isConnected && this._items) this._render();
    }
    get value() { return this._value; }

    connectedCallback() {
        this._render();
        this._fetchBlueprints();
    }

    // ─── API helpers ───────────────────────────────────────────────────────
    _apiUrl(path) {
        return (window.__GRAV_API_SERVER_URL || '') +
               (window.__GRAV_API_PREFIX || '/api/v1') + path;
    }

    _headers() {
        const h = { Accept: 'application/json' };
        const token = window.__GRAV_API_TOKEN;
        if (token) h['X-API-Token'] = token;
        return h;
    }

    async _fetchBlueprints() {
        this._loading = true;
        this._error = null;
        try {
            const resp = await fetch(this._apiUrl('/flex-objects/blueprints'), { headers: this._headers() });
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
            const json = await resp.json();
            this._items = Array.isArray(json.data) ? json.data : (Array.isArray(json) ? json : []);
        } catch (e) {
            this._error = e.message || String(e);
            this._items = [];
        } finally {
            this._loading = false;
            this._render();
        }
    }

    // True when either the canonical or the legacy URL of `item` is in `value`.
    _isEnabled(item) {
        if (this._value.includes(item.url)) return true;
        return item.legacy_url ? this._value.includes(item.legacy_url) : false;
    }

    _toggle(item, enabled) {
        // Drop both forms first to keep the array clean, then add canonical if enabled
        let next = this._value.filter((u) => u !== item.url && u !== item.legacy_url);
        if (enabled) next.push(item.url);
        this._value = next;
        this._render();
        this.dispatchEvent(new CustomEvent('change', { detail: next, bubbles: true }));
    }

    // ─── Rendering ─────────────────────────────────────────────────────────
    _render() {
        if (this._loading && !this._items) {
            this.innerHTML = `<div class="fxo-status">Loading directories…</div>${this._styles()}`;
            return;
        }
        if (this._error && !this._items?.length) {
            this.innerHTML = `<div class="fxo-status fxo-error">Failed to load directories: ${this._escape(this._error)}</div>${this._styles()}`;
            return;
        }
        if (!this._items?.length) {
            this.innerHTML = `<div class="fxo-status">No flex directories available.</div>${this._styles()}`;
            return;
        }

        const rows = this._items.map((item, i) => {
            const enabled = this._isEnabled(item);
            const id = `fxo-${this._uid}-${i}`;
            const desc = item.description ? this._escape(item.description) : '';
            return `
                <div class="fxo-row">
                    <div class="fxo-meta">
                        <div class="fxo-title" ${desc ? `title="${desc}"` : ''}>${this._escape(item.title || item.type)}</div>
                        <div class="fxo-url">${this._escape(item.url)}</div>
                    </div>
                    <div class="fxo-toggle" role="group" aria-label="${this._escape(item.title || item.type)}">
                        <button type="button" data-id="${id}" data-enabled="1" class="${enabled ? 'is-on' : ''}">Enabled</button>
                        <button type="button" data-id="${id}" data-enabled="0" class="${!enabled ? 'is-on' : ''}">Disabled</button>
                    </div>
                </div>
            `;
        }).join('');

        this.innerHTML = `<div class="fxo-list">${rows}</div>${this._styles()}`;

        this.querySelectorAll('.fxo-toggle button').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const wantEnabled = btn.dataset.enabled === '1';
                const idx = parseInt(id.split('-').pop(), 10);
                const item = this._items[idx];
                if (!item) return;
                if (this._isEnabled(item) === wantEnabled) return;
                this._toggle(item, wantEnabled);
            });
        });
    }

    _styles() {
        return `
            <style>
                .fxo-list {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }
                .fxo-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 16px;
                    padding: 10px 12px;
                    border: 1px solid var(--border, #e5e7eb);
                    border-radius: 8px;
                    background: var(--muted, transparent);
                }
                .fxo-meta { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
                .fxo-title { font-size: 14px; font-weight: 500; color: var(--foreground, #1f2937); }
                .fxo-url {
                    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                    font-size: 11px;
                    color: var(--muted-foreground, #6b7280);
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .fxo-toggle {
                    display: inline-flex;
                    border: 1px solid var(--border, #e5e7eb);
                    border-radius: 8px;
                    overflow: hidden;
                    flex-shrink: 0;
                    background: var(--background, transparent);
                }
                .fxo-toggle button {
                    appearance: none;
                    border: 0;
                    background: transparent;
                    padding: 6px 14px;
                    font-size: 12px;
                    color: var(--muted-foreground, #6b7280);
                    cursor: pointer;
                    font-family: inherit;
                }
                .fxo-toggle button + button { border-left: 1px solid var(--border, #e5e7eb); }
                .fxo-toggle button.is-on {
                    background: var(--primary, #3b82f6);
                    color: var(--primary-foreground, #fff);
                }
                .fxo-status {
                    padding: 12px;
                    color: var(--muted-foreground, #6b7280);
                    font-size: 13px;
                }
                .fxo-error { color: var(--destructive, #dc2626); }
            </style>
        `;
    }

    _escape(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    get _uid() {
        if (!this.__uid) this.__uid = Math.random().toString(36).slice(2, 8);
        return this.__uid;
    }
}

customElements.define(TAG, FlexObjectsField);
