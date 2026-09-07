/**
 * INSTANT CRUD HANDLER - Pure Laravel
 * 
 * Handles all Create/Read/Update/Delete operations without page reload
 * - Instant form submission via AJAX
 * - Real-time table updates
 * - Automatic stats refresh
 * - Cross-browser compatible
 * - Zero cache issues
 */

(function() {
    'use strict';

    // Get CSRF token from meta tag
    const getCsrfToken = () => {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    };

    // Show toast notification
    const showToast = (message, type = 'success') => {
        const toastId = 'toast-' + Date.now();
        const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
        
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg toast-enter z-50`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.remove('toast-enter');
            toast.classList.add('toast-exit');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    // Update table row with new data
    const updateTableRow = (rowId, data) => {
        const row = document.getElementById(rowId);
        if (!row) return;

        // Update all data attributes
        Object.keys(data).forEach(key => {
            const cell = row.querySelector(`[data-field="${key}"]`);
            if (cell) {
                cell.textContent = data[key];
                cell.classList.add('bg-yellow-100');
                setTimeout(() => cell.classList.remove('bg-yellow-100'), 1000);
            }
        });
    };

    // Remove table row
    const removeTableRow = (rowId) => {
        const row = document.getElementById(rowId);
        if (row) {
            row.style.opacity = '0';
            row.style.transition = 'opacity 0.3s ease-out';
            setTimeout(() => row.remove(), 300);
        }
    };

    // Add new row to table
    const addTableRow = (tableId, rowHtml) => {
        const table = document.getElementById(tableId);
        if (!table) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const tr = document.createElement('tr');
        tr.innerHTML = rowHtml;
        tr.style.opacity = '0';
        
        tbody.insertBefore(tr, tbody.firstChild);
        
        setTimeout(() => {
            tr.style.transition = 'opacity 0.3s ease-in';
            tr.style.opacity = '1';
        }, 10);
    };

    // Refresh stats/counters
    const refreshStats = async (statsUrl) => {
        if (!statsUrl) return;

        try {
            const response = await fetch(statsUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Cache-Control': 'no-cache, no-store, must-revalidate'
                },
                cache: 'no-store'
            });

            if (!response.ok) return;

            const data = await response.json();

            // Update all stat elements
            Object.keys(data).forEach(key => {
                const elements = document.querySelectorAll(`[data-stat="${key}"]`);
                elements.forEach(el => {
                    el.textContent = data[key];
                    el.classList.add('bg-yellow-100');
                    setTimeout(() => el.classList.remove('bg-yellow-100'), 1000);
                });
            });
        } catch (error) {
            console.error('Stats refresh error:', error);
        }
    };

    // Handle form submission
    const handleFormSubmit = (form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(form);
            const url = form.getAttribute('action');
            const method = form.getAttribute('method') || 'POST';
            const redirectUrl = form.getAttribute('data-redirect');
            const tableId = form.getAttribute('data-table');
            const statsUrl = form.getAttribute('data-stats');
            const rowId = form.getAttribute('data-row-id');

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Cache-Control': 'no-cache, no-store, must-revalidate'
                    },
                    body: formData,
                    cache: 'no-store'
                });

                const data = await response.json();

                if (response.ok) {
                    showToast(data.message || 'Operation successful', 'success');

                    // Update table if needed
                    if (data.row && rowId) {
                        updateTableRow(rowId, data.row);
                    } else if (data.row && tableId) {
                        addTableRow(tableId, data.rowHtml);
                    }

                    // Refresh stats
                    if (statsUrl) {
                        await refreshStats(statsUrl);
                    }

                    // Close modal if exists
                    const modal = form.closest('[role="dialog"]');
                    if (modal) {
                        modal.style.display = 'none';
                    }

                    // Redirect if specified
                    if (redirectUrl) {
                        setTimeout(() => window.location.href = redirectUrl, 500);
                    } else {
                        // Reset form
                        form.reset();
                    }
                } else {
                    showToast(data.message || 'Operation failed', 'error');
                }
            } catch (error) {
                console.error('Form submission error:', error);
                showToast('An error occurred', 'error');
            }
        });
    };

    // Handle delete button
    const handleDeleteButton = (btn) => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();

            if (!confirm('Are you sure?')) return;

            const url = btn.getAttribute('data-url');
            const rowId = btn.getAttribute('data-row-id');
            const statsUrl = btn.getAttribute('data-stats');

            try {
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Cache-Control': 'no-cache, no-store, must-revalidate'
                    },
                    cache: 'no-store'
                });

                const data = await response.json();

                if (response.ok) {
                    showToast(data.message || 'Deleted successfully', 'success');

                    // Remove row
                    if (rowId) {
                        removeTableRow(rowId);
                    }

                    // Refresh stats
                    if (statsUrl) {
                        await refreshStats(statsUrl);
                    }
                } else {
                    showToast(data.message || 'Delete failed', 'error');
                }
            } catch (error) {
                console.error('Delete error:', error);
                showToast('An error occurred', 'error');
            }
        });
    };

    // Initialize all forms and buttons
    const init = () => {
        // Handle all forms with data-instant attribute
        document.querySelectorAll('form[data-instant]').forEach(form => {
            handleFormSubmit(form);
        });

        // Handle all delete buttons with data-delete attribute
        document.querySelectorAll('[data-delete]').forEach(btn => {
            handleDeleteButton(btn);
        });

        // Re-initialize on dynamic content
        document.addEventListener('DOMContentLoaded', init);
    };

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose functions globally for manual use
    window.InstantCRUD = {
        updateRow: updateTableRow,
        removeRow: removeTableRow,
        addRow: addTableRow,
        refreshStats: refreshStats,
        showToast: showToast
    };
})();
