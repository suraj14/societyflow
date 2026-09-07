/**
 * TRUE INSTANT FORM SUBMISSION - NO PAGE RELOAD
 * Submits via AJAX, updates DOM instantly, no refresh needed
 */

(function() {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFormHandler);
    } else {
        initFormHandler();
    }

    function initFormHandler() {
        console.log('🔧 Instant Form Handler: Initializing...');
        document.addEventListener('submit', handleFormSubmit, true);
    }

    function handleFormSubmit(e) {
        const form = e.target;
        
        if (form.hasAttribute('data-no-ajax')) return;
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) return;

        e.preventDefault();
        submitViaAjax(form);
    }

    function submitViaAjax(form) {
        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        const action = form.getAttribute('action');
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '';
        
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        }

        const formData = new FormData(form);
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        // Build request
        const options = {
            method: method,
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        };

        // Handle file uploads vs regular forms
        if (form.enctype === 'multipart/form-data') {
            options.body = formData;
        } else {
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key !== '_token') data[key] = value;
            }
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }

        fetch(action, options)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(result => {
                console.log('✅ Success:', result);
                showNotification('success', result.message || 'Operation completed');
                
                // Reset form
                form.reset();
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
                
                // Refresh table instantly
                refreshTableData();
            })
            .catch(error => {
                console.error('❌ Error:', error);
                showNotification('error', error.message || 'An error occurred');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
    }

    function refreshTableData() {
        console.log('🔄 Refreshing table data...');
        
        // Find the main table
        const table = document.querySelector('table');
        if (!table) {
            console.log('No table found, reloading page');
            location.reload();
            return;
        }

        // Get current URL with cache bust
        const url = new URL(window.location);
        url.searchParams.set('_t', Date.now());

        fetch(url.toString(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        })
        .then(response => response.text())
        .then(html => {
            // Parse new HTML
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');
            
            // Find new table
            const newTable = newDoc.querySelector('table');
            if (newTable && table) {
                // Replace table body
                const oldTbody = table.querySelector('tbody');
                const newTbody = newTable.querySelector('tbody');
                if (oldTbody && newTbody) {
                    oldTbody.innerHTML = newTbody.innerHTML;
                    console.log('✅ Table updated instantly');
                    
                    // Update stats
                    updateStats(newDoc);
                    return;
                }
            }
            
            // Fallback: reload page
            console.log('Fallback: reloading page');
            location.reload();
        })
        .catch(error => {
            console.error('Error refreshing table:', error);
            location.reload();
        });
    }

    function updateStats(newDoc) {
        // Update all stat cards
        const stats = document.querySelectorAll('[data-stat]');
        stats.forEach(stat => {
            const key = stat.getAttribute('data-stat');
            const newStat = newDoc.querySelector(`[data-stat="${key}"]`);
            if (newStat) {
                stat.innerHTML = newStat.innerHTML;
            }
        });
    }

    function showNotification(type, message) {
        const div = document.createElement('div');
        div.className = `fixed top-4 right-4 p-4 rounded-lg text-white z-50 shadow-lg ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        }`;
        div.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>${message}`;
        document.body.appendChild(div);
        
        setTimeout(() => {
            div.style.opacity = '0';
            div.style.transition = 'opacity 0.3s';
            setTimeout(() => div.remove(), 300);
        }, 3000);
    }
})();
