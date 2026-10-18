<style>
/* Cart Sidebar Styles */
.cart-sidebar { position: fixed; top: 0; right: -420px; width: 400px; height: 100vh; background: #fffefc; box-shadow: -8px 0 40px rgba(0,0,0,0.1); transition: right 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1), visibility 0.4s; z-index: 1100; display: flex; flex-direction: column; border-left: 2px solid #f3e1d2; visibility: hidden; }
.cart-sidebar.open { right: 0; visibility: visible; }
.cart-header { padding: 24px; border-bottom: 2px solid #f2e5d9; display: flex; justify-content: space-between; align-items: center; }
.cart-header h3 { font-size: 1.5rem; color: #3b2a1d; display: flex; gap: 10px; margin: 0;}
.close-cart { font-size: 1.6rem; color: #a67b60; cursor: pointer; background: none; border: none; }
.cart-items { flex: 1; overflow-y: auto; padding: 20px; }
.empty-cart { text-align: center; margin-top: 60px; color: #b99278; }
.cart-item { display: flex; gap: 14px; margin-bottom: 24px; padding-bottom: 18px; border-bottom: 1px solid #f2e5d9; align-items: center; }
.cart-item-image { width: 44px; height: 44px; background: #fdf8f4; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; font-size: 1.5rem; }
.cart-item-details { flex: 1; }
.cart-item-name { font-weight: 700; margin-bottom: 4px; font-size: 0.9rem; }
.cart-item-price { color: #b45f2b; font-weight: 600; font-size: 0.85rem; }
.cart-item-actions { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
.cart-item-actions button { background: #f3eee9; width: 30px; height: 30px; border-radius: 40px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; color: #333; }
.cart-item-actions button:hover { background: #e5d5c4; }
.cart-footer { padding: 24px; border-top: 2px solid #f2e5d9; }
.cart-total { display: flex; justify-content: space-between; font-weight: 800; font-size: 1.3rem; margin-bottom: 20px; }
.checkout-btn, .continue-shopping { width: 100%; padding: 14px; border-radius: 50px; font-weight: 700; margin-bottom: 12px; border: none; cursor: pointer; font-size: 1rem; }
.checkout-btn { background: #b45f2b; color: white; }
.continue-shopping { background: none; border: 1px solid #e6d9cd; color: #5e4330; }
.cart-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(3px); z-index: 1050; display: none; }
.cart-overlay.active { display: block; }
@media (max-width: 640px) { .cart-sidebar { width: 100%; right: -100%; } }
</style>
<div class="cart-sidebar" id="globalCartSidebar">
  <div class="cart-header">
    <h3><i class="fas fa-shopping-cart"></i> Your Cart</h3>
    <button class="close-cart" id="globalCloseCart" onclick="closeCartDrawer()"><i class="fas fa-times"></i></button>
  </div>
  <div class="cart-items" id="globalCartItems"></div>
  <div class="cart-footer">
    <div class="cart-total"><span>Total</span><span class="total-price" id="globalCartTotal">₹0</span></div>
    <button class="checkout-btn" onclick="window.location.href='../checkout/checkout.php'">Proceed to Checkout</button>
    <button class="continue-shopping" onclick="closeCartDrawer()">Continue Shopping</button>
  </div>
</div>
<div class="cart-overlay" id="globalCartOverlay" onclick="closeCartDrawer()"></div>

<script>
let globalCart = [];

function loadGlobalCart() {
  fetch('../index.php?ajax=cart')
    .then(res => res.text())
    .then(text => {
      let data;
      try {
        const jsonStart = text.indexOf('{');
        const arrayStart = text.indexOf('[');
        const start = (jsonStart !== -1 && arrayStart !== -1) ? Math.min(jsonStart, arrayStart) : (jsonStart !== -1 ? jsonStart : arrayStart);
        if(start !== -1) data = JSON.parse(text.substring(start));
      } catch(e) { return; }
      globalCart = Array.isArray(data) ? data : (data ? Object.values(data) : []);
      if (typeof window.cart !== 'undefined') window.cart = globalCart;
      renderGlobalCartItems();
    })
    .catch(console.error);
}

function openCartDrawer() {
  const sidebar = document.getElementById('globalCartSidebar');
  const overlay = document.getElementById('globalCartOverlay');
  if (sidebar) sidebar.classList.add('open');
  if (overlay) overlay.classList.add('active');
  document.body.style.overflow = 'hidden';
  loadGlobalCart();
}

function closeCartDrawer() {
  const sidebar = document.getElementById('globalCartSidebar');
  const overlay = document.getElementById('globalCartOverlay');
  if (sidebar) sidebar.classList.remove('open');
  if (overlay) overlay.classList.remove('active');
  document.body.style.overflow = '';
}

// Fallback aliases for other existing localized scripts
window.openCart = openCartDrawer;
window.closeCart = closeCartDrawer;
window.triggerCartDrawer = openCartDrawer;

function updateGlobalCartQty(productId, qty) {
  const formData = new FormData();
  formData.append('id', productId);
  formData.append('qty', qty);
  fetch('../index.php?ajax=update_cart', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(text => {
      let data;
      try {
        const jsonStart = text.indexOf('{');
        if(jsonStart !== -1) data = JSON.parse(text.substring(jsonStart));
      } catch(e) { return; }
      if (data && data.success) {
        globalCart = Array.isArray(data.cart) ? data.cart : Object.values(data.cart || {});
        if (typeof window.cart !== 'undefined') window.cart = globalCart;
        updateAllCartBadges();
        renderGlobalCartItems();
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('karavalliCartWeightExtended', JSON.stringify(globalCart));
        }
      }
    })
    .catch(console.error);
}

function removeGlobalCartItem(productId) {
  const formData = new FormData();
  formData.append('id', productId);
  fetch('../index.php?ajax=remove_cart', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(text => {
      let data;
      try {
        const jsonStart = text.indexOf('{');
        if(jsonStart !== -1) data = JSON.parse(text.substring(jsonStart));
      } catch(e) { return; }
      if (data && data.success) {
        globalCart = Array.isArray(data.cart) ? data.cart : Object.values(data.cart || {});
        if (typeof window.cart !== 'undefined') window.cart = globalCart;
        updateAllCartBadges();
        renderGlobalCartItems();
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('karavalliCartWeightExtended', JSON.stringify(globalCart));
        }
      }
    })
    .catch(console.error);
}

function updateAllCartBadges() {
    const arr = Array.isArray(globalCart) ? globalCart : (globalCart ? Object.values(globalCart) : []);
    const totalQty = arr.reduce((sum, item) => sum + parseInt(item.qty || 1), 0);
    const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge, #cartBadgeCount");
    badges.forEach(b => {
        b.innerText = totalQty;
    });
}

function renderGlobalCartItems() {
  const container = document.getElementById('globalCartItems');
  const globalCartTotal = document.getElementById('globalCartTotal');
  if (!container) return;
  if (!globalCart || !globalCart.length) {
    container.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-basket"></i><p>Your cart is empty</p></div>`;
    if(globalCartTotal) globalCartTotal.innerText = '₹0';
    return;
  }
  let total = 0;
  let html = '';
  globalCart.forEach(item => {
    const itemTotal = parseFloat(item.price || 0) * parseInt(item.qty || 1);
    total += itemTotal;
    const itemName = item.name || 'Product';
    html += `
      <div class="cart-item" data-id="${item.id}">
        <div class="cart-item-image"><i class="fas fa-shopping-cart"></i></div>
        <div class="cart-item-details">
          <div class="cart-item-name">${itemName}</div>
          <div class="cart-item-price">₹${parseFloat(item.price || 0).toFixed(2)} × ${item.qty} = ₹${itemTotal.toFixed(2)}</div>
          <div class="cart-item-actions">
            <button onclick="updateGlobalCartQty('${item.id}', ${item.qty - 1})">-</button>
            <span>${item.qty}</span>
            <button onclick="updateGlobalCartQty('${item.id}', ${item.qty + 1})">+</button>
            <button onclick="removeGlobalCartItem('${item.id}')"><i class="fas fa-trash-alt"></i></button>
          </div>
        </div>
      </div>
    `;
  });
  container.innerHTML = html;
  if(globalCartTotal) globalCartTotal.innerText = `₹${total.toFixed(2)}`;
}

// Initial load
document.addEventListener('DOMContentLoaded', loadGlobalCart);
</script>
