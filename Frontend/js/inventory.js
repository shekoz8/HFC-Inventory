document.addEventListener('DOMContentLoaded', function () {

    // Load navbar
    fetch('/hfc_inventory/Frontend/partials/navbar.php')
        .then(response => {
            if (!response.ok) throw new Error('Failed to load navbar');
            return response.text();
        })
        .then(html => {
            document.getElementById('navbarContainer').innerHTML = html;
            if (user.name) {
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

    // Dummy data fallback
    const inventoryData = JSON.parse(localStorage.getItem('inventoryData')) || [
        { id: 1, name: "Bible", category: "Books", quantity: 50, status: "In Stock" },
        { id: 2, name: "Microphone", category: "Electronics", quantity: 10, status: "In Stock" },
        { id: 3, name: "Communion Cup", category: "Worship", quantity: 200, status: "In Stock" },
        { id: 4, name: "Hymn Book", category: "Books", quantity: 30, status: "In Stock" }
    ];

    renderInventoryTable(inventoryData);

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
            id: inventoryData.length + 1,
            name: document.getElementById('itemName').value,
            category: document.getElementById('itemCategory').value,
            quantity: parseInt(document.getElementById('itemQuantity').value),
        };
        newItem.status = newItem.quantity < 5 ? 'Low Stock' : 'In Stock';

        inventoryData.unshift(newItem);
        localStorage.setItem('inventoryData', JSON.stringify(inventoryData));
        renderInventoryTable(inventoryData);
        this.reset();

        bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
        showAlert('Item added successfully!', 'success');
    });

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
