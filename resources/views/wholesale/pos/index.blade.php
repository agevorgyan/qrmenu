@extends('layouts.app')

@section('title', __('Wholesale POS Terminal'))

@section('content')
<div class="content-wrapper" x-data="wholesalePos()" style="max-width: 1400px; margin: 0 auto;">
    <!-- POS Header Bar -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(99, 102, 241, 0.12); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-cash-register"></i>
            </div>
            <div>
                <h1 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Wholesale POS Terminal') }}
                </h1>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                    {{ __('B2B Distribution, Bulk Pricing Engine & Direct Invoicing') }}
                </div>
            </div>
        </div>

        <!-- Controls: Seller & Customer -->
        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <!-- Seller Company -->
            <div>
                <label style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.25rem;">
                    {{ __('Issuing Seller Company') }} *
                </label>
                <select x-model="selectedSellerId" style="padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.88rem; font-weight: 700;">
                    @foreach($sellers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->tin }}) &mdash; {{ $s->tax_regime->value }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Customer -->
            <div>
                <label style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.25rem;">
                    {{ __('B2B Buyer / Client') }} *
                </label>
                <select x-model="selectedCustomerId" @change="onCustomerChange()" style="padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.88rem; font-weight: 700;">
                    <option value="">{{ __('Select Corporate Customer...') }}</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" 
                                data-credit="{{ $c->is_credit_allowed ? 1 : 0 }}"
                                data-limit="{{ (float)$c->credit_limit }}"
                                data-balance="{{ (float)$c->current_balance }}"
                                data-terms="{{ $c->payment_term_days }}">
                            {{ $c->name }} (TIN: {{ $c->tin ?? 'N/A' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Customer Credit Status Badge -->
            <div x-show="customerInfo.id" style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.4rem 0.75rem; font-size: 0.78rem;">
                <div style="font-weight: 700; color: var(--text-main);">
                    {{ __('AR Balance') }}: <span :style="customerInfo.balance > 0 ? 'color: #ef4444;' : 'color: #10b981;'" x-text="formatCurrency(customerInfo.balance) + ' AMD'"></span>
                </div>
                <div style="color: var(--text-muted); font-size: 0.72rem;">
                    <span x-text="customerInfo.creditAllowed ? 'Credit Limit: ' + formatCurrency(customerInfo.limit) + ' AMD' : 'Prepayment only'"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main POS Grid: Left = Product Directory, Right = Cart -->
    <div style="display: grid; grid-template-columns: 1fr 480px; gap: 1.5rem; align-items: start;">
        <!-- Left: Product Directory -->
        <div>
            <!-- Search & Filter -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1rem; display: flex; gap: 0.75rem;">
                <div style="flex: 1; position: relative;">
                    <i class="fa-solid fa-barcode" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" x-model="searchQuery" placeholder="{{ __('Scan Barcode or search by SKU, ATGAA, or Product name...') }}"
                           style="width: 100%; padding: 0.65rem 1rem 0.65rem 2.5rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                </div>
                <select x-model="selectedCategory" style="padding: 0.65rem 1rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Products List / Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem;">
                @foreach($products as $p)
                    <div x-show="matchesSearch('{{ strtolower($p->name) }}', '{{ strtolower($p->sku) }}', '{{ $p->barcode }}', '{{ $p->category_id }}', '{{ $p->classifier_code }}')"
                         style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.15s ease, box-shadow 0.15s ease;"
                         onmouseover="this.style.boxShadow='0 6px 18px rgba(0,0,0,0.06)'"
                         onmouseout="this.style.boxShadow='none'">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
                                <span style="font-family: monospace; font-size: 0.72rem; font-weight: 700; color: #6366f1; background: rgba(99, 102, 241, 0.1); padding: 0.15rem 0.4rem; border-radius: 4px;">
                                    {{ $p->sku }}
                                </span>
                                <span style="font-size: 0.7rem; font-family: monospace; color: var(--text-muted);">
                                    {{ __('FEACN / ATGAA') }}: {{ $p->classifier_code }}
                                </span>
                            </div>
                            <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem 0; line-height: 1.3;">
                                {{ $p->name }}
                            </h3>
                            <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                                {{ __('Unit') }}: {{ $p->unit?->name ?? __('unit') }}
                                @if((float)$p->min_order_quantity > 1)
                                    &bull; {{ __('MOQ:') }} {{ (float)$p->min_order_quantity }}
                                @endif
                            </div>
                        </div>

                        <div>
                            <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.6rem;">
                                {{ number_format($p->base_wholesale_price, 2) }} <span style="font-size: 0.8rem; font-weight: 500;">AMD</span>
                            </div>
                            <button type="button" 
                                    @click="addToCart({{ $p->id }}, '{{ addslashes($p->name) }}', '{{ $p->sku }}', {{ (float)$p->base_wholesale_price }}, '{{ $p->classifier_code }}', '{{ $p->unit?->code ?? 'unit' }}', {{ (float)$p->min_order_quantity }})"
                                    style="width: 100%; padding: 0.55rem; border-radius: 8px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem; font-size: 0.85rem;">
                                <i class="fa-solid fa-plus"></i> {{ __('Add to Order') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Right: Order Cart & Checkout -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); position: sticky; top: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Order Items') }} (<span x-text="cart.length"></span>)
                </h2>
                <button type="button" @click="clearCart()" x-show="cart.length > 0" style="background: none; border: none; color: #ef4444; font-size: 0.8rem; cursor: pointer;">
                    <i class="fa-solid fa-trash"></i> {{ __('Clear') }}
                </button>
            </div>

            <!-- Cart Items List -->
            <div style="max-height: 380px; overflow-y: auto; margin-bottom: 1rem;">
                <template x-for="(item, index) in cart" :key="item.product_id">
                    <div style="border-bottom: 1px solid var(--border-color); padding: 0.75rem 0; display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1; padding-right: 0.5rem;">
                            <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);" x-text="item.name"></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                SKU: <span x-text="item.sku"></span> &bull; <span x-text="item.unit"></span>
                            </div>
                            <div style="font-size: 0.75rem; color: #6366f1; font-weight: 600; margin-top: 0.2rem;">
                                <span x-text="formatCurrency(item.unit_price) + ' AMD'"></span>
                            </div>
                        </div>

                        <!-- Stepper -->
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <button type="button" @click="decrementQty(index)" style="width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-weight: 700; cursor: pointer;">-</button>
                            <input type="number" step="1" x-model.number="item.quantity" @change="recalculateTotals()" style="width: 50px; text-align: center; padding: 0.25rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-weight: 700; font-size: 0.85rem;">
                            <button type="button" @click="incrementQty(index)" style="width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-weight: 700; cursor: pointer;">+</button>
                            <button type="button" @click="removeItem(index)" style="background: none; border: none; color: #ef4444; margin-left: 0.25rem; cursor: pointer;">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                </template>
                <div x-show="cart.length === 0" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); font-size: 0.88rem;">
                    <i class="fa-solid fa-cart-shopping" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.3;"></i>
                    {{ __('Cart is empty. Select products on the left or scan barcodes.') }}
                </div>
            </div>

            <!-- Totals Breakdown -->
            <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text-muted);">
                    <span>{{ __('Subtotal') }}</span>
                    <span style="font-weight: 700; color: var(--text-main);" x-text="formatCurrency(subtotal) + ' AMD'"></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text-muted);">
                    <span>{{ __('VAT (20%)') }}</span>
                    <span style="font-weight: 700; color: var(--text-main);" x-text="formatCurrency(taxTotal) + ' AMD'"></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 800; color: var(--text-main); padding-top: 0.5rem; border-top: 2px solid var(--border-color);">
                    <span>{{ __('Grand Total') }}</span>
                    <span style="color: #6366f1;" x-text="formatCurrency(grandTotal) + ' AMD'"></span>
                </div>
            </div>

            <!-- Checkout Form Submission -->
            <form method="POST" action="{{ route('admin.wholesale.pos.checkout') }}" @submit="prepareSubmission($event)">
                @csrf
                <input type="hidden" name="seller_id" :value="selectedSellerId">
                <input type="hidden" name="customer_id" :value="selectedCustomerId">
                <input type="hidden" name="order_type" value="STANDARD">
                <input type="hidden" name="action_type" x-model="actionType">
                <input type="hidden" name="payment_method" x-model="paymentMethod">
                <input type="hidden" name="paid_amount" :value="grandTotal">
                
                <template x-for="(item, index) in cart" :key="index">
                    <div>
                        <input type="hidden" :name="'items['+index+'][product_id]'" :value="item.product_id">
                        <input type="hidden" :name="'items['+index+'][quantity]'" :value="item.quantity">
                    </div>
                </template>

                <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                    <button type="submit" @click="actionType = 'CONFIRM_AND_SALE'; paymentMethod = 'BANK_TRANSFER'"
                            :disabled="cart.length === 0 || !selectedCustomerId"
                            style="width: 100%; padding: 0.85rem; border-radius: 10px; background: #10b981; color: #fff; font-weight: 800; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);">
                        <i class="fa-solid fa-receipt"></i>
                        <span>{{ __('Instant Commercial Sale (Invoice)') }}</span>
                    </button>

                    <button type="submit" @click="actionType = 'CREATE_ORDER'"
                            :disabled="cart.length === 0 || !selectedCustomerId"
                            style="width: 100%; padding: 0.75rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span>{{ __('Save as Draft Wholesale Order') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function wholesalePos() {
    return {
        selectedSellerId: '{{ $sellers->first()?->id }}',
        selectedCustomerId: '',
        searchQuery: '',
        selectedCategory: '',
        actionType: 'CONFIRM_AND_SALE',
        paymentMethod: 'BANK_TRANSFER',
        customerInfo: { id: null, limit: 0, balance: 0, creditAllowed: false },
        cart: [],
        subtotal: 0,
        taxTotal: 0,
        grandTotal: 0,

        onCustomerChange() {
            const select = document.querySelector('select[x-model="selectedCustomerId"]');
            const option = select.options[select.selectedIndex];
            if (option && option.value) {
                this.customerInfo = {
                    id: option.value,
                    limit: parseFloat(option.dataset.limit || 0),
                    balance: parseFloat(option.dataset.balance || 0),
                    creditAllowed: option.dataset.credit === '1',
                };
            } else {
                this.customerInfo = { id: null, limit: 0, balance: 0, creditAllowed: false };
            }
        },

        matchesSearch(name, sku, barcode, categoryId, classifier) {
            if (this.selectedCategory && categoryId !== this.selectedCategory) return false;
            if (!this.searchQuery) return true;
            const q = this.searchQuery.toLowerCase().trim();
            return name.includes(q) || sku.includes(q) || (barcode && barcode.includes(q)) || (classifier && classifier.includes(q));
        },

        addToCart(productId, name, sku, basePrice, classifier, unit, moq) {
            const existing = this.cart.find(i => i.product_id === productId);
            if (existing) {
                existing.quantity += 1;
            } else {
                this.cart.push({
                    product_id: productId,
                    name: name,
                    sku: sku,
                    unit_price: basePrice,
                    quantity: moq > 0 ? moq : 1,
                    classifier: classifier,
                    unit: unit,
                });
            }
            this.recalculateTotals();
        },

        incrementQty(index) {
            this.cart[index].quantity += 1;
            this.recalculateTotals();
        },

        decrementQty(index) {
            if (this.cart[index].quantity > 1) {
                this.cart[index].quantity -= 1;
            } else {
                this.cart.splice(index, 1);
            }
            this.recalculateTotals();
        },

        removeItem(index) {
            this.cart.splice(index, 1);
            this.recalculateTotals();
        },

        clearCart() {
            this.cart = [];
            this.recalculateTotals();
        },

        recalculateTotals() {
            let sum = 0;
            this.cart.forEach(i => {
                sum += (i.unit_price * i.quantity);
            });
            this.subtotal = sum;
            this.taxTotal = Math.round(sum * 0.20 * 100) / 100;
            this.grandTotal = Math.round((this.subtotal + this.taxTotal) * 100) / 100;
        },

        formatCurrency(num) {
            return new Intl.NumberFormat('hy-AM', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num || 0);
        },

        prepareSubmission(e) {
            if (!this.selectedCustomerId) {
                e.preventDefault();
                alert('{{ __("Please select a B2B corporate customer before completing checkout.") }}');
            }
        }
    };
}
</script>
@endsection

