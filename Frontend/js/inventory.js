document.addEventListener('DOMContentLoaded', function () {

    // Load navbar
    fetch('/hfc_inventory/Frontend/partials/navbar.php')
        .then(response => {
            if (!response.ok) throw new Error('Failed to load navbar');
            return response.text();
        })
        .then(html => {
            document.getElementById('navbarContainer').innerHTML = html;
            if (typeof user !== 'undefined' && user.name) {
                document.getElementById('userName').textContent = user.name.split(' ')[0];
            }
        })
        .catch(error => {
            console.error('Navbar error:', error);
            document.getElementById('navbarContainer').innerHTML = `
                <nav class="navbar navbar-dark bg-primary">
                    <div class="container">
                        <a class="navbar-brand" href="#">
                            <img src="/hfc_inventory/Frontend/images/HFC-logo.png" alt="HFC" height="40">
                            HFC Inventory System
                        </a>
                    </div>
                </nav>
            `;
        });

    // Initialize inventory data
    let inventoryData = [];
    let categoriesData = [];
    
    // Fetch categories and inventory items from the API
    fetchCategories().then(() => fetchInventoryItems());
    
    // Set up event listeners
    document.getElementById('searchInput').addEventListener('input', function () {
        const search = this.value.toLowerCase();
        const filtered = inventoryData.filter(item =>
            item.name.toLowerCase().includes(search) ||
            item.category.toLowerCase().includes(search)
        );
        renderInventoryTable(filtered);
    });

    document.getElementById('categoryFilter').addEventListener('change', function () {
        const category = this.value;
        const filtered = category === 'all'
            ? inventoryData
            : inventoryData.filter(item => item.category === category);
        renderInventoryTable(filtered);
    });

    document.getElementById('addItemForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const newItem = {
            name: document.getElementById('itemName').value,
            category_id: parseInt(document.getElementById('itemCategory').value),
            quantity: parseInt(document.getElementById('itemQuantity').value),
        };

        // Add item via API
        console.log('Adding item:', newItem);   
        addInventoryItem(newItem);
        this.reset();
    });

    // Add event delegation for edit and delete buttons
    document.getElementById('inventoryTable').addEventListener('click', function(e) {
        // Handle edit button click
        if (e.target.closest('.edit-btn')) {
            const id = e.target.closest('.edit-btn').getAttribute('data-id');
            openEditModal(id);
        }
        
        // Handle delete button click
        if (e.target.closest('.delete-btn')) {
            const id = e.target.closest('.delete-btn').getAttribute('data-id');
            confirmDelete(id);
        }
        
        // Handle request button click
        if (e.target.closest('.request-btn')) {
            const id = e.target.closest('.request-btn').getAttribute('data-id');
            openRequestModal(id);
        }
    });
    
    // Function to fetch all categories from the API
    function fetchCategories() {
        return fetch('/hfc_inventory/includes/inventory_api.php?action=get_categories')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    categoriesData = data.data;
                    populateCategoryDropdowns();
                } else {
                    showAlert(data.error || 'Failed to fetch categories', 'danger');
                }
            })
            .catch(error => {
                console.error('Error fetching categories:', error);
                showAlert('Error loading categories. Please try again later.', 'danger');
            });
    }
    
    // Function to populate category dropdowns
    function populateCategoryDropdowns() {
        // Populate the add item form dropdown
        const addItemCategorySelect = document.getElementById('itemCategory');
        addItemCategorySelect.innerHTML = '<option value="">Select category</option>';
        
        // Populate the category filter dropdown
        const categoryFilterSelect = document.getElementById('categoryFilter');
        categoryFilterSelect.innerHTML = '<option value="all">All Categories</option>';
        
        categoriesData.forEach(category => {
            // Add to the add item form
            const addOption = document.createElement('option');
            addOption.value = category.id;
            addOption.textContent = category.name;
            addItemCategorySelect.appendChild(addOption);
            
            // Add to the filter
            const filterOption = document.createElement('option');
            filterOption.value = category.name;
            filterOption.textContent = category.name;
            categoryFilterSelect.appendChild(filterOption);
        });
    }
    
    // Function to fetch all inventory items from the API
    function fetchInventoryItems() {
        return fetch('/hfc_inventory/includes/inventory_api.php?action=get_all')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    inventoryData = data.data;
                    renderInventoryTable(inventoryData);
                } else {
                    showAlert(data.error || 'Failed to fetch inventory items', 'danger');
                }
            })
            .catch(error => {
                console.error('Error fetching inventory:', error);
                showAlert('Error loading inventory. Please try again later.', 'danger');
            });
    }
    
    // Function to add a new inventory item via API
    function addInventoryItem(item) {
        console.log('Adding item:', item);
        fetch('/hfc_inventory/includes/inventory_api.php?action=add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(item)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add the new item to the local data
                inventoryData.push(data.data);
                renderInventoryTable(inventoryData);
                
                // Close the modal and show success message
                bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
                showAlert('Item added successfully!', 'success');
            } else {
                showAlert(data.error || 'Failed to add item', 'danger');
            }
        })
        .catch(error => {
            console.error('Error adding item:', error);
            showAlert('Error adding item. Please try again.', 'danger');
        });
    }
    
    // Function to update an inventory item via API
    function updateInventoryItem(id, updatedData) {
        fetch(`/hfc_inventory/includes/inventory_api.php?action=update&id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(updatedData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the item in the local data
                const index = inventoryData.findIndex(item => item.id == id);
                if (index !== -1) {
                    inventoryData[index] = data.data;
                }
                renderInventoryTable(inventoryData);
                
                // Close the modal and show success message
                if (document.getElementById('editItemModal')) {
                    bootstrap.Modal.getInstance(document.getElementById('editItemModal')).hide();
                }
                showAlert('Item updated successfully!', 'success');
            } else {
                showAlert(data.error || 'Failed to update item', 'danger');
            }
        })
        .catch(error => {
            console.error('Error updating item:', error);
            showAlert('Error updating item. Please try again.', 'danger');
        });
    }
    
    // Function to delete an inventory item via API
    function deleteInventoryItem(id) {
        fetch(`/hfc_inventory/includes/inventory_api.php?action=delete&id=${id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the item from the local data
                inventoryData = inventoryData.filter(item => item.id != id);
                renderInventoryTable(inventoryData);
                
                showAlert('Item deleted successfully!', 'success');
            } else {
                showAlert(data.error || 'Failed to delete item', 'danger');
            }
        })
        .catch(error => {
            console.error('Error deleting item:', error);
            showAlert('Error deleting item. Please try again.', 'danger');
        });
    }
    
    // Function to open the edit modal for an item
    function openEditModal(id) {
        const item = inventoryData.find(item => item.id == id);
        if (!item) return;
        
        // Check if the edit modal already exists
        let editModal = document.getElementById('editItemModal');
        
        // If not, create it
        if (!editModal) {
            const modalHTML = `
                <div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="editItemForm">
                                    <input type="hidden" id="editItemId">
                                    <div class="mb-3">
                                        <label for="editItemName" class="form-label">Item Name</label>
                                        <input type="text" class="form-control" id="editItemName" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="editItemCategory" class="form-label">Category</label>
                                        <select id="editItemCategory" class="form-select" required>
                                            <option value="">Select category</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="editItemQuantity" class="form-label">Quantity</label>
                                        <input type="number" class="form-control" id="editItemQuantity" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="editItemMinQuantity" class="form-label">Minimum Quantity</label>
                                        <input type="number" class="form-control" id="editItemMinQuantity" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="editItemLocation" class="form-label">Location</label>
                                        <input type="text" class="form-control" id="editItemLocation">
                                    </div>
                                    <div class="mb-3">
                                        <label for="editItemDescription" class="form-label">Description</label>
                                        <textarea class="form-control" id="editItemDescription" rows="3"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update Item</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Append the modal to the body
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Populate the category dropdown
            const editCategorySelect = document.getElementById('editItemCategory');
            categoriesData.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                editCategorySelect.appendChild(option);
            });
            
            // Add event listener to the form
            document.getElementById('editItemForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const id = document.getElementById('editItemId').value;
                const updatedItem = {
                    name: document.getElementById('editItemName').value,
                    category_id: parseInt(document.getElementById('editItemCategory').value),
                    quantity: parseInt(document.getElementById('editItemQuantity').value),
                    min_quantity: parseInt(document.getElementById('editItemMinQuantity').value),
                    location: document.getElementById('editItemLocation').value,
                    description: document.getElementById('editItemDescription').value
                };
                
                updateInventoryItem(id, updatedItem);
            });
            
            editModal = document.getElementById('editItemModal');
        }
        
        // Fill the form with item data
        document.getElementById('editItemId').value = item.id;
        document.getElementById('editItemName').value = item.name;
        document.getElementById('editItemCategory').value = item.category_id;
        document.getElementById('editItemQuantity').value = item.quantity;
        document.getElementById('editItemMinQuantity').value = item.min_quantity || 5;
        document.getElementById('editItemLocation').value = item.location || '';
        document.getElementById('editItemDescription').value = item.description || '';
        
        // Show the modal
        const modal = new bootstrap.Modal(editModal);
        modal.show();
    }
    
    // Function to confirm deletion of an item
    function confirmDelete(id) {
        if (confirm('Are you sure you want to delete this item?')) {
            deleteInventoryItem(id);
        }
    }
    
    // Function to open the request modal for an item
    function openRequestModal(id) {
        const item = inventoryData.find(item => item.id == id);
        if (!item) return;
        
        // Fill the request form with item data
        document.getElementById('requestItemName').value = item.name;
        document.getElementById('requestQuantity').value = 1;
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('requestModal'));
        modal.show();
    }

    function renderInventoryTable(data) {
        const tableBody = document.getElementById('inventoryTable');
        tableBody.innerHTML = '';

        // Sort data by ID in ascending order
        const sortedData = [...data].sort((a, b) => a.id - b.id);

        sortedData.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.id}</td>
                <td>${item.name}</td>
                <td><span class="badge bg-category">${item.category}</span></td>
                <td class="${item.quantity < (item.min_quantity || 5) ? 'text-danger fw-bold' : ''}">${item.quantity}</td>
                <td><span class="badge ${getStatusBadgeClass(item.status)}">${item.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary edit-btn me-1" data-id="${item.id}">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-btn me-1" data-id="${item.id}">
                        <i class="bi bi-trash"></i>
                    </button>
                    <button class="btn btn-sm btn-hfc-blue request-btn" data-id="${item.id}">
                        <i class="bi bi-arrow-left-right"></i>
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });

        document.querySelectorAll('.bg-category').forEach(badge => {
            const category = badge.textContent;
            badge.classList.add(getCategoryColorClass(category));
        });
    }

    function getStatusBadgeClass(status) {
        switch (status) {
            case 'In Stock': return 'bg-success';
            case 'Low Stock': return 'bg-warning text-dark';
            case 'Out of Stock': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }

    function getCategoryColorClass(category) {
        const colors = {
            'Books': 'bg-info',
            'Electronics': 'bg-primary',
            'Worship': 'bg-purple',
            'Furniture': 'bg-brown',
            'Music': 'bg-pink'
        };
        return colors[category] || 'bg-secondary';
    }

    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alertDiv.style.zIndex = '9999';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        setTimeout(() => {
            alertDiv.classList.remove('show');
            setTimeout(() => alertDiv.remove(), 150);
        }, 3000);
    }
});
