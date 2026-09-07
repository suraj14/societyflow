/**
 * INSTANT TABLE REFRESH - Universal solution for all CRUD operations
 * Refreshes table data without page reload
 * Works with all features: visitors, events, notices, facilities, etc.
 */

(function() {
    'use strict';

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInstantRefresh);
    } else {
        initInstantRefresh();
    }

    function initInstantRefresh() {
        console.log('🔄 Instant Table Refresh: Initializing...');
        
        // Expose refresh function globally
        window.instantRefresh = function() {
            console.log('🔄 Instant refresh triggered');
            refreshCurrentTable();
        };
        
        // Also expose for specific table refresh
        window.refreshTable = function(tableSelector) {
            console.log('🔄 Refreshing table:', tableSelector);
            refreshTableData(tableSelector);
        };
    }

    function refreshCurrentTable() {
        // Try to find the main data table
        const table = document.querySelector('table[data-table-id]') || 
                      document.querySelector('table.data-table') ||
                      document.querySelector('table');
        
        if (!table) {
            console.log('⚠️ No table found, falling back to page reload');
            reloadPageWithCacheBust();
            return;
        }

        const tableId = table.getAttribute('data-table-id') || 'main-table';
        console.log('🔄 Refreshing table:', tableId);
        
        // Get current URL and refresh data
        const currentUrl = window.location.href;
        const urlObj = new URL(currentUrl);
        
        // Add cache-bust parameter
        urlObj.searchParams.set('_t', Date.now());
        
        // Fetch fresh data
        fetch(urlObj.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.text();
        })
        .then(html => {
            console.log('✓ Fresh data received');
            
            // Parse the HTML
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');
            
            // Find the new table
            const newTable = newDoc.querySelector('table[data-table-id]') || 
                            newDoc.querySelector('table.data-table') ||
                            newDoc.querySelector('table');
            
            if (newTable) {
                // Replace table body
                const oldTbody = table.querySelector('tbody');
                const newTbody = newTable.querySelector('tbody');
                
                if (oldTbody && newTbody) {
                    oldTbody.innerHTML = newTbody.innerHTML;
                    console.log('✓ Table updated with fresh data');
                    
                    // Update stats if present
                    updateStats(newDoc);
                    
                    // Re-initialize any event listeners on new rows
                    initializeRowActions();
                    
                    return;
                }
            }
            
            // Fallback: reload page
            console.log('⚠️ Could not update table, reloading page');
            reloadPageWithCacheBust();
        })
        .catch(error => {
            console.error('❌ Error refreshing table:', error);
            // Fallback: reload page
            reloadPageWithCacheBust();
        });
    }

    function refreshTableData(tableSelector) {
        const table = document.querySelector(tableSelector);
        if (!table) {
            console.log('⚠️ Table not found:', tableSelector);
            return;
        }

        const currentUrl = window.location.href;
        const urlObj = new URL(currentUrl);
        urlObj.searchParams.set('_t', Date.now());
        
        fetch(urlObj.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache, no-store, must-revalidate'
            },
            credentials: 'same-origin'
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');
            const newTable = newDoc.querySelector(tableSelector);
            
            if (newTable) {
                const oldTbody = table.querySelector('tbody');
                const newTbody = newTable.querySelector('tbody');
                
                if (oldTbody && newTbody) {
                    oldTbody.innerHTML = newTbody.innerHTML;
                    console.log('✓ Table updated');
                    initializeRowActions();
                }
            }
        })
        .catch(error => console.error('❌ Error:', error));
    }

    function updateStats(newDoc) {
        // Update all stat elements
        const statElements = document.querySelectorAll('[data-stat]');
        
        statElements.forEach(element => {
            const statKey = element.getAttribute('data-stat');
            const newElement = newDoc.querySelector(`[data-stat="${statKey}"]`);
            
            if (newElement) {
                element.textContent = newElement.textContent;
                console.log(`✓ Updated stat: ${statKey}`);
            }
        });
    }

    function initializeRowActions() {
        // Re-attach event listeners to action buttons
        const deleteButtons = document.querySelectorAll('[data-action="delete"]');
        const editButtons = document.querySelectorAll('[data-action="edit"]');
        const approveButtons = document.querySelectorAll('[data-action="approve"]');
        
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', handleDelete);
        });
        
        editButtons.forEach(btn => {
            btn.addEventListener('click', handleEdit);
        });
        
        approveButtons.forEach(btn => {
            btn.addEventListener('click', handleApprove);
        });
    }

    function handleDelete(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this item?')) {
            const url = this.getAttribute('href');
            fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log('✓ Item deleted');
                window.instantRefresh();
            })
            .catch(error => console.error('❌ Error:', error));
        }
    }

    function handleEdit(e) {
        // Default behavior - navigate to edit page
        // Can be customized per feature
    }

    function handleApprove(e) {
        e.preventDefault();
        const url = this.getAttribute('href');
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('✓ Item approved');
            window.instantRefresh();
        })
        .catch(error => console.error('❌ Error:', error));
    }

    function reloadPageWithCacheBust() {
        const separator = window.location.href.includes('?') ? '&' : '?';
        window.location.href = window.location.href + separator + '_t=' + Date.now();
    }
})();
