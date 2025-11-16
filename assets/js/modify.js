
document.addEventListener('DOMContentLoaded', () => {
    // Tab switching
    document.getElementById('new-orders-tab').addEventListener('click', function() {
        document.getElementById('new-orders-list').style.display = 'block';
        document.getElementById('billed-orders-list').style.display = 'none';
        this.classList.add('active');
        document.getElementById('billed-orders-tab').classList.remove('active');
    });

    document.getElementById('billed-orders-tab').addEventListener('click', function() {
        document.getElementById('new-orders-list').style.display = 'none';
        document.getElementById('billed-orders-list').style.display = 'block';
        this.classList.add('active');
        document.getElementById('new-orders-tab').classList.remove('active');
    });

    // Order click: redirect to show details
    document.querySelectorAll('.order-card').forEach(card => {
        card.addEventListener('click', function() {
            const orderId = this.getAttribute('data-id');
            window.location.href = 'modify.php?order_id=' + orderId;
        });
    });

    // Modal setup
    const modal = document.getElementById("addItemModal");
    const viewFullMenuBtn = document.querySelector(".btn.btn-outline i.fa-plus")?.closest("button");
    const span = document.getElementsByClassName("close")[0];

    if (viewFullMenuBtn) {
        viewFullMenuBtn.onclick = function() {
            modal.style.display = "block";
            loadMenuItems();
        };
    }

    if (span) {
        span.onclick = function() {
            modal.style.display = "none";
        };
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    };

    // Search functionality in modal
    document.getElementById('menuSearch')?.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const items = document.querySelectorAll('.menu-item');

        items.forEach(item => {
            const name = item.getAttribute('data-name').toLowerCase();
            item.style.display = name.includes(searchTerm) ? 'block' : 'none';
        });
    });

    // Load menu items into modal
    function loadMenuItems() {
        fetch('../includes/get_menu_items.php')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('menuItemsContainer');
                container.innerHTML = '';

                data.forEach(item => {
                    const itemElement = document.createElement('div');
                    itemElement.className = 'menu-item';
                    itemElement.setAttribute('data-id', item.id);
                    itemElement.setAttribute('data-name', item.name);
                    itemElement.setAttribute('data-price', item.price);

                    itemElement.innerHTML = `
                    <div class="item-name">${item.name}</div>
                    <div class="item-price">₵${Number(item.price).toFixed(2)}</div>
                    <button class="add-to-order">Add</button>
                `;

                    itemElement.querySelector('.add-to-order').addEventListener('click', function() {
                        addToOrder(item.id, item.name, item.price);
                    });

                    container.appendChild(itemElement);
                });
            });
    }

    // Add item to order table
    function addToOrder(id, name, price) {
        const existingRow = document.querySelector(`.items-table tbody tr[data-id="${id}"]`);

        if (existingRow) {
            const quantityEl = existingRow.querySelector('.quantity');
            let quantity = parseInt(quantityEl.textContent);
            quantityEl.textContent = quantity + 1;
        } else {
            const tbody = document.querySelector('.items-table tbody');
            const row = document.createElement('tr');
            row.setAttribute('data-id', id);

            row.innerHTML = `
            <td>${name}</td>
            <td>
                <div class="quantity-control">
                    <button class="quantity-btn">-</button>
                    <span class="quantity">1</span>
                    <button class="quantity-btn">+</button>
                </div>
            </td>
            <td>₵${Number(price).toFixed(2)}</td>
            <td>₵${Number(price).toFixed(2)}</td>
        `;

            row.querySelectorAll('.quantity-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const isPlus = btn.textContent === '+';
                    const quantityEl = btn.parentElement.querySelector('.quantity');
                    let quantity = parseInt(quantityEl.textContent);

                    quantity = isPlus ? quantity + 1 : Math.max(1, quantity - 1);
                    quantityEl.textContent = quantity;

                    updateTotals();
                });
            });

            tbody.appendChild(row);
        }

        updateTotals();
    }

    // Attach click listeners to inline "Add Item" buttons
    document.querySelectorAll('.add-item-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const menuItem = this.closest('.menu-item');
            const name = menuItem.querySelector('h5').textContent.trim();
            const price = parseFloat(menuItem.querySelector('p').textContent.replace('₵', ''));

            addToOrder(id, name, price);
        });
    });

    const updateTotals = () => {
        let subtotal = 0;

        document.querySelectorAll('.items-table tbody tr').forEach(row => {
            const price = parseFloat(row.querySelector('td:nth-child(3)').textContent.replace('₵', ''));
            const quantity = parseInt(row.querySelector('.quantity').textContent);
            const total = price * quantity;

            row.querySelector('td:nth-child(4)').textContent = `₵${total.toFixed(2)}`;
            subtotal += total;
        });

        const gt_levy = subtotal * 0.01;
        const nhil = subtotal * 0.025;
        const gf_levy = subtotal * 0.025;
        const vat = subtotal * 0.125;
        const totalTax = gt_levy + nhil + gf_levy + vat;
        const total = subtotal + totalTax;

        const summaryRows = document.querySelectorAll('.summary-row');
        if (summaryRows.length >= 3) {
            summaryRows[0].querySelector('span:last-child').textContent = `₵${subtotal.toFixed(2)}`;
            summaryRows[1].querySelector('span:last-child').textContent = `₵${totalTax.toFixed(2)}`;
            summaryRows[2].querySelector('span:last-child').textContent = `₵${total.toFixed(2)}`;
        }
    };

    // Also attach listeners to pre-rendered quantity buttons
    document.querySelectorAll('.quantity-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const isPlus = btn.textContent === '+';
            const quantityEl = btn.parentElement.querySelector('.quantity');
            let quantity = parseInt(quantityEl.textContent);

            quantity = isPlus ? quantity + 1 : Math.max(1, quantity - 1);
            quantityEl.textContent = quantity;

            updateTotals();
        });
    });
    // Store initial state of order items
    let originalItems = [];

    function captureOriginalItems() {
        originalItems = [];
        document.querySelectorAll('.items-table tbody tr').forEach(row => {
            const id = row.getAttribute('data-id');
            const name = row.querySelector('td:nth-child(1)').textContent;
            const quantity = parseInt(row.querySelector('.quantity').textContent);
            const price = parseFloat(row.querySelector('td:nth-child(3)').textContent.replace('₵', ''));
            originalItems.push({
                id,
                name,
                quantity,
                price
            });
        });
    }

    function restoreOriginalItems() {
        const tbody = document.querySelector('.items-table tbody');
        tbody.innerHTML = ''; // Clear table first
        originalItems.forEach(item => {
            const row = document.createElement('tr');
            row.setAttribute('data-id', item.id);
            row.innerHTML = `
    <td>${item.name}</td>
    <td>
        <div class="quantity-control">
            <button class="quantity-btn">-</button>
            <span class="quantity">${item.quantity}</span>
            <button class="quantity-btn">+</button>
        </div>
    </td>
    <td>₵${item.price.toFixed(2)}</td>
    <td>₵${(item.quantity * item.price).toFixed(2)}</td>
`;

            // Re-bind + / - buttons
            row.querySelectorAll('.quantity-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const isPlus = btn.textContent === '+';
                    const quantityEl = btn.parentElement.querySelector('.quantity');
                    let quantity = parseInt(quantityEl.textContent);
                    quantity = isPlus ? quantity + 1 : Math.max(1, quantity - 1);
                    quantityEl.textContent = quantity;
                    updateTotals();
                });
            });

            tbody.appendChild(row);
        });

        updateTotals();
    }

    // Capture initial state on load
    captureOriginalItems();

    // Discard Button Action
    document.getElementById('discardChangesBtn')?.addEventListener('click', () => {
        restoreOriginalItems();
    });

    // Save Button Action
    document.getElementById('saveChangesBtn')?.addEventListener('click', () => {
        const items = [];
        document.querySelectorAll('.items-table tbody tr').forEach(row => {
            const id = row.getAttribute('data-id');
            const name = row.querySelector('td:nth-child(1)').textContent;
            const price = parseFloat(row.querySelector('td:nth-child(3)').textContent.replace('₵', ''));
            const quantity = parseInt(row.querySelector('.quantity').textContent);
            items.push({
                id,
                name,
                price,
                quantity
            });
        });

        const orderId = window.selectedOrderId;

        if (!orderId) {
            alert('No order selected. Please select an order to modify.');
            return;
        }

        const formData = new FormData();
        formData.append('save_changes', true);
        formData.append('order_id', orderId);
        formData.append('items', JSON.stringify(items));

        fetch('modify.php', {
            method: 'POST',
            body: formData
        }).then(res => {
            if (res.redirected) {
                window.location.href = res.url; // reload properly
            } else {
                alert('Order updated.');
                captureOriginalItems();  // Refresh backup after saving
                clearOrderDetails();    // <--- This clears the UI

            }

        }).catch(err => {
            alert('Error saving changes');
            console.error(err);
        });
    });

    function clearOrderDetails() {
        const orderDetailsSection = document.querySelector('.main-content');
        const modifySection = document.querySelector('.modify-order');
    
        if (orderDetailsSection) orderDetailsSection.innerHTML = '<div class="alert alert-info">No order selected or order not found.</div>';
        if (modifySection) modifySection.innerHTML = ''; // Clears the modify panel
    }
    


});
