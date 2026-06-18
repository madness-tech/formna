/**
 * File Preview Modal
 *
 * Opens a near-fullscreen lightbox to preview images and PDFs inline.
 * Non-previewable files (e.g. .doc, .docx) are left as plain download links.
 */

/** MIME types the browser can render inline */
const PREVIEWABLE_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
]);

/**
 * Check whether a given MIME type can be previewed in the modal.
 * Useful for views that need to decide between a preview button and a download link.
 */
export function isPreviewable(mimeType) {
    return PREVIEWABLE_TYPES.has(mimeType);
}

/**
 * Open the file preview modal.
 *
 * @param {number} fileId     - The file's numeric ID (used for /files/{id}/preview)
 * @param {string} fileName   - Original file name (shown in the header)
 * @param {string} mimeType   - MIME type (determines rendering strategy)
 */
export function openFilePreview(fileId, fileName, mimeType) {
    // Remove any existing preview modal
    closeFilePreview();

    const previewUrl = `/files/${fileId}/preview`;
    const downloadUrl = `/files/${fileId}/download`;
    const isImage = mimeType.startsWith('image/');

    // Build modal
    const overlay = document.createElement('div');
    overlay.id = 'file-preview-modal';
    overlay.className = 'fixed inset-0 flex items-center justify-center';
    overlay.style.zIndex = '9999';
    overlay.innerHTML = `
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/70 transition-opacity" data-preview-backdrop></div>

        <!-- Modal container -->
        <div class="relative z-10 flex flex-col bg-white rounded-lg shadow-2xl overflow-hidden"
             style="width: 95vw; height: 95vh; max-width: 95vw; max-height: 95vh;">

            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50 shrink-0">
                <div class="flex items-center gap-2 min-w-0">
                    <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-900 truncate">${escapeHtml(fileName)}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="${downloadUrl}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors" title="Download">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download
                    </a>
                    <button type="button" data-preview-close
                            class="p-1.5 text-gray-400 hover:text-gray-600 rounded-md hover:bg-gray-100 transition-colors"
                            title="Close (Esc)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Content area -->
            <div class="flex-1 overflow-auto bg-gray-100 flex items-center justify-center" data-preview-content>
                ${renderContent(previewUrl, mimeType, isImage, fileName)}
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Prevent body scroll
    document.body.style.overflow = 'hidden';

    // Event listeners
    overlay.querySelector('[data-preview-backdrop]').addEventListener('click', closeFilePreview);
    overlay.querySelector('[data-preview-close]').addEventListener('click', closeFilePreview);
    document.addEventListener('keydown', handleEscape);
}

/**
 * Close the file preview modal if open.
 */
export function closeFilePreview() {
    const modal = document.getElementById('file-preview-modal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleEscape);
    }
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

function handleEscape(e) {
    if (e.key === 'Escape') {
        closeFilePreview();
    }
}

function renderContent(previewUrl, mimeType, isImage, fileName) {
    if (isImage) {
        return `<img src="${previewUrl}"
                     alt="${escapeHtml(fileName)}"
                     class="max-w-full max-h-full object-contain select-none"
                     style="margin: auto;"
                     draggable="false"
                     loading="eager" />`;
    }

    if (mimeType === 'application/pdf') {
        return `<iframe src="${previewUrl}"
                        class="w-full h-full border-0"
                        title="${escapeHtml(fileName)}"></iframe>`;
    }

    // Fallback — should not normally be reached since views only show
    // the preview button for previewable types.
    return `<div class="text-center p-8">
                <p class="text-gray-500 text-sm">Preview not available for this file type.</p>
            </div>`;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
