/**
 * INSTANT VISITOR REFRESH
 * Fetches and displays new visitor data via AJAX without page reload
 */

(function() {
    'use strict';

    /**
     * Refresh the visitor table by fetching fresh data
     */
    window.refreshVisitorTable = function() {
        const baseUrl = window.location.pathname;
        const url = baseUrl + '?_t=' + Date.now();

        console.log('🔄 Fetching fresh visitor data...');

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
            
            // Find the table body in the new document
            const newTableBody = newDoc.querySelector('tbody');
            const currentTableBody = document.querySelector('tbody');
            
            if (newTableBody && currentTableBody) {
                // Replace table body with new content
                currentTableBody.innerHTML = newTableBody.innerHTML;
                console.log('✓ Visitor table updated instantly');
                
                // Update stats if they exist
                updateStats(newDoc);
                
                // Re-initialize any event listeners if needed
                reinitializeTableEvents();
            }
        })
        .catch(error => {
            console.error('Error refreshing visitor table:', error);
        });
    };

    /**
     * Update statistics cards
     */
    function updateStats(newDoc) {
        const statCards = document.querySelectorAll('[data-stat]');
        
        statCards.forEach(card => {
            const statType = card.getAttribute('data-stat');
            const newCard = newDoc.querySelector(`[data-stat="${statType}"]`);
            
            if (newCard) {
                const newValue = newCard.querySelector('p:last-child');
                const currentValue = card.querySelector('p:last-child');
                
                if (newValue && currentValue && newValue.textContent !== currentValue.textContent) {
                    // Animate the change
                    currentValue.style.transition = 'all 0.3s ease';
                    currentValue.textContent = newValue.textContent;
                }
            }
        });
    }

    /**
     * Re-initialize table event listeners
     */
    function reinitializeTableEvents() {
        // Re-attach any event listeners to new table rows if needed
        // This is called after table content is updated
        console.log('Table events re-initialized');
    }

    // Make function available globally
    console.log('✓ Instant Visitor Refresh initialized');
})();
