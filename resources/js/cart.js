import {escapeHtml} from './admin.js';

const key = 'jmac.cart.v2';
let cart = [];
try { cart = JSON.parse(localStorage.getItem(key) ?? '[]'); } catch {}
if (!Array.isArray(cart)) cart = [];
cart = cart.filter(item => Number.isInteger(item.id) && item.id > 0 && typeof item.name === 'string' && typeof item.sku === 'string' && Number.isInteger(item.quantity) && item.quantity >= 1 && item.quantity <= 999).slice(0, 100);

export const getCart = () => cart.map(item => ({product_id: item.id, quantity: item.quantity}));
export function clearCart() { cart = []; render(); }

const emptyIcon = '<svg class="cart-icon empty-cart-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h3l2.4 11.3a2 2 0 0 0 2 1.7h8.8a2 2 0 0 0 2-1.6L22 7H6"/><circle cx="10" cy="21" r="1"/><circle cx="19" cy="21" r="1"/></svg>';
const drawer = document.getElementById('cart-drawer');
function announce(message) {
    document.querySelectorAll('[data-cart-status]').forEach(element => element.textContent = message);
}
function render() {
    try { localStorage.setItem(key, JSON.stringify(cart)); } catch {}
    const quantity = cart.reduce((sum, item) => sum + item.quantity, 0);
    document.querySelectorAll('[data-cart-count]').forEach(element => {
        element.textContent = quantity;
        element.title = `${quantity} unidades en tu solicitud`;
    });
    document.querySelectorAll('[data-cart-lines]').forEach(element => {
        element.innerHTML = cart.length ? cart.map(item => `<div class="cart-line"><div><strong>${escapeHtml(item.name)}</strong><p>${escapeHtml(item.sku)}</p></div><label>Cantidad<input type="number" min="1" max="999" value="${item.quantity}" data-cart-quantity="${item.id}" aria-label="Cantidad de ${escapeHtml(item.name)}"></label><button type="button" class="secondary" data-cart-remove="${item.id}" aria-label="Quitar ${escapeHtml(item.name)}">Quitar</button></div>`).join('') : `<div class="empty-state">${emptyIcon}<h3>Tu carrito está vacío</h3><p>Podrás agregar productos cuando estén disponibles en el catálogo.</p></div>`;
    });
    document.querySelectorAll('[data-preorder-form-container], [data-cart-actions]').forEach(element => element.hidden = !cart.length);
    document.dispatchEvent(new CustomEvent('jmac:cart', {detail: getCart()}));
}

document.addEventListener('click', event => {
    const open = event.target.closest('[data-cart-open]');
    if (open && drawer && typeof drawer.showModal === 'function') {
        drawer.showModal();
        event.preventDefault();
        open.setAttribute('aria-expanded', 'true');
        document.body.classList.add('cart-is-open');
        return;
    }
    if (event.target.closest('[data-cart-close]') && drawer?.open) drawer.close();
    const add = event.target.closest('[data-cart-add]');
    const remove = event.target.closest('[data-cart-remove]');
    const clear = event.target.closest('[data-cart-clear]');
    if (add) {
        const quantityInput = add.dataset.quantityInput ? document.getElementById(add.dataset.quantityInput) : null;
        const quantity = quantityInput ? Number(quantityInput.value) : 1;
        if (!Number.isInteger(quantity) || quantity < 1 || quantity > 999) { quantityInput?.reportValidity(); return; }
        const id = Number(add.dataset.id);
        if (!Number.isInteger(id) || id < 1) return;
        const existing = cart.find(item => item.id === id);
        if (existing) existing.quantity = Math.min(999, existing.quantity + quantity);
        else if (cart.length < 100) cart.push({id, name: add.dataset.name ?? '', sku: add.dataset.sku ?? '', quantity});
        else { announce('Tu carrito admite hasta 100 productos diferentes.'); return; }
        render();
        const label = add.querySelector('[data-cart-label]') ?? add;
        const original = label.textContent;
        label.textContent = 'Agregado ✓';
        setTimeout(() => label.textContent = original, 1200);
        announce('Producto agregado a tu carrito.');
    }
    if (remove) { cart = cart.filter(item => item.id !== Number(remove.dataset.cartRemove)); render(); announce('Producto retirado del carrito.'); }
    if (clear && (!cart.length || confirm('¿Vaciar tu carrito de preventa?'))) { clearCart(); announce('Carrito vaciado.'); }
});
drawer?.addEventListener('close', () => {
    document.body.classList.remove('cart-is-open');
    document.querySelectorAll('[data-cart-open]').forEach(element => element.setAttribute('aria-expanded', 'false'));
});
drawer?.addEventListener('click', event => {
    if (event.target !== drawer) return;
    const rectangle = drawer.getBoundingClientRect();
    if (event.clientX < rectangle.left || event.clientX > rectangle.right || event.clientY < rectangle.top || event.clientY > rectangle.bottom) drawer.close();
});
document.addEventListener('change', event => {
    const input = event.target;
    if (input.dataset.cartQuantity) {
        const item = cart.find(item => item.id === Number(input.dataset.cartQuantity));
        const value = Number(input.value);
        if (item && Number.isInteger(value) && value >= 1 && value <= 999) item.quantity = value;
        render();
    }
});
document.querySelectorAll('[data-gallery-url]').forEach(button => button.addEventListener('click', () => {
    const image = document.getElementById('product-main-image');
    if (image) { image.src = button.dataset.galleryUrl; image.alt = button.dataset.galleryAlt; }
}));
render();
