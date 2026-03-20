// 1. Initial Inventory
const manualItems = [
    { id: 1, name: "Crocin Advance 650", price: 60, img: "image_8bd4df.jpg" },
    { id: 2, name: "Dolo 650mg", price: 32, img: "https://via.placeholder.com/150?text=Dolo" },
    { id: 3, name: "Vicks Vaporub", price: 95, img: "https://via.placeholder.com/150?text=Vicks" }
];

const prefixes = ["Amlon", "Telma", "Met-", "Pan-", "Omee-", "Cef-", "Azith-", "Lev-", "Neuro", "Vita"];
const suffixes = [" 40", " D", " 500", " 250", " L", " XT", " SR", " Forte", " Plus", " Gold"];

let inventory = [...manualItems];
for (let i = 4; i <= 55; i++) {
    inventory.push({
        id: i,
        name: prefixes[Math.floor(Math.random() * prefixes.length)] + suffixes[Math.floor(Math.random() * suffixes.length)],
        price: Math.floor(Math.random() * 400) + 15,
        img: "https://via.placeholder.com/150?text=Medicine"
    });
}

let cart = [];

function loadProducts(data = inventory) {
    const grid = document.getElementById('medicine-grid');
    grid.innerHTML = data.map(item => `
        <div class="med-card">
            <img src="${item.img}" alt="${item.name}" onerror="this.src='https://via.placeholder.com/150?text=Med'">
            <h4>${item.name}</h4>
            <p style="color:var(--primary); font-weight:700;">₹${item.price}</p>
            <button class="add-btn" onclick="addToCart(${item.id})">Add to Cart</button>
        </div>
    `).join('');
}

function filterProducts() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();
    const filtered = inventory.filter(item => item.name.toLowerCase().includes(searchTerm));
    loadProducts(filtered);
}

// Logic to handle +1, +2 grouping
function addToCart(id) {
    const existingItem = cart.find(item => item.id === id);
    if (existingItem) {
        if (existingItem.qty >= 99) { alert('Maximum 99 units per item allowed.'); return; }
        existingItem.qty += 1;
    } else {
        const product = inventory.find(p => p.id === id);
        cart.push({...product, qty: 1});
    }
    updateUI();
    document.getElementById('cart-panel').classList.add('active');
}

function changeQty(id, delta) {
    const item = cart.find(item => item.id === id);
    if (!item) return;
    const newQty = item.qty + delta;
    if (newQty > 99) { alert('Maximum 99 units per item allowed.'); return; }
    item.qty = newQty;
    if (item.qty <= 0) {
        cart = cart.filter(i => i.id !== id);
    }
    updateUI();
}

function updateUI() {
    // Total count of all items
    document.getElementById('cart-count').innerText = cart.reduce((sum, item) => sum + item.qty, 0);
    const list = document.getElementById('cart-items');
    
    let subtotal = 0;
    list.innerHTML = cart.map((item) => {
        const itemTotal = item.price * item.qty;
        subtotal += itemTotal;
        return `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <div style="flex: 1;">
                    <strong>${item.name}</strong><br>
                    <small>₹${item.price} x ${item.qty}</small>
                </div>
                <div class="qty-controls">
                    <button class="qty-btn" onclick="changeQty(${item.id}, -1)">-</button>
                    <span>${item.qty}</span>
                    <button class="qty-btn" onclick="changeQty(${item.id}, 1)">+</button>
                </div>
            </div>
        `;
    }).join('');

    const deliveryCharge = cart.length > 0 ? 20 : 0;
    const taxRate = 0.06; 
    const cgstVal = subtotal * taxRate;
    const sgstVal = subtotal * taxRate;
    const grandTotal = subtotal + cgstVal + sgstVal + deliveryCharge;

    document.getElementById('items-total').innerText = subtotal.toFixed(2);
    document.getElementById('cgst').innerText = cgstVal.toFixed(2);
    document.getElementById('sgst').innerText = sgstVal.toFixed(2);
    document.getElementById('delivery-fee').innerText = deliveryCharge;
    document.getElementById('total-price').innerText = grandTotal.toFixed(2);
}

function toggleCart() { document.getElementById('cart-panel').classList.toggle('active'); }

function shuffleStore() {
    const stores = ["Somaiya Medical Store", "Apollo Pharmacy", "Noble Plus"];
    document.getElementById('store-display').innerText = stores[Math.floor(Math.random()*stores.length)];
}

function checkout() {
    if(cart.length === 0) return alert("Bhai, cart khali hai!");
    alert("Order Confirmed! Total Amount: ₹" + document.getElementById('total-price').innerText);
}

window.onload = () => { loadProducts(); shuffleStore(); };