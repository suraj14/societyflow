// Users CRUD - Instant AJAX Actions

function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg fixed top-4 right-4 z-50 max-w-md';
    alertDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <p class="font-medium">${message}</p>
        </div>
    `;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}

function showErrorMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg fixed top-4 right-4 z-50 max-w-md';
    alertDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-3"></i>
            <p class="font-medium">${message}</p>
        </div>
    `;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 5000);
}

// Create User via AJAX
document.addEventListener('DOMContentLoaded', function() {
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('{{ route("users.store") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Failed to create user');
                    });
                }
                return response.json();
            })
            .then(data => {
                closeAddUserDrawer();
                showSuccessMessage(data.success);
                // Reload table
                setTimeout(() => location.reload(), 500);
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage(error.message || 'Failed to create user');
            });
        });
    }
    
    // Update User via AJAX
    const updateUserForm = document.getElementById('updateUserForm');
    if (updateUserForm) {
        updateUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const userId = this.action.split('/').pop();
            const formData = new FormData(this);
            
            fetch(`/users/${userId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Failed to update user');
                    });
                }
                return response.json();
            })
            .then(data => {
                closeUpdateUserDrawer();
                showSuccessMessage(data.success);
                // Reload table
                setTimeout(() => location.reload(), 500);
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage(error.message || 'Failed to update user');
            });
        });
    }
});

// Delete User via AJAX
function confirmDelete() {
    const userId = document.getElementById('deleteUserId').value;
    
    fetch(`/users/${userId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || 'Failed to delete user');
            });
        }
        return response.json();
    })
    .then(data => {
        closeDeleteConfirmation();
        // Remove row from table
        const row = document.querySelector(`tr[data-user-id="${userId}"]`);
        if (row) {
            row.style.opacity = '0';
            row.style.transition = 'opacity 0.3s ease';
            setTimeout(() => row.remove(), 300);
        }
        showSuccessMessage(data.success);
        // Reload if no users left
        const tableBody = document.getElementById('usersTableBody');
        if (tableBody.children.length === 0) {
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage(error.message || 'Failed to delete user');
    });
}
