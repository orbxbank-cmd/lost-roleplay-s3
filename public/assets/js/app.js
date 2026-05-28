class Cart {
    constructor() {
        this.items = JSON.parse(localStorage.getItem('cart') || '[]');
        this.bundles = [];
        this.activeBundle = null;
        this.loadBundles();
        this.updateBadge();
    }

    async loadBundles() {
        try {
            const res = await fetch('bundles_json.php');
            this.bundles = await res.json();
        } catch (e) { this.bundles = []; }
    }

    findBestBundle() {
        if (!this.bundles.length) return null;
        for (const b of this.bundles) {
            const match = b.products.every(bp => {
                const cartItem = this.items.find(i => i.id === bp.product_id);
                return cartItem && cartItem.quantity >= bp.quantity;
            });
            if (match) return b;
        }
        return null;
    }

    getBundleTotal(baseTotal) {
        this.activeBundle = this.findBestBundle();
        if (this.activeBundle) return this.activeBundle.total_price;
        return baseTotal;
    }

    add(product) {
        const existing = this.items.find(i => i.id === product.id);
        if (existing) {
            existing.quantity += product.quantity || 1;
            if (product.models) {
                existing.models = (existing.models || []).concat(product.models);
            }
        } else {
            this.items.push({
                id: product.id,
                name: product.name,
                category: product.category,
                price: product.price,
                coinPrice: product.coinPrice || product.price || 0,
                quantity: product.quantity || 1,
                models: product.models || null
            });
        }
        this.save();
        this.showToast(`"${product.name}" added to cart`);
    }

    remove(productId) {
        this.items = this.items.filter(i => i.id !== productId);
        this.save();
        this.renderCart();
    }

    updateQuantity(productId, qty) {
        const item = this.items.find(i => i.id === productId);
        if (item) {
            item.quantity = Math.max(1, parseInt(qty) || 1);
            this.save();
            this.renderCart();
        }
    }

    clear() {
        this.items = [];
        this.save();
        this.renderCart();
    }

    getTotal() {
        const base = this.items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
        return this.getBundleTotal(base);
    }

    getBaseTotal() {
        return this.items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
    }

    getCount() {
        return this.items.reduce((sum, i) => sum + i.quantity, 0);
    }

    save() {
        localStorage.setItem('cart', JSON.stringify(this.items));
        this.updateBadge();
    }

    updateBadge() {
        document.querySelectorAll('.cart-badge').forEach(el => {
            el.textContent = this.getCount();
            el.style.display = this.getCount() > 0 ? 'inline' : 'none';
        });
    }

    renderCart() {
        const container = document.getElementById('cart-items');
        const summary = document.getElementById('cart-summary');
        if (!container) return;

        if (this.items.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                    <h3>Cart is empty</h3>
                    <p>Add some products from the shop</p>
                    <a href="shop.php" class="btn btn-primary"><i class="fas fa-store"></i> Browse Shop</a>
                </div>`;
            if (summary) summary.innerHTML = '';
            return;
        }

        container.innerHTML = this.items.map(item => `
            <div class="cart-item" data-id="${item.id}">
                <div class="item-info">
                    <h4>${this.escapeHtml(item.name)}</h4>
                    <div class="item-category">${this.escapeHtml(item.category || '')}</div>
                    ${item.models ? `<div style="font-size:0.75rem;color:var(--info);margin-top:4px;"><i class="fas fa-car"></i> ${item.models.map(m => 'ID: ' + m).join(', ')}</div>` : ''}
                </div>
                <div class="item-actions">
                    <input type="number" class="form-control qty-input" value="${item.quantity}" min="1" style="width: 60px;" data-id="${item.id}">
                    <span class="item-price"><i class="fas fa-coins" style="color:var(--accent);"></i> ${(item.price * item.quantity).toFixed(0)} <small>Coins</small></span>
                    <button class="btn btn-danger btn-sm remove-item" data-id="${item.id}"><i class="fas fa-times"></i></button>
                </div>
            </div>
        `).join('');

        if (summary) {
            const baseTotal = this.getBaseTotal();
            const finalTotal = this.getTotal();
            const hasDiscount = finalTotal < baseTotal;
            const bundle = this.activeBundle;

            let discountHtml = '';
            if (hasDiscount && bundle) {
                discountHtml = `
                    <div class="total-row" style="color: var(--success);">
                        <span class="total-label"><i class="fas fa-tag"></i> Bundle: ${this.escapeHtml(bundle.name)}</span>
                        <span class="total-value">-${(baseTotal - finalTotal).toFixed(0)} <small>Coins</small></span>
                    </div>`;
            }

            summary.innerHTML = `
                <div class="cart-summary">
                    <h3 style="margin-bottom: 1rem; font-family: var(--header-font);">Order Summary</h3>
                    <div class="total-row">
                        <span class="total-label">Products</span>
                        <span class="total-value">${this.getCount()}</span>
                    </div>
                    ${discountHtml}
                    <div class="total-row">
                        <span class="total-label">Total</span>
                        <span class="total-value grand-total" style="${hasDiscount ? 'color: var(--success);' : ''}"><i class="fas fa-coins"></i> ${finalTotal.toFixed(0)} <small>Coins</small></span>
                    </div>
                    ${hasDiscount ? `<div style="font-size: 0.8rem; color: var(--success); text-align: center; margin-bottom: 0.5rem;"><i class="fas fa-check-circle"></i> Bundle discount applied!</div>` : ''}
                    <a href="checkout.php" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 1rem;">
                        <i class="fas fa-credit-card"></i> Checkout
                    </a>
                    <button class="btn btn-secondary btn-sm clear-cart" style="width: 100%; margin-top: 0.5rem;">
                        <i class="fas fa-trash"></i> Clear Cart
                    </button>
                </div>`;
        }

        container.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('change', (e) => {
                this.updateQuantity(parseInt(e.target.dataset.id), e.target.value);
            });
        });

        container.querySelectorAll('.remove-item').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.remove(parseInt(e.target.dataset.id));
            });
        });

        const clearBtn = document.querySelector('.clear-cart');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                if (confirm('Clear your cart?')) {
                    this.clear();
                }
            });
        }
    }

    showToast(message) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed; bottom: 20px; left: 20px; background: #34a853; color: white;
            padding: 12px 24px; border-radius: 8px; font-size: 14px; z-index: 9999;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

const cart = new Cart();

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('cart-items')) {
        cart.renderCart();
    }
});
