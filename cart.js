// cart.js (Corrected and updated version)

let cart = {};

// Regular Cart Functions
document.addEventListener('DOMContentLoaded', () => {
    const savedCart = localStorage.getItem('shoppingCart');
    if (savedCart) {
        cart = JSON.parse(savedCart);
    }
    
    renderCart();
});

function saveCart() {
    localStorage.setItem('shoppingCart', JSON.stringify(cart));
}

function addToCart(name, price, image) {
    const id = name.replace(/\s/g, '');
    if (cart[id]) {
        cart[id].qty++;
    } else {
        cart[id] = {
            name: name,
            price: price,
            image: image,
            qty: 1
        };
    }
    saveCart();
    alert(name + ' added to cart!');
}

function renderCart() {
    const cartContainer = document.querySelector('.cart-container');
    if (!cartContainer) return;

    const cartItems = document.getElementById('cart-items');
    if (!cartItems) return;

    if (Object.keys(cart).length === 0) {
        cartItems.innerHTML = `<p>Your cart is empty.</p><a href="index.html" class="btn btn-primary">Return to Shop</a>`;
        const grandTotalSpan = document.getElementById('grand-total');
        if (grandTotalSpan) {
            grandTotalSpan.textContent = '$0.00';
        }
        return;
    }

    cartItems.innerHTML = '';
    let grandTotal = 0;
    for (const id in cart) {
        const item = cart[id];
        const itemTotal = item.price * item.qty;
        grandTotal += itemTotal;
        const cartItemDiv = document.createElement('div');
        cartItemDiv.className = 'cart-item';
        cartItemDiv.dataset.id = id;
        cartItemDiv.innerHTML = `
            <div><input type="checkbox"></div>
            <div><img src="${item.image}" alt="${item.name}" style="max-width: 100px;"></div>
            <div class="description"><p><strong>${item.name}</strong></p></div>
            <div class="price">$${item.price.toFixed(2)}</div>
            <div class="qty"><input type="number" value="${item.qty}" min="1" class="item-qty"></div>
            <div class="total">$${itemTotal.toFixed(2)}</div>`;
        cartItems.appendChild(cartItemDiv);
    }

    const grandTotalSpan = document.getElementById('grand-total');
    if (grandTotalSpan) {
        grandTotalSpan.textContent = '$' + grandTotal.toFixed(2);
    }
}

function updateCartFromPage() {
    const qtyInputs = document.querySelectorAll('.cart-item .item-qty');
    qtyInputs.forEach(input => {
        const id = input.closest('.cart-item').dataset.id;
        const newQty = parseInt(input.value);
        if (newQty > 0) {
            cart[id].qty = newQty;
        } else {
            alert('Quantity must be at least 1.');
            input.value = cart[id].qty;
        }
    });
    saveCart();
    renderCart();
}

function removeItems() {
    const checkboxes = document.querySelectorAll('.cart-item input[type="checkbox"]:checked');
    if (checkboxes.length === 0) {
        alert('Please select items to remove.');
        return;
    }
    checkboxes.forEach(checkbox => {
        const itemElement = checkbox.closest('.cart-item');
        const id = itemElement.dataset.id;
        delete cart[id];
    });
    saveCart();
    renderCart();
}

function checkout() {
    if (Object.keys(cart).length === 0) {
        alert('Your cart is empty.');
        return;
    }
    // This function simply redirects to the billing page.
    window.location.href = 'billing_info.html';
}