<?php
// Resolve the correct API base path and detect admin context.
$_bell_path    = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$_bell_admin   = str_starts_with($_bell_path, '/admin') || str_starts_with($_bell_path, '/api/admin');
$_bell_api_base = $_bell_admin ? '/api/admin/notifications' : '/api/notifications';
?>
<!-- Notification Bell -->
<div class="relative" id="notifications-dropdown">
    <button type="button" class="relative p-2 text-gray-400 hover:text-gray-500 focus:outline-none" id="notifications-button" aria-label="View notifications">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        <!-- Unread dot -->
        <span id="notifications-badge" class="hidden absolute top-1.5 end-1.5 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white"></span>
    </button>
    
    <!-- Dropdown -->
    <div class="hidden absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black/5" id="notifications-menu">
        <div class="py-2">
            <!-- Header -->
            <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900"><?= $_bell_admin ? 'Notifications' : t('notifications.title') ?></h3>
                <div class="flex items-center gap-3">
                    <button type="button" id="mark-all-read-btn" class="text-xs text-primary-600 hover:text-primary-800 font-medium">
                        <?= $_bell_admin ? 'Mark all read' : t('notifications.mark_all_read') ?>
                    </button>
                    <button type="button" id="clear-all-btn" class="text-xs text-red-600 hover:text-red-800 font-medium">
                        <?= $_bell_admin ? 'Clear all' : t('notifications.clear_all') ?>
                    </button>
                </div>
            </div>
            
            <!-- Notifications list -->
            <div id="notifications-list" class="max-h-96 overflow-y-auto">
                <!-- Loading state -->
                <div class="px-4 py-6 text-center text-sm text-gray-500">
                    <?= $_bell_admin ? 'Loading…' : t('common.loading') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="module">
import { api, toast, AuthError } from '<?= asset('/js/common.js') ?>';

const API_BASE = <?= json_encode($_bell_api_base) ?>;

let notificationsOpen = false;
let pollInterval = null;

const button = document.getElementById('notifications-button');
const menu = document.getElementById('notifications-menu');
const badge = document.getElementById('notifications-badge');
const list = document.getElementById('notifications-list');
const markAllBtn = document.getElementById('mark-all-read-btn');
const clearAllBtn = document.getElementById('clear-all-btn');

// Toggle dropdown
button?.addEventListener('click', (e) => {
    e.stopPropagation();
    notificationsOpen = !notificationsOpen;
    
    if (notificationsOpen) {
        menu?.classList.remove('hidden');
        loadNotifications();
    } else {
        menu?.classList.add('hidden');
    }
});

// Close on outside click
document.addEventListener('click', () => {
    if (notificationsOpen) {
        menu?.classList.add('hidden');
        notificationsOpen = false;
    }
});

// Mark all as read
markAllBtn?.addEventListener('click', async () => {
    try {
        await api(API_BASE + '/read-all', 'POST');
        loadNotifications();
        updateBadge();
    } catch (error) {
        console.error('Failed to mark all as read:', error);
    }
});

// Clear all notifications
clearAllBtn?.addEventListener('click', async () => {
    const confirmMsg = <?= json_encode($_bell_admin ? 'Clear all notifications?' : t('notifications.clear_confirm')) ?>;
    if (!confirm(confirmMsg)) {
        return;
    }
    
    try {
        await api(API_BASE + '/clear-all', 'POST');
        loadNotifications();
        updateBadge();
        const successMsg = <?= json_encode($_bell_admin ? 'Notifications cleared' : t('notifications.cleared_success')) ?>;
        toast('success', successMsg);
    } catch (error) {
        console.error('Failed to clear all notifications:', error);
        const errorMsg = <?= json_encode($_bell_admin ? 'Failed to clear notifications' : t('notifications.clear_failed')) ?>;
        toast('error', errorMsg);
    }
});

// Load notifications
async function loadNotifications() {
    try {
        const data = await api(API_BASE + '?limit=10');
        renderNotifications(data.notifications || []);
    } catch (error) {
        console.error('Failed to load notifications:', error);
        const errorMsg = <?= json_encode($_bell_admin ? 'Failed to load notifications' : t('notifications.failed_to_load')) ?>;
        list.innerHTML = `<div class="px-4 py-6 text-center text-sm text-red-500">${errorMsg}</div>`;
    }
}

// Render notifications
function renderNotifications(notifications) {
    const noNotifMsg = <?= json_encode($_bell_admin ? 'No notifications' : t('notifications.no_notifications')) ?>;
    if (notifications.length === 0) {
        list.innerHTML = `<div class="px-4 py-8 text-center text-sm text-gray-500">${noNotifMsg}</div>`;
        return;
    }
    
    const html = notifications.map(notif => `
        <a href="${notif.link}" 
           class="block px-4 py-3 hover:bg-gray-50 transition ${notif.read ? 'opacity-75' : 'bg-primary-50'}"
           data-notification-id="${notif.id}"
           onclick="markAsRead(${notif.id}, event)">
            <div class="flex items-start gap-3">
                ${getIcon(notif.icon)}
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-900 ${notif.read ? '' : 'font-semibold'}">${notif.message}</p>
                    <p class="help-text">${notif.time_ago}</p>
                </div>
                ${!notif.read ? '<div class="flex-shrink-0 h-2 w-2 rounded-full bg-primary-600 mt-2"></div>' : ''}
            </div>
        </a>
    `).join('');
    
    list.innerHTML = html;
}

// Get icon SVG
function getIcon(iconName) {
    const icons = {
        'chat-bubble-left-right': '<svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>',
        'user-plus': '<svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /></svg>',
        'exclamation-circle': '<svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>',
        'bell': '<svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>'
    };
    return icons[iconName] || icons.bell;
}

// Mark notification as read
window.markAsRead = async function(notificationId, event) {
    try {
        await api(`${API_BASE}/${notificationId}/read`, 'POST');
        updateBadge();
        // Don't prevent default - let the link navigate
    } catch (error) {
        console.error('Failed to mark as read:', error);
    }
};

// Stop the polling interval (called on auth failure or unrecoverable error)
function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

// Update badge count — stops polling on auth errors to prevent toast floods
async function updateBadge() {
    try {
        const data = await api(API_BASE + '?limit=1');
        const count = data.count || 0;
        
        if (count > 0) {
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    } catch (error) {
        if (error instanceof AuthError) {
            stopPolling();
            return;
        }
        // Stop polling on persistent non-auth errors too (e.g. server down)
        stopPolling();
    }
}

// Poll for new notifications every 30 seconds
function startPolling() {
    updateBadge(); // Initial load
    pollInterval = setInterval(updateBadge, 30000);
}

// Start polling on page load
startPolling();

// Cleanup on page unload
window.addEventListener('beforeunload', stopPolling);
</script>
