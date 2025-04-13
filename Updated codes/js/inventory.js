document.addEventListener('DOMContentLoaded', function() {
    // Check authentication
    if (!localStorage.getItem('user')) {
        window.location.href = 'index.html';
        return;
    }

    // Load navbar
    fetch('/partials/navbar.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('navbarContainer').innerHTML = data;
            
            // Set user name
            const user = JSON.parse(localStorage.getItem('user'));
            if (user) {
                document.getElementById('userName').textContent = user.name.split(' ')[0];
            }
        });

    // Sample inventory data
    const inventoryData = [
        { id: 1, name: "Bible", category: "Books", quantity: 50, status: "In Stock" },
        { id: 2, name: "Microphone", category: "Electronics", quantity: 10, status: "In Stock" },
        { id: 3, name: "Communion Cup", category: "Worship", quantity: 200, status: "In Stock" },
        { id: 4, name: "Projector", category: "Electronics", quantity: 2, status: "Low Stock" },
        { id: 5, name: "Hymn Book", category: "Books", quantity: 30, status: "In Stock" }
    ];

    // Render inventory table
    function renderInventoryTable(data) {
        const tableBody = document.getElementById('inventoryTable');
        tableBody.innerHTML = '';

        data.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.id}</td>
                <td>${item.name}</td>
                <td><span class="badge bg-category">${item.category}</span></td>
                <td class="${item.quantity < 5 ? 'text-danger fw-bold' : ''}">${item.quantity}</td>
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

        // Add category background colors
        document.querySelectorAll('.bg-category').forEach(badge => {
            const category = badge.textContent;
            badge.classList.add(getCategoryColorClass(category));
        });
    }

    fetch('./partials/navbar.html')
    .then(response => {
        console.log('Navbar path:', response.url); // Debug path
        if (!response.ok) throw new Error('Failed to load navbar');
        return response.text();
    })
    .then(html => {
        console.log('Navbar content loaded'); // Debug loading
        document.getElementById('navbarContainer').innerHTML = html;
    })
    .catch(err => {
        console.error('Navbar error:', err);
        // Fallback navbar with absolute logo path
        document.getElementById('navbarContainer').innerHTML = `
            <nav class="navbar navbar-dark bg-primary">
                <div class="container">
                    <a class="navbar-brand" href="#">
                        <img src="/images/HFC-logo.png" alt="HFC" height="40">
                        HFC Inventory System
                    </a>
                </div>
            </nav>
        `;
    });

    // Get status badge class
    function getStatusBadgeClass(status) {
        switch(status) {
            case 'In Stock': return 'bg-success';
            case 'Low Stock': return 'bg-warning text-dark';
            case 'Out of Stock': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }

    // Get category color class
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

    // Initialize the table
    renderInventoryTable(inventoryData);

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const filteredData = inventoryData.filter(item => 
            item.name.toLowerCase().includes(searchTerm) || 
            item.category.toLowerCase().includes(searchTerm)
        );
        renderInventoryTable(filteredData);
    });

    // Category filter
    document.getElementById('categoryFilter').addEventListener('change', function() {
        const category = this.value;
        if (category === 'all') {
            renderInventoryTable(inventoryData);
        } else {
            const filteredData = inventoryData.filter(item => item.category === category);
            renderInventoryTable(filteredData);
        }
    });

    // Add item form
    document.getElementById('addItemForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const newItem = {
            id: inventoryData.length + 1,
            name: document.getElementById('itemName').value,
            category: document.getElementById('itemCategory').value,
            quantity: parseInt(document.getElementById('itemQuantity').value),
            status: parseInt(document.getElementById('itemQuantity').value) < 5 ? 'Low Stock' : 'In Stock'
        };
        
        inventoryData.unshift(newItem);
        renderInventoryTable(inventoryData);
        this.reset();
        
        // Hide modal
        bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
        
        // Show success message
        showAlert('Item added successfully!', 'success');
    });

    // Edit button click
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-btn')) {
            const itemId = parseInt(e.target.closest('.edit-btn').dataset.id);
            const item = inventoryData.find(item => item.id === itemId);
            
            if (item) {
                document.getElementById('editItemName').value = item.name;
                document.getElementById('editItemCategory').value = item.category;
                document.getElementById('editItemQuantity').value = item.quantity;
                document.getElementById('editItemId').value = item.id;
                
                const editModal = new bootstrap.Modal(document.getElementById('editItemModal'));
                editModal.show();
            }
        }
        
        // Delete button click
        if (e.target.closest('.delete-btn')) {
            if (confirm('Are you sure you want to delete this item?')) {
                const itemId = parseInt(e.target.closest('.delete-btn').dataset.id);
                const index = inventoryData.findIndex(item => item.id === itemId);
                
                if (index !== -1) {
                    inventoryData.splice(index, 1);
                    renderInventoryTable(inventoryData);
                    showAlert('Item deleted successfully!', 'success');
                }
            }
        }
    });

    // Edit form submission
    document.getElementById('editItemForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const itemId = parseInt(document.getElementById('editItemId').value);
        const item = inventoryData.find(item => item.id === itemId);
        
        if (item) {
            item.name = document.getElementById('editItemName').value;
            item.category = document.getElementById('editItemCategory').value;
            item.quantity = parseInt(document.getElementById('editItemQuantity').value);
            item.status = item.quantity < 5 ? 'Low Stock' : 'In Stock';
            
            renderInventoryTable(inventoryData);
            bootstrap.Modal.getInstance(document.getElementById('editItemModal')).hide();
            showAlert('Item updated successfully!', 'success');
        }
    });

    // Show alert function
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