/**
 * COMPREHENSIVE AJAX FORM HANDLER
 * Handles all create/edit/delete operations instantly
 * No page reload, instant DOM updates
 */

(function() {
    'use strict';

    // Wait for jQuery
    if (typeof jQuery === 'undefined') {
        console.error('jQuery not loaded');
        return;
    }

    const $ = jQuery;

    $(document).ready(function() {
        console.log('✅ AJAX Form Handler: Initializing...');

        // Setup CSRF token for all AJAX requests
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        // Handle ALL form submissions (including DELETE forms)
        $(document).on('submit', 'form:not([data-no-ajax])', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const method = $form.attr('method') || 'POST';
            
            // Check if this is a DELETE form
            if (method.toUpperCase() === 'POST' && $form.find('input[name="_method"][value="DELETE"]').length > 0) {
                // This is a DELETE request
                const url = $form.attr('action');
                const $row = $form.closest('tr');
                const id = $row.attr('id') ? $row.attr('id').replace('row-', '') : null;
                
                if (confirm('Are you sure you want to delete this item?')) {
                    deleteItem(url, id);
                }
            } else {
                // Regular form submission
                handleFormSubmit($form);
            }
        });

        // Handle delete buttons (for backward compatibility)
        $(document).on('click', '.delete-btn, [data-action="delete"]', function(e) {
            e.preventDefault();
            const url = $(this).attr('href') || $(this).data('url');
            const id = $(this).data('id');
            
            if (confirm('Are you sure you want to delete this item?')) {
                deleteItem(url, id);
            }
        });

        // Handle approve/allow buttons
        $(document).on('click', '.approve-btn, [data-action="approve"]', function(e) {
            e.preventDefault();
            const url = $(this).attr('href') || $(this).data('url');
            approveItem(url);
        });
    });

    /**
     * Handle form submission via AJAX
     */
    function handleFormSubmit($form) {
        const url = $form.attr('action');
        const method = $form.attr('method') || 'POST';
        const hasFiles = $form.attr('enctype') === 'multipart/form-data';

        // Show loading state
        const $submitBtn = $form.find('button[type="submit"]');
        const originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Processing...');

        // Prepare data
        let data;
        if (hasFiles) {
            data = new FormData($form[0]);
        } else {
            data = $form.serialize();
        }

        // Send AJAX request
        $.ajax({
            url: url,
            type: method,
            data: data,
            processData: !hasFiles,
            contentType: hasFiles ? false : 'application/x-www-form-urlencoded',
            dataType: 'json',
            success: function(response) {
                console.log('✅ Success:', response);
                
                // Show success notification
                showNotification('success', response.message || 'Operation completed successfully');
                
                // Reset form
                $form[0].reset();
                
                // Refresh table data
                refreshTableData();
                
                // Close modal if exists
                const $modal = $form.closest('.modal');
                if ($modal.length) {
                    $modal.modal('hide');
                }
            },
            error: function(xhr) {
                console.error('❌ Error:', xhr);
                const message = xhr.responseJSON?.message || 'An error occurred';
                showNotification('error', message);
            },
            complete: function() {
                // Restore button
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }

    /**
     * Delete item via AJAX
     */
    function deleteItem(url, id) {
        $.ajax({
            url: url,
            type: 'DELETE',
            dataType: 'json',
            success: function(response) {
                console.log('✅ Deleted:', response);
                showNotification('success', response.message || 'Item deleted successfully');
                
                // Find and remove the row - try multiple selectors
                let $row = null;
                
                if (id) {
                    // Try ID-based selector first
                    $row = $(`#row-${id}`);
                    if ($row.length === 0) {
                        // Try finding by data attribute
                        $row = $(`tr[data-id="${id}"]`);
                    }
                    if ($row.length === 0) {
                        // Try finding the closest tr from the form
                        $row = $(`tr:has(form[action*="${id}"])`);
                    }
                }
                
                if ($row && $row.length > 0) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        console.log('✅ Row removed from table');
                        // Refresh stats after row removal
                        setTimeout(refreshTableData, 300);
                    });
                } else {
                    console.log('⚠️ Row not found, refreshing entire table');
                    // If row not found, refresh the entire table
                    setTimeout(refreshTableData, 300);
                }
            },
            error: function(xhr) {
                console.error('❌ Error:', xhr);
                const message = xhr.responseJSON?.message || 'Failed to delete item';
                showNotification('error', message);
            }
        });
    }

    /**
     * Approve/Allow item via AJAX
     */
    function approveItem(url) {
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                console.log('✅ Approved:', response);
                showNotification('success', response.message || 'Item approved successfully');
                refreshTableData();
            },
            error: function(xhr) {
                console.error('❌ Error:', xhr);
                showNotification('error', 'Failed to approve item');
            }
        });
    }

    /**
     * Refresh table data instantly
     */
    function refreshTableData() {
        console.log('🔄 Refreshing table data...');
        
        const $table = $('table');
        if (!$table.length) {
            console.log('No table found');
            return;
        }

        // Get current URL with cache bust
        const url = new URL(window.location);
        url.searchParams.set('_t', Date.now());

        $.ajax({
            url: url.toString(),
            type: 'GET',
            dataType: 'html',
            success: function(html) {
                // Parse new HTML
                const $newDoc = $(html);
                const $newTable = $newDoc.find('table');
                
                if ($newTable.length) {
                    // Replace table body
                    const $newTbody = $newTable.find('tbody');
                    $table.find('tbody').html($newTbody.html());
                    console.log('✅ Table updated');
                    
                    // Update stats
                    updateStats($newDoc);
                } else {
                    console.log('No table found in response');
                }
            },
            error: function(xhr) {
                console.error('Error refreshing table:', xhr);
            }
        });
    }

    /**
     * Update statistics
     */
    function updateStats($newDoc) {
        $('[data-stat]').each(function() {
            const key = $(this).attr('data-stat');
            const $newStat = $newDoc.find(`[data-stat="${key}"]`);
            if ($newStat.length) {
                $(this).html($newStat.html());
                console.log(`✅ Updated stat: ${key}`);
            }
        });
    }

    /**
     * Show notification
     */
    function showNotification(type, message) {
        const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        const $notification = $(`
            <div class="fixed top-4 right-4 p-4 rounded-lg text-white z-50 shadow-lg ${bgColor}">
                <div class="flex items-center">
                    <i class="fas ${icon} mr-2"></i>
                    <span>${message}</span>
                </div>
            </div>
        `);
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Expose functions globally
    window.refreshTableData = refreshTableData;
    window.updateStats = updateStats;
    window.showNotification = showNotification;

})();
