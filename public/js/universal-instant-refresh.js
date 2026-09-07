/**
 * UNIVERSAL INSTANT REFRESH
 * Works for ALL features - visitors, events, notices, facilities, payments, etc.
 * Automatically detects table and refreshes data without page reload
 */

(function() {
    'use strict';

    /**
     * Universal table refresh function
     * Works for any page with a table
     */
    window.refreshCurrentTable = function() {
        const baseUrl = window.location.pathname;
        const url = baseUrl + '?_t=' + Date.now();

        console.log('🔄 Refreshing table data...');

        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            // Parse the HTML response
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');
            
            // Find all tables in the new document
            const newTables = newDoc.querySelectorAll('table');
            const currentTables = document.querySelectorAll('table');
            
            if (newTables.length > 0 && currentTables.length > 0) {
                // Replace each table body with new content
                newTables.forEach((newTable, index) => {
                    if (currentTables[index]) {
                        const newTableBody = newTable.querySelector('tbody');
                        const currentTableBody = currentTables[index].querySelector('tbody');
                        
                        if (newTableBody && currentTableBody) {
                            currentTableBody.innerHTML = newTableBody.innerHTML;
                            console.log(`✓ Table ${index + 1} updated`);
                        }
                    }
                });
            }
            
            // Update all stat cards
            updateAllStats(newDoc);
            
            // Update pagination if exists
            updatePagination(newDoc);
        })
        .catch(error => {
            console.error('Error refreshing table:', error);
        });
    };

    /**
     * Update all statistics cards on the page
     */
    function updateAllStats(newDoc) {
        const statCards = document.querySelectorAll('[data-stat]');
        
        statCards.forEach(card => {
            const statType = card.getAttribute('data-stat');
            const newCard = newDoc.querySelector(`[data-stat="${statType}"]`);
            
            if (newCard) {
                const newValue = newCard.querySelector('p:last-child');
                const currentValue = card.querySelector('p:last-child');
                
                if (newValue && currentValue && newValue.textContent !== currentValue.textContent) {
                    currentValue.style.transition = 'all 0.3s ease';
                    currentValue.textContent = newValue.textContent;
                }
            }
        });
    }

    /**
     * Update pagination if it exists
     */
    function updatePagination(newDoc) {
        const newPagination = newDoc.querySelector('[class*="pagination"]');
        const currentPagination = document.querySelector('[class*="pagination"]');
        
        if (newPagination && currentPagination) {
            currentPagination.innerHTML = newPagination.innerHTML;
            console.log('✓ Pagination updated');
        }
    }

    /**
     * Redirect to index page after create/edit
     * Used when form redirects to index
     */
    window.redirectToIndex = function(indexRoute) {
        window.location.href = indexRoute;
    };

    // Make function available globally
    console.log('✓ Universal Instant Refresh initialized');
})();
