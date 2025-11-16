// Modal functionality
const modal = document.getElementById("addItemModal");
const addBtn = document.querySelector(".filter-group button");
const span = document.getElementsByClassName("close")[0];

addBtn.onclick = function() {
    modal.style.display = "block";
}

span.onclick = function() {
    modal.style.display = "none";
}

window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

// Category filtering
document.querySelectorAll('.category-filter').forEach(filter => {
    filter.addEventListener('click', function() {
        // Remove active class from all filters
        document.querySelectorAll('.category-filter').forEach(f => {
            f.classList.remove('active');
        });
        
        // Add active class to clicked filter
        this.classList.add('active');
        
        const category = this.textContent.split(' ')[0].toLowerCase();
        
        // Show all rows first
        document.querySelectorAll('.menu-table tbody tr').forEach(row => {
            row.style.display = '';
        });
        
        // If not "all", filter by category
        if (category !== 'all') {
            document.querySelectorAll('.menu-table tbody tr').forEach(row => {
                const rowCategory = row.querySelector('.menu-item-category').textContent.toLowerCase();
                if (!rowCategory.includes(category)) {
                    row.style.display = 'none';
                }
            });
        }
    });
});

// Search functionality
document.querySelector('.search-box input').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    
    document.querySelectorAll('.menu-table tbody tr').forEach(row => {
        const itemName = row.querySelector('.menu-item-name').textContent.toLowerCase();
        const itemDesc = row.querySelector('.menu-item-description').textContent.toLowerCase();
        
        if (itemName.includes(searchTerm) || itemDesc.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});