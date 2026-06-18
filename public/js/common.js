/**
 * Common JavaScript Utilities
 * Shared functions for API calls, notifications, modals, etc.
 */

/**
 * Get CSRF token from meta tag
 */
export function getCsrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}

/**
 * Custom error class for authentication failures.
 * Callers (e.g. polling loops) can check `instanceof AuthError` to stop
 * retrying and avoid flooding the UI with error toasts.
 */
export class AuthError extends Error {
    constructor(message = 'Session expired') {
        super(message);
        this.name = 'AuthError';
    }
}

/**
 * API wrapper with CSRF token and error handling.
 *
 * Handles session expiry gracefully: if the server returns 401 the browser
 * is redirected to /login and an AuthError is thrown (no toast shown).
 * Non-JSON responses (e.g. HTML error pages) are caught and surfaced as a
 * readable error instead of a cryptic JSON-parse message.
 */
export async function api(url, method = 'GET', data = null) {
    const options = {
        method: method.toUpperCase(),
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken()
        },
        credentials: 'same-origin'
    };

    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, options);

        // Session expired — redirect to login without showing a toast
        if (response.status === 401) {
            window.location.href = '/login';
            throw new AuthError();
        }

        // Verify we actually received JSON before attempting to parse
        const contentType = response.headers.get('Content-Type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error('Request failed');
        }

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Request failed');
        }

        return result;
    } catch (error) {
        // Auth errors are handled above (redirect); don't show a toast
        if (error instanceof AuthError) {
            throw error;
        }
        console.error('API Error:', error);
        toast('error', error.message || 'An error occurred');
        throw error;
    }
}

/**
 * Show toast notification using the shared .toast-container / .toast CSS components.
 * Error toasts persist until dismissed; others auto-dismiss.
 */
export function toast(type, message, duration) {
    // Ensure the fixed container exists
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const icons = {
        success: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
        error:   '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>',
        warning: '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>',
        info:    '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>'
    };

    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.setAttribute('role', 'alert');
    el.innerHTML =
        `<svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">${icons[type] || icons.info}</svg>` +
        '<p class="font-medium flex-1 leading-5"></p>' +
        '<button type="button" class="toast-dismiss" aria-label="Dismiss">' +
            '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>' +
        '</button>';
    el.querySelector('p').textContent = message;

    container.appendChild(el);

    function dismiss() {
        if (el.classList.contains('toast-dismissing')) return;
        el.classList.add('toast-dismissing');
        el.addEventListener('animationend', () => {
            el.remove();
            if (container && !container.hasChildNodes()) container.remove();
        }, { once: true });
    }

    el.querySelector('.toast-dismiss').addEventListener('click', dismiss);

    // Default durations: errors stay until dismissed, others auto-dismiss
    const autoDismiss = duration !== undefined ? duration : (type === 'error' ? 0 : 4000);
    if (autoDismiss > 0) {
        setTimeout(dismiss, autoDismiss);
    }
}

/**
 * Confirmation dialog
 */
export function confirm(message) {
    return new Promise((resolve) => {
        const existing = document.querySelector('.confirm-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.className = 'confirm-modal fixed inset-0 z-50 flex items-center justify-center p-4';
        modal.innerHTML = `
                <div class="fixed inset-0 transition-opacity bg-gray-500/75" onclick="this.closest('.confirm-modal').remove()"></div>
                <div class="relative z-10 w-full max-w-lg bg-white rounded-lg text-left overflow-hidden shadow-xl p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Confirm Action</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 js-confirm-msg"></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" class="btn btn-primary btn-full confirm-yes sm:ml-3 sm:w-auto sm:text-sm">
                            Confirm
                        </button>
                        <button type="button" class="btn btn-secondary btn-full confirm-no mt-3 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
        `;
        modal.querySelector('.js-confirm-msg').textContent = message;

        document.body.appendChild(modal);

        modal.querySelector('.confirm-yes').onclick = () => {
            modal.remove();
            resolve(true);
        };

        modal.querySelector('.confirm-no').onclick = () => {
            modal.remove();
            resolve(false);
        };
    });
}

/**
 * Generic modal
 */
export function modal(contentHtml, options = {}) {
    const { title = 'Modal', size = 'md', onClose = null } = options;

    const sizeClasses = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-2xl',
        lg: 'sm:max-w-4xl',
        xl: 'sm:max-w-6xl'
    };

    const existing = document.querySelector('.custom-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.className = 'custom-modal fixed inset-0 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
            <div class="fixed inset-0 transition-opacity bg-gray-500/75 modal-backdrop"></div>
            <div class="relative z-10 w-full ${sizeClasses[size]} bg-white rounded-lg text-left overflow-hidden shadow-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">${title}</h3>
                    <button type="button" class="close-modal text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="modal-content">
                    ${contentHtml}
                </div>
            </div>
    `;

    document.body.appendChild(modal);

    const closeModal = () => {
        modal.remove();
        if (onClose) onClose();
    };

    modal.querySelector('.close-modal').onclick = closeModal;
    modal.querySelector('.modal-backdrop').onclick = closeModal;

    return modal;
}

/**
 * Close modal
 */
export function closeModal() {
    const modal = document.querySelector('.custom-modal');
    if (modal) modal.remove();
}

/**
 * Debounce function
 */
export function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Format date
 */
export function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Format date and time
 */
export function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
