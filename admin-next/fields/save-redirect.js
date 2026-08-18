/**
 * Save-Redirect — custom web component field for flex-objects.
 *
 * Renders radio buttons for the "After Save" redirect behavior.
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
        // The blueprint declares all three, already translated server-side.
        if (this._field?.options && Array.isArray(this._field.options)) {
            return this._field.options.map(o => ({
                value: o.value,
                label: o.label,
            }));
        }
        // No options in the served blueprint (an older flex-objects, or a
        // directory that overrode the field). Offer all three regardless of the
        // current value: gating the list on it removed "create new" from the
        // group the moment a blueprint set any other default, which made
        // `default: edit` impossible to use (admin2#160).
        return [
            { value: 'create-new', label: 'Create New Item' },
            { value: 'edit', label: 'Edit Item' },
            { value: 'list', label: 'List Items' },
        ];
    }

    _render() {
        const options = this._getOptions();
        // Translated server-side alongside the options; falls back to the
        // English wording only if an older blueprint sends no label at all.
        const heading = this._field?.label ?? 'After Save...';

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
                <span class="sr-label-text">${heading}</span>
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
