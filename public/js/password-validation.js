/**
 * Password Validation Module
 *
 * Shared client-side password strength checking, match validation,
 * and visibility toggling used across registration, reset, settings,
 * and admin user pages.
 */

/**
 * Toggle password field visibility and swap the eye/eye-off icons.
 *
 * @param {string}      inputId  ID of the password input
 * @param {HTMLElement}  btn      The toggle button element
 * @param {{ show?: string, hide?: string }} [labels] Accessible aria-label strings
 */
export function togglePasswordVisibility(inputId, btn, labels = {}) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.querySelector('.icon-eye').classList.toggle('hidden', isHidden);
    btn.querySelector('.icon-eye-off').classList.toggle('hidden', !isHidden);
    btn.setAttribute('aria-label', isHidden
        ? (labels.hide || 'Hide password')
        : (labels.show || 'Show password')
    );
}

/**
 * Initialize live password-rule indicators and match checking.
 *
 * Wires up `input` listeners on both fields so the complexity guide and
 * match error update in real time.
 *
 * @param {Object}  config
 * @param {string}  config.passwordId                 ID of the password input
 * @param {string}  config.confirmId                  ID of the confirm-password input
 * @param {string}  [config.prefix='pw']              ID prefix for rule elements (pw-length, pw-upper, …)
 * @param {string}  [config.matchErrorId='pw-match-error'] ID of the inline match-error element
 * @returns {{ updateRules: Function, checkMatch: Function, rules: Object } | null}
 */
export function initPasswordValidation({ passwordId, confirmId, prefix = 'pw', matchErrorId = 'pw-match-error' }) {
    const pwInput   = document.getElementById(passwordId);
    const pwConfirm = document.getElementById(confirmId);
    if (!pwInput || !pwConfirm) return null;

    const rules = {
        length: { el: document.getElementById(`${prefix}-length`), test: v => v.length >= 8 },
        upper:  { el: document.getElementById(`${prefix}-upper`),  test: v => /[A-Z]/.test(v) },
        lower:  { el: document.getElementById(`${prefix}-lower`),  test: v => /[a-z]/.test(v) },
        digit:  { el: document.getElementById(`${prefix}-digit`),  test: v => /[0-9]/.test(v) },
    };

    function updateRules() {
        const val = pwInput.value;
        for (const rule of Object.values(rules)) {
            if (!rule.el) continue;
            const pass  = rule.test(val);
            const dot   = rule.el.querySelector('.pw-dot');
            const check = rule.el.querySelector('.pw-check');
            const label = rule.el.querySelector('span:last-child');
            dot.classList.toggle('hidden', pass);
            check.classList.toggle('hidden', !pass);
            label.classList.remove('text-red-500');
            label.classList.toggle('text-gray-500', !pass);
            label.classList.toggle('text-green-600', pass);
            dot.classList.remove('bg-red-400');
            dot.classList.add('bg-gray-300');
        }
    }

    function checkMatch() {
        const errEl = document.getElementById(matchErrorId);
        if (!errEl) return;
        if (pwConfirm.value && pwConfirm.value !== pwInput.value) {
            errEl.classList.remove('hidden');
        } else {
            errEl.classList.add('hidden');
        }
    }

    pwInput.addEventListener('input', () => { updateRules(); checkMatch(); });
    pwConfirm.addEventListener('input', checkMatch);

    return { updateRules, checkMatch, rules };
}

/**
 * Validate password strength on form submit.
 *
 * Highlights unmet rules in red and shows the error banner.
 *
 * @param {Object} rules         The `rules` object returned by initPasswordValidation
 * @param {string} password      Current password value
 * @param {string} errorBannerId ID of the alert/banner element
 * @param {string} message       Error message to display
 * @returns {boolean} true if all rules pass
 */
export function validatePasswordStrength(rules, password, errorBannerId, message) {
    const allPass = Object.values(rules).every(r => r.test(password));
    if (allPass) return true;

    for (const rule of Object.values(rules)) {
        if (!rule.test(password) && rule.el) {
            const dot = rule.el.querySelector('.pw-dot');
            dot.classList.remove('bg-gray-300');
            dot.classList.add('bg-red-400');
            rule.el.querySelector('span:last-child').classList.add('text-red-500');
            rule.el.querySelector('span:last-child').classList.remove('text-gray-500');
        }
    }

    const banner = document.getElementById(errorBannerId);
    if (banner) {
        banner.textContent = message;
        banner.classList.remove('hidden');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    return false;
}

/**
 * Validate that password and confirmation match on form submit.
 *
 * Shows both the inline match-error text and the error banner.
 *
 * @param {string} password      Password value
 * @param {string} confirm       Confirm-password value
 * @param {string} matchErrorId  ID of the inline match-error element
 * @param {string} errorBannerId ID of the alert/banner element
 * @param {string} message       Error message to display
 * @returns {boolean} true if passwords match
 */
export function validatePasswordMatch(password, confirm, matchErrorId, errorBannerId, message) {
    if (password === confirm) return true;

    const matchErr = document.getElementById(matchErrorId);
    if (matchErr) matchErr.classList.remove('hidden');

    const banner = document.getElementById(errorBannerId);
    if (banner) {
        banner.textContent = message;
        banner.classList.remove('hidden');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    return false;
}
