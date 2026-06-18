/**
 * Form Admins Management
 * Handles user search, adding/removing admins, and permission updates
 */

class FormAdmins {
    constructor(formUuid, currentUserId, isOwner) {
        this.formUuid = formUuid;
        this.currentUserId = currentUserId;
        this.isOwner = isOwner;
        this.searchTimeout = null;
        this.csrfToken = document.querySelector('input[name="_csrf"]')?.value || '';
        
        this.init();
    }
    
    init() {
        this.setupSearch();
        this.loadAdmins();
    }
    
    setupSearch() {
        const searchInput = document.getElementById('admin-search');
        const searchResults = document.getElementById('search-results');
        
        if (!searchInput) return;
        
        // Debounced search
        searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 3) {
                searchResults.classList.add('hidden');
                return;
            }
            
            this.searchTimeout = setTimeout(() => {
                this.searchUsers(query);
            }, 300);
        });
        
        // Close search results when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });
    }
    
    async searchUsers(query) {
        const searchResults = document.getElementById('search-results');
        
        try {
            const response = await fetch(`/api/admin/users/search?q=${encodeURIComponent(query)}&form_uuid=${encodeURIComponent(this.formUuid)}`);
            
            if (!response.ok) {
                throw new Error('Search failed');
            }
            
            const data = await response.json();
            this.displaySearchResults(data.users);
            
        } catch (error) {
            console.error('Search error:', error);
            searchResults.innerHTML = '<div class="p-3 text-sm text-red-600">Search failed. Please try again.</div>';
            searchResults.classList.remove('hidden');
        }
    }
    
    displaySearchResults(users) {
        const searchResults = document.getElementById('search-results');
        
        if (users.length === 0) {
            searchResults.innerHTML = `
                <div class="p-4 text-center">
                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    <p class="text-sm font-medium text-gray-900 mb-1">No administrators available</p>
                    <p class="help-text">All eligible admins have already been added or no matches found</p>
                </div>
            `;
            searchResults.classList.remove('hidden');
            return;
        }
        
        // Build results using safe DOM methods instead of innerHTML to prevent XSS
        searchResults.innerHTML = '';
        users.forEach(user => {
            const row = document.createElement('div');
            row.className = 'p-3 hover:bg-primary-50 cursor-pointer border-b border-gray-100 last:border-b-0 transition';
            row.dataset.userId = user.id;
            row.dataset.userName = user.name;
            row.dataset.userEmail = user.email;
            row.dataset.userRole = user.role;

            const nameDiv = document.createElement('div');
            nameDiv.className = 'font-medium text-gray-900';
            nameDiv.textContent = user.name;

            const detailDiv = document.createElement('div');
            detailDiv.className = 'text-sm text-gray-500';
            detailDiv.textContent = `${user.email} · ${this.formatRole(user.role)}`;

            row.appendChild(nameDiv);
            row.appendChild(detailDiv);
            row.addEventListener('click', () => this.selectUser(row));
            searchResults.appendChild(row);
        });
        
        searchResults.classList.remove('hidden');
    }
    
    selectUser(element) {
        const userId = element.dataset.userId;
        const userName = element.dataset.userName;
        const userEmail = element.dataset.userEmail;
        const userRole = element.dataset.userRole;
        
        // Hide search results
        document.getElementById('search-results').classList.add('hidden');
        document.getElementById('admin-search').value = '';
        
        // Show permission selector modal
        this.showPermissionModal(userId, userName, userEmail, userRole);
    }
    
    showPermissionModal(userId, userName, userEmail, userRole) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4';
        modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full transform transition-all">
                <!-- Header -->
                <div class="bg-gradient-to-r from-primary-600 to-purple-600 px-6 py-5 rounded-t-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Add Administrator</h3>
                                <p class="text-sm text-primary-100 mt-0.5">Grant access permissions</p>
                            </div>
                        </div>
                        <button type="button" onclick="this.closest('.fixed').remove()" class="text-white hover:text-primary-100 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Body -->
                <div class="p-6 space-y-6">
                    <!-- User Info Card -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center">
                                <span class="text-primary-600 font-semibold text-lg">${this.escapeHtml(userName).charAt(0).toUpperCase()}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-gray-900 truncate">${this.escapeHtml(userName)}</h4>
                                <p class="body-text truncate">${this.escapeHtml(userEmail)}</p>
                                <span class="badge badge-blue mt-1">
                                    ${this.formatRole(userRole)}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Permission Selection -->
                    <div>
                        <label class="text-gray-900 mb-3">Select Permission Level</label>
                        <div class="space-y-3">
                            <label class="flex items-start p-4 border-2 border-gray-200 rounded-lg hover:border-primary-300 cursor-pointer transition group">
                                <input type="radio" name="permission" value="view_results" checked class="mt-1 h-4 w-4 text-primary-600 focus:ring-primary-500">
                                <div class="ml-3 flex-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-gray-900 group-hover:text-primary-600">Manage Submissions</span>
                                        <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded">Default</span>
                                    </div>
                                    <p class="body-text mt-1">View submissions and request clarifications</p>
                                </div>
                            </label>
                            
                            <label class="flex items-start p-4 border-2 border-gray-200 rounded-lg hover:border-primary-300 cursor-pointer transition group">
                                <input type="radio" name="permission" value="manage" class="mt-1 h-4 w-4 text-primary-600 focus:ring-primary-500">
                                <div class="ml-3 flex-1">
                                    <span class="font-medium text-gray-900 group-hover:text-primary-600">Manage Form & Settings</span>
                                    <p class="body-text mt-1">Full access to edit form, settings, and scoring</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-4 rounded-b-xl flex justify-end gap-3 border-t border-gray-200">
                    <button type="button" onclick="this.closest('.fixed').remove()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="button" onclick="window.formAdminsManager.addAdmin(${userId}, document.querySelector('input[name=permission]:checked').value, this.closest('.fixed'))" class="btn btn-primary">
                        Add Administrator
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    async addAdmin(userId, access, modal) {
        try {
            const response = await fetch(`/api/admin/forms/${this.formUuid}/admins`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({ user_id: userId, access })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to add administrator');
            }
            
            // Close modal
            modal.remove();
            
            // Reload admins list
            this.loadAdmins();
            
            this.showMessage('Administrator added successfully', 'success');
            
        } catch (error) {
            console.error('Add admin error:', error);
            this.showMessage(error.message, 'error');
        }
    }
    
    async loadAdmins() {
        const tbody = document.getElementById('admins-table-body');
        
        try {
            const response = await fetch(`/api/admin/forms/${this.formUuid}/admins`);
            
            if (!response.ok) {
                throw new Error('Failed to load administrators');
            }
            
            const data = await response.json();
            this.displayAdmins(data.owner, data.admins);
            
        } catch (error) {
            console.error('Load admins error:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="py-8 text-center text-red-600">
                        Failed to load administrators. Please refresh the page.
                    </td>
                </tr>
            `;
        }
    }
    
    displayAdmins(owner, admins) {
        const tbody = document.getElementById('admins-table-body');
        let html = '';
        
        // Owner row
        if (owner) {
            html += `
                <tr class="bg-purple-50">
                    <td>
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-purple-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <div class="font-medium text-gray-900">${this.escapeHtml(owner.name)}</div>
                                <div class="text-sm text-gray-500">${this.escapeHtml(owner.email)}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full ${this.getRoleBadgeClass(owner.role)}">
                            ${this.formatRole(owner.role)}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-purple">
                            Owner
                        </span>
                    </td>
                    <td class="text-gray-500">-</td>
                    <td class="text-right text-gray-400">-</td>
                </tr>
            `;
        }
        
        // Admin rows
        if (admins.length === 0 && owner) {
            html += `
                <tr>
                    <td colspan="5" class="empty-state">
                        <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        No additional administrators added yet
                    </td>
                </tr>
            `;
        }
        
        admins.forEach(admin => {
            const canRemove = this.isOwner || admin.id === this.currentUserId;
            const canUpdate = this.isOwner;
            
            html += `
                <tr>
                    <td>
                        <div>
                            <div class="font-medium text-gray-900">${this.escapeHtml(admin.name)}</div>
                            <div class="text-sm text-gray-500">${this.escapeHtml(admin.email)}</div>
                        </div>
                    </td>
                    <td>
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full ${this.getRoleBadgeClass(admin.role)}">
                            ${this.formatRole(admin.role)}
                        </span>
                    </td>
                    <td>
                        ${canUpdate ? `
                            <select class="px-2 py-1 text-xs font-medium rounded-full border-0 ${this.getAccessBadgeClass(admin.access)}" 
                                    data-action="update-permission" data-user-id="${admin.id}">
                                <option value="view_results" ${admin.access === 'view_results' ? 'selected' : ''}>Manage Submissions</option>
                                <option value="manage" ${admin.access === 'manage' ? 'selected' : ''}>Manage Form & Settings</option>
                            </select>
                        ` : `
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full ${this.getAccessBadgeClass(admin.access)}">
                                ${this.formatAccess(admin.access)}
                            </span>
                        `}
                    </td>
                    <td class="text-gray-500">${this.formatDate(admin.granted_at)}</td>
                    <td class="text-right">
                        ${canRemove ? `
                            <button type="button"
                                    data-action="remove-admin" data-user-id="${admin.id}" data-user-name="${this.escapeHtml(admin.name)}"
                                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Remove
                            </button>
                        ` : `
                            <span class="text-gray-400 text-sm">-</span>
                        `}
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;

        // Attach event listeners via delegation instead of inline handlers
        // to avoid JS-in-HTML-attribute XSS (escapeHtml is insufficient in
        // that mixed context because the browser HTML-decodes before JS eval).
        tbody.querySelectorAll('[data-action="remove-admin"]').forEach(btn => {
            btn.addEventListener('click', () => {
                this.confirmRemove(Number(btn.dataset.userId), btn.dataset.userName);
            });
        });
        tbody.querySelectorAll('[data-action="update-permission"]').forEach(sel => {
            sel.addEventListener('change', () => {
                this.updatePermission(Number(sel.dataset.userId), sel.value);
            });
        });
    }
    
    async updatePermission(userId, access) {
        try {
            const response = await fetch(`/api/admin/forms/${this.formUuid}/admins/${userId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({ access })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to update permission');
            }
            
            this.showMessage('Permission updated successfully', 'success');
            
        } catch (error) {
            console.error('Update permission error:', error);
            this.showMessage(error.message, 'error');
            this.loadAdmins(); // Reload to reset dropdown
        }
    }
    
    confirmRemove(userId, userName) {
        const isSelf = userId === this.currentUserId;
        const message = isSelf 
            ? 'Are you sure you want to remove yourself as an administrator? You will lose access to this form.'
            : `Are you sure you want to remove ${userName} as an administrator?`;
        
        if (confirm(message)) {
            this.removeAdmin(userId);
        }
    }
    
    async removeAdmin(userId) {
        try {
            const response = await fetch(`/api/admin/forms/${this.formUuid}/admins/${userId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                }
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to remove administrator');
            }
            
            // If user removed themselves, redirect to forms list
            if (userId === this.currentUserId) {
                window.location.href = '/admin/forms';
                return;
            }
            
            this.loadAdmins();
            this.showMessage('Administrator removed successfully', 'success');
            
        } catch (error) {
            console.error('Remove admin error:', error);
            this.showMessage(error.message, 'error');
        }
    }
    
    // Helper methods
    getFormId() {
        // Extract form ID from current data (could be improved)
        return this.formUuid;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/'/g, '&#39;');
    }
    
    formatRole(role) {
        const roles = {
            'super_admin': 'Super Admin',
            'admin': 'Admin',
            'reviewer': 'Reviewer',
            'user': 'User'
        };
        return roles[role] || role;
    }
    
    formatAccess(access) {
        return access === 'manage' ? 'Manage Form & Settings' : 'Manage Submissions';
    }
    
    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }
    
    getRoleBadgeClass(role) {
        const classes = {
            'super_admin': 'bg-purple-100 text-purple-800',
            'admin': 'bg-blue-100 text-blue-800',
            'reviewer': 'bg-green-100 text-green-800',
            'user': 'bg-gray-100 text-gray-800'
        };
        return classes[role] || 'bg-gray-100 text-gray-800';
    }
    
    getAccessBadgeClass(access) {
        return access === 'manage' ? 'bg-primary-100 text-primary-800' : 'bg-blue-100 text-blue-800';
    }
    
    showMessage(message, type) {
        // Create a flash message
        const flash = document.createElement('div');
        flash.className = `fixed top-4 right-4 z-50 max-w-sm px-4 py-3 rounded-lg shadow-lg ${
            type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
        }`;
        flash.textContent = message;
        document.body.appendChild(flash);
        
        setTimeout(() => {
            flash.remove();
        }, 3000);
    }
}
