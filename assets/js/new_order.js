document.addEventListener('DOMContentLoaded', function () {
    // Modal functionality
    const modal = document.getElementById("addItemModal");
    const btn = document.querySelector(".dropbtn");
    const span = document.getElementsByClassName("close")[0];

    btn.onclick = function () {
        modal.style.display = "block";
        loadMenuItems(); // Load menu items when modal opens
    }

    span.onclick = function () {
        modal.style.display = "none";
    }

    window.onclick = function (event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Search functionality
    document.getElementById('menuSearch').addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase();
        const items = document.querySelectorAll('.menu-item');

        items.forEach(item => {
            const name = item.getAttribute('data-name').toLowerCase();
            if (name.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Load menu items from server
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

                    // Fix image path for cashier directory
                    let imagePath = item.image;
                    if (imagePath && imagePath.startsWith('../../assets/')) {
                        imagePath = '../' + imagePath.substring(2); // Remove ./ and add ../
                    }

                    itemElement.innerHTML = `
                        <div class="item-image">
                            ${imagePath ? `<img src="${imagePath}" alt="${item.name}" style="max-width:50px;max-height:50px;object-fit:cover;border-radius:5px;">` : `<div style='width:50px;height:50px;background:#eee;display:flex;align-items:center;justify-content:center;border-radius:5px;'><i class='fa fa-image' style='color:#ccc;'></i></div>`}
                        </div>
                        <div class="item-name">${item.name}</div>
                        <div class="item-price">₵${Number(item.price).toFixed(2)}</div>
                        <button class="add-to-order">Add</button>
                    `;

                    itemElement.querySelector('.add-to-order').addEventListener('click', function () {
                        addToOrder(item.id, item.name, item.price);
                    });

                    container.appendChild(itemElement);
                });
            });
    }

    // Add item to order
    function addToOrder(id, name, price) {
        // Check if item already exists in order
        const existingRow = document.querySelector(`#orderItems tr[data-id="${id}"]`);

        if (existingRow) {
            // Increase quantity
            const qtyInput = existingRow.querySelector('.quantity');
            qtyInput.value = parseInt(qtyInput.value) + 1;
            updateRowTotal(existingRow);
        } else {
            // Add new row
            const tbody = document.getElementById('orderItems');

            // Remove "no items" message if it exists
            if (tbody.querySelector('td[colspan="5"]')) {
                tbody.innerHTML = '';
            }

            const row = document.createElement('tr');
            row.setAttribute('data-id', id);
            row.innerHTML = `
                <td>${name}</td>
                <td class="price">₵${Number(price).toFixed(2)}</td>
                <td><input type="number" class="quantity" value="1" min="1"></td>
                <td class="total">₵${Number(price).toFixed(2)}</td>
                <td><button class="remove-item"><i class="fa-solid fa-trash"></button></td>
            `;

            // Add event listeners
            row.querySelector('.quantity').addEventListener('change', function () {
                updateRowTotal(row);
            });

            row.querySelector('.remove-item').addEventListener('click', function () {
                row.remove();
                if (document.getElementById('orderItems').children.length === 0) {
                    document.getElementById('orderItems').innerHTML = `
                        <tr>
                            <td colspan="5">
                                        <div style=" display: flex; justify-content: center; align-items: center; flex-direction: column; gap: 10px; color:#ddd">
    <i class="fas  fa-clipboard-list style" style=" font-size: 2rem;"></i>  </div>
                                       <h3 style="font-size: 1.2rem; color:#afb3af; text-align: center;"> No items added</h3>
                                    </td>
                        </tr>
                    `;
                }
                updateOrderSummary();
            });

            tbody.appendChild(row);
        }

        updateOrderSummary();
        modal.style.display = "none";
    }

    function updateRowTotal(row) {
        const price = parseFloat(row.querySelector('.price').textContent.replace('₵', ''));
        const quantity = parseInt(row.querySelector('.quantity').value);
        const totalCell = row.querySelector('.total');
        totalCell.textContent = '₵' + (price * quantity).toFixed(2);
        updateOrderSummary();
    }

    function updateOrderSummary() {
        let subtotal = 0;

        document.querySelectorAll('#orderItems tr[data-id]').forEach(row => {
            subtotal += parseFloat(row.querySelector('.total').textContent.replace('₵', ''));
        });

        // Calculate taxes (adjust percentages as needed)
        const gtLevy = subtotal * 0.01;
        const nhil = subtotal * 0.025;
        const gfLevy = subtotal * 0.025;
        const vat = subtotal * 0.125;
        const total = subtotal + gtLevy + nhil + gfLevy + vat;

        // Update summary
        document.getElementById('subtotal').textContent = '₵' + subtotal.toFixed(2);
        document.querySelectorAll('#tax')[0].textContent = '₵' + gtLevy.toFixed(2);
        document.querySelectorAll('#tax')[1].textContent = '₵' + nhil.toFixed(2);
        document.querySelectorAll('#tax')[2].textContent = '₵' + gfLevy.toFixed(2);
        document.querySelectorAll('#tax')[3].textContent = '₵' + vat.toFixed(2);
        document.getElementById('total').textContent = '₵' + total.toFixed(2);
    }
});

// Add this to your existing JavaScript
document.querySelector('.submitBtn').addEventListener('click', function () {
    const orderType = document.getElementById('orderType').value;
    const waiterId = document.getElementById('waiter').value;
    const tableNumber = document.querySelector('input[type="text"]').value;
    const paymentMethod = document.querySelector('input[name="payment-method"]:checked')?.value || 'cash';

    // Collect order items
    const items = [];
    document.querySelectorAll('#orderItems tr[data-id]').forEach(row => {
        items.push({
            id: row.getAttribute('data-id'),
            name: row.querySelector('td:first-child').textContent,
            price: parseFloat(row.querySelector('.price').textContent.replace('₵', '')),
            quantity: parseInt(row.querySelector('.quantity').value),
            total: parseFloat(row.querySelector('.total').textContent.replace('₵', ''))
        });
    });

    if (items.length === 0) {
        alert('Please add at least one item to the order');
        return;
    }

    // Calculate totals
    const subtotal = parseFloat(document.getElementById('subtotal').textContent.replace('₵', ''));
    const gtLevy = parseFloat(document.querySelectorAll('#tax')[0].textContent.replace('₵', ''));
    const nhil = parseFloat(document.querySelectorAll('#tax')[1].textContent.replace('₵', ''));
    const gfLevy = parseFloat(document.querySelectorAll('#tax')[2].textContent.replace('₵', ''));
    const vat = parseFloat(document.querySelectorAll('#tax')[3].textContent.replace('₵', ''));
    const total = parseFloat(document.getElementById('total').textContent.replace('₵', ''));

    // Prepare data for submission
    const orderData = {
        order_type: orderType,
        waiter_id: waiterId,
        table_number: tableNumber,
        payment_method: paymentMethod,
        subtotal: subtotal,
        gt_levy: gtLevy,
        nhil: nhil,
        gf_levy: gfLevy,
        vat: vat,
        total: total,
        items: items
    };

    // Submit to server
    fetch('../includes/submit_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(orderData)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Order submitted successfully!');
                // Reset form or redirect as needed
                window.location.reload();
            } else {
                alert('Error submitting order: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to submit order');
        });
});

// for printing the order receipt
function printOrderReceipt() {
    // Get order data
    const orderNumber = document.getElementById('orderNumber').value;
    const orderType = document.getElementById('orderType').value;
    const date = new Date().toLocaleString();
    const paymentMethod = document.querySelector('input[name="payment-method"]:checked')?.value || 'cash';

    // Get order items
    const items = [];
    document.querySelectorAll('#orderItems tr[data-id]').forEach(row => {
        items.push({
            name: row.querySelector('td:first-child').textContent,
            price: row.querySelector('.price').textContent,
            quantity: row.querySelector('.quantity').value,
            total: row.querySelector('.total').textContent
        });
    });

    // Get totals
    const subtotal = document.getElementById('subtotal').textContent;
    const gtLevy = document.querySelectorAll('#tax')[0].textContent;
    const nhil = document.querySelectorAll('#tax')[1].textContent;
    const gfLevy = document.querySelectorAll('#tax')[2].textContent;
    const vat = document.querySelectorAll('#tax')[3].textContent;
    const total = document.getElementById('total').textContent;

    // Create print window
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Order Receipt #${orderNumber}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .receipt { width: 80mm; margin: 0 auto; }
                .header { text-align: center; margin-bottom: 10px; }
                .title { font-size: 18px; font-weight: bold; }
                .order-info { margin-bottom: 15px; }
                .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                .items-table th { text-align: left; border-bottom: 1px dashed #000; padding: 5px 0; }
                .items-table td { padding: 3px 0; }
                .total-row { font-weight: bold; border-top: 1px dashed #000; }
                .footer { text-align: center; margin-top: 15px; font-size: 12px; }
                .totals-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.totals-table td {
    padding: 2px 0;
    font-size: 14px;
}
.total-row {
    border-top: 1px dashed #000;
    font-weight: bold;
    padding-top: 5px;
}

                @media print {
                    body { width: 80mm; }
                    button { display: none; }
                }
                    .items-table th, .items-table td {
    font-size: 12px;
    padding: 2px 0;
    text-align: left;
}

.items-table td:nth-child(1) { width: 50%; }
.items-table td:nth-child(2) { width: 20%; text-align: center; }
.items-table td:nth-child(3) { width: 30%; text-align: right; }

            </style>
        </head>
        <body>
            <div class="receipt">
                <div class="header">
                    <div class="title">MEGAMIND RESTAURANT</div>
                    <div>Kumasi, Ghana</div>
                    <div>Tel: (233) 54 522 1112</div>
                </div>
                
                <div class="order-info">
                    <div><strong>Order #:</strong> ${orderNumber}</div>
                    <div><strong>Date:</strong> ${date}</div>
                    <div><strong>Type:</strong> ${orderType === 'dine-in' ? 'Dine-In' : 'Take Away'}</div>
                    ${orderType === 'dine-in' ? `<div><strong>Table:</strong> ${document.getElementById('tableNumber').value || 'N/A'}</div>` : ''}
                    <div><strong>Waiter:</strong> ${document.getElementById('waiter').value || 'N/A'}</div>
                </div>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(item => `
                            <tr>
                                <td>${item.name}</td>
                                <td>${item.quantity}</td>
                                <td>${item.total}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                <hr style="margin: 10px 0;">

              <table class="totals-table">
    <tr><td>Subtotal:</td><td style="text-align: right;">${subtotal}</td></tr>
    <tr><td>GT Levy (1%):</td><td style="text-align: right;">${gtLevy}</td></tr>
    <tr><td>NHIL (2.5%):</td><td style="text-align: right;">${nhil}</td></tr>
    <tr><td>GF Levy (2.5%):</td><td style="text-align: right;">${gfLevy}</td></tr>
    <tr><td>VAT (12.5%):</td><td style="text-align: right;">${vat}</td></tr>
    <tr class="total-row"><td><strong>TOTAL:</strong></td><td style="text-align: right;"><strong>${total}</strong></td></tr>
</table>

                
                <div class="payment-method">
                    <div><strong>Payment Method:</strong> ${paymentMethod.toUpperCase()}</div>
                </div>
                
                
                
                <div class="footer">
                    Thank you for dining with us!<br>
                    Please come again
                </div>
            </div>
            
            <button onclick="window.print()" style="margin: 20px auto; display: block; padding: 10px 20px;">
                Print Receipt
            </button>
        </body>
        </html>
    `);
    printWindow.document.close();

    // Auto-print after a short delay (optional)
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 300);
}

// Add event listener to your print button
document.querySelector('.PrintOrder').addEventListener('click', function (e) {
    e.preventDefault();
    printOrderReceipt();
});