/**
 * Save-Redirect — custom web component field for flex-objects.
 *
 * Renders radio buttons for "After Save..." redirect behavior.
 * The `field` property contains the blueprint definition which
 * may include an `options` array. If not, defaults are provided.
 *
 * Dispatches `change` events with the selected value.
 */

const TAG = window.__GRAV_FIELD_TAG;

class SaveRedirectField extends HTMLElement {
    constructor() {
        super();
        this._value = 'edit';
        this._field = null;
    }

    set field(v) { this._field = v; }
    get field() { return this._field; }

    set value(v) {
        const newVal = v ?? 'edit';
        if (this._value !== newVal) {
            this._value = newVal;
            if (this.isConnected) {
                this._syncChecked();
            }
        }
    }
    get value() { return this._value; }

    connectedCallback() {
        this._render();
        this._syncChecked();
    }

    _getOptions() {
        // Check if blueprint provides explicit options
        if (this._field?.options && Array.isArray(this._field.options)) {
            return this._field.options.map(o => ({
                value: o.value,
                label: o.label,
            }));
        }
        // Show "Create New Item" only when the current value is create-new
        // (i.e. when creating a new item — the blueprint default is create-new)
        const isNewContext = this._value === 'create-new';
        const options = [];
        if (isNewContext) {
            options.push({ value: 'create-new', label: 'Create New Item' });
        }
        options.push({ value: 'edit', label: 'Edit Item' });
        options.push({ value: 'list', label: 'List Items' });
        return options;
    }

    _render() {
        const options = this._getOptions();

        this.innerHTML = `
            <style>
                .sr-container {
                    display: flex;
                    align-items: center;
                    gap: 16px;
                    font-family: inherit;
                }
                .sr-label-text {
                    font-size: 13px;
                    color: var(--muted-foreground, #6b7280);
                }
                .sr-option {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    font-size: 13px;
                    color: var(--foreground, #1f2937);
                    cursor: pointer;
                }
                .sr-option input {
                    accent-color: var(--primary, #3b82f6);
                    cursor: pointer;
                }
            </style>
            <div class="sr-container">
                <span class="sr-label-text">After Save...</span>
                ${options.map(opt => `
                    <label class="sr-option">
                        <input type="radio" name="sr-${this._uid}" value="${opt.value}" />
                        ${opt.label}
                    </label>
                `).join('')}
            </div>
        `;

        // Bind change handlers
        this.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                const newVal = e.target.value;
                this._value = newVal;
                this.dispatchEvent(new CustomEvent('change', {
                    detail: newVal,
                    bubbles: true,
                }));
            });
        });
    }

    _syncChecked() {
        const radios = this.querySelectorAll('input[type="radio"]');
        radios.forEach(radio => {
            radio.checked = radio.value === this._value;
        });
    }

    get _uid() {
        if (!this.__uid) {
            this.__uid = Math.random().toString(36).slice(2, 8);
        }
        return this.__uid;
    }
}

customElements.define(TAG, SaveRedirectField);
