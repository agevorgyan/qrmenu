@if($orders->count() == 0)
    <div style="text-align: center; padding: 3.5rem 1rem; background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: 16px;">
        <i class="fa-solid fa-bell-concierge" style="font-size: 3rem; color: var(--text-muted); opacity: 0.5; margin-bottom: 1rem;"></i>
        <h3 style="font-family: 'Outfit'; font-size: 1.2rem; color: var(--text-main); margin-bottom: 0.35rem;">
            {{ __('No Orders Found') }}
        </h3>
        <p style="color: var(--text-muted); font-size: 0.85rem;">
            {{ __('New orders from tables and delivery will automatically appear here in real time.') }}
        </p>
    </div>
@else
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); gap: 1.25rem;">
        @foreach($orders as $order)
            <div class="card order-card" id="order-card-{{ $order->id }}" style="border-top: 4px solid {{ $order->status == 'pending' ? '#ef4444' : ($order->status == 'preparing' ? '#f59e0b' : ($order->status == 'ready' ? '#3b82f6' : '#10b981')) }}; position: relative; transition: all 0.3s ease; display: flex; flex-direction: column;">
                
                @if($order->status == 'pending')
                    <div style="position: absolute; top: -10px; right: 12px; background: #ef4444; color: #fff; font-size: 0.68rem; font-weight: 800; padding: 0.15rem 0.6rem; border-radius: 9999px; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4); animation: pulse 1.8s infinite;">
                        ⚡ {{ __('NEW ORDER') }}
                    </div>
                @endif

                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; gap: 0.5rem;">
                    <div style="min-width: 0;">
                        <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0; word-break: break-word;">{{ $order->order_number }}</h3>
                        <span style="font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.25rem; margin-top: 0.2rem;"><i class="fa-solid fa-clock"></i> {{ $order->created_at->format('H:i, d M') }}</span>
                    </div>

                    <div style="text-align: right; flex-shrink: 0;">
                        @if($order->type === 'delivery')
                            <span style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.4); font-size: 0.75rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-motorcycle"></i> {{ __('DELIVERY') }}
                            </span>
                        @else
                            <span style="background: var(--badge-bg); color: var(--badge-text); font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 6px;">
                                {{ $order->table_number ? (str_starts_with($order->table_number, 'Table') ? str_replace('Table', __('Table'), $order->table_number) : __('Table') . ' ' . $order->table_number) : __('Takeaway') }}
                            </span>
                        @endif
                        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem; text-transform: uppercase;">
                            {{ match($order->type) { 'dine_in' => __('Dine In'), 'takeaway' => __('Takeaway'), 'delivery' => __('Delivery'), default => __(ucwords(str_replace('_', ' ', $order->type))) } }}
                        </div>
                        @if($order->payment_method)
                            <div style="margin-top: 0.25rem;">
                                <span style="font-size: 0.68rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 4px; background: {{ $order->payment_status === 'paid' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' }}; color: {{ $order->payment_status === 'paid' ? '#10b981' : '#ef4444' }};">
                                    {{ strtoupper($order->payment_method) }}: {{ $order->payment_status === 'paid' ? __('Paid') : __('Unpaid') }}
                                </span>
                            </div>
                        @endif
                        @php
                            $sb = $order->activeSplitBill ?? $order->splitBills->first();
                            $hasPendingCashSplit = $sb && $sb->payments->where('status', 'pending')->isNotEmpty();
                        @endphp
                        @if($sb)
                            <div style="margin-top: 0.35rem;">
                                <a href="{{ route('admin.orders.split_bill', $order->id) }}" style="text-decoration: none; font-size: 0.7rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 6px; background: {{ $hasPendingCashSplit ? 'rgba(234, 179, 8, 0.15)' : ($sb->isFullyPaid() ? 'rgba(16, 185, 129, 0.15)' : 'rgba(59, 130, 246, 0.15)') }}; color: {{ $hasPendingCashSplit ? '#d97706' : ($sb->isFullyPaid() ? '#10b981' : '#2563eb') }}; border: 1px solid currentColor; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-divide"></i>
                                    <span>{{ __('Split') }}: {{ number_format($sb->paid_amount) }}/{{ number_format($sb->total_amount) }}</span>
                                    @if($hasPendingCashSplit)
                                        <span style="background: #ef4444; color: #fff; border-radius: 9999px; padding: 0.05rem 0.35rem; font-size: 0.62rem; animation: pulse 1.5s infinite;">!</span>
                                    @endif
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                @if($order->customer_name || $order->customer_email || $order->customer_phone || $order->delivery_address)
                    <div style="font-size: 0.85rem; color: var(--text-main); margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 0.4rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.4rem;">
                            <div style="min-width: 0;">
                                <i class="fa-solid fa-user" style="color: var(--primary);"></i> <strong>{{ $order->customer_name ?? __('Guest') }}</strong>
                                @if($order->customer_phone)
                                    <span style="color: var(--text-muted); margin-left: 0.3rem;">
                                        <i class="fa-solid fa-phone" style="font-size: 0.75rem; color: {{ $order->type === 'delivery' ? '#f59e0b' : 'var(--primary)' }};"></i> 
                                        <strong>{{ $order->customer_phone }}</strong>
                                    </span>
                                @endif
                                @if($order->customer_email)
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem; word-break: break-all;">
                                        <i class="fa-solid fa-envelope"></i> {{ $order->customer_email }}
                                    </div>
                                @endif
                            </div>
                            @if($order->marketing_opt_in)
                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 4px;" title="Consented to privacy policy and marketing communications">
                                    <i class="fa-solid fa-check-double"></i> {{ __('Marketing Consent') }}
                                </span>
                            @endif
                        </div>

                        @if($order->delivery_address)
                            <div style="width: 100%; font-size: 0.82rem; color: #f59e0b; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 8px; padding: 0.45rem 0.7rem; display: flex; align-items: flex-start; gap: 0.5rem; word-break: break-word;">
                                <i class="fa-solid fa-map-location-dot" style="margin-top: 0.15rem; flex-shrink: 0;"></i>
                                <div style="min-width: 0;">
                                    <strong>{{ __('Delivery address:') }}</strong> {{ $order->delivery_address }}
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; max-height: 180px; overflow-y: auto;">
                    @foreach($order->items as $item)
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.45rem 0.65rem; border-radius: 10px; color: var(--text-main); gap: 0.5rem;">
                            <div style="min-width: 0;">
                                <strong>{{ $item->quantity }}x</strong> <span style="word-break: break-word;">{{ $item->product_name }}</span>
                                @if($item->variation_name && $item->variation_name !== 'Standard' && $item->variation_name !== 'Standard Portion')
                                    <small style="color: var(--text-muted); display: block;">({{ $item->variation_name }})</small>
                                @endif
                            </div>
                            <div style="font-weight: 700; color: var(--text-main); flex-shrink: 0;">{{ number_format($item->subtotal) }}</div>
                        </div>
                    @endforeach
                </div>

                @if($order->notes)
                    <div style="font-size: 0.8rem; color: #ef4444; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); padding: 0.4rem 0.6rem; border-radius: 8px; margin-bottom: 1rem; word-break: break-word;">
                        <i class="fa-solid fa-note-sticky"></i> {{ $order->notes }}
                    </div>
                @endif

                <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid var(--border-color); padding-top: 0.75rem; margin-top: auto; flex-wrap: wrap; gap: 0.75rem;">
                    <div style="min-width: 0;">
                        @if($order->subtotal > 0 && ($order->service_fee > 0 || $order->delivery_fee > 0 || $order->type === 'delivery'))
                            <div style="font-size: 0.72rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 0.15rem; margin-bottom: 0.35rem; line-height: 1.3;">
                                <div>{{ __('Subtotal:') }} <strong>{{ number_format($order->subtotal) }}</strong> {{ $vendor->currency }}</div>
                                @if($order->service_fee > 0)
                                    <div style="color: #10b981;">{{ __('Service:') }} +{{ number_format($order->service_fee) }} {{ $vendor->currency }}</div>
                                @endif
                                @if($order->delivery_fee > 0)
                                    <div style="color: #3b82f6;">{{ __('Delivery:') }} +{{ number_format($order->delivery_fee) }} {{ $vendor->currency }}</div>
                                @elseif($order->type === 'delivery')
                                    <div style="color: #10b981;">{{ __('Delivery:') }} {{ __('FREE') }}</div>
                                @endif
                            </div>
                        @endif
                        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700; letter-spacing: 0.05em;">{{ __('TOTAL AMOUNT') }}</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #10b981; font-family: 'Outfit'; line-height: 1.1;">
                            {{ number_format($order->total_amount) }} <span style="font-size: 0.85rem;">{{ $vendor->currency }}</span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                        <button type="button" class="btn btn-secondary" style="padding: 0.45rem 0.65rem; font-size: 0.78rem; border-color: rgba(245, 158, 11, 0.4); color: var(--primary);" title="{{ __('Print Receipt (ESC/POS)') }}" onclick="printOrderReceipt({{ $order->id }})">
                            <i class="fa-solid fa-print"></i>
                        </button>

                        @if($sb || ($vendor->isSplitBillEnabled() && $order->payment_status !== 'paid'))
                            <a href="{{ route('admin.orders.split_bill', $order->id) }}" class="btn btn-secondary" style="padding: 0.45rem 0.65rem; font-size: 0.78rem; color: #3b82f6; border-color: rgba(59, 130, 246, 0.4); text-decoration: none;" title="{{ __('Split Bill Details & Payments') }}">
                                <i class="fa-solid fa-divide"></i>
                            </a>
                        @endif

                        @if($order->status == 'pending')
                            <button type="button" class="btn btn-primary" style="padding: 0.45rem 0.85rem; font-size: 0.78rem;" onclick="changeOrderStatus({{ $order->id }}, 'preparing')">
                                <i class="fa-solid fa-fire"></i> {{ __('Accept') }}
                            </button>
                            <button type="button" class="btn btn-secondary" style="padding: 0.45rem 0.65rem; font-size: 0.78rem; color: #ef4444;" title="{{ __('Cancel') }}" onclick="if(confirm('{{ __('Cancel this order?') }}')) changeOrderStatus({{ $order->id }}, 'cancelled')">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        @elseif($order->status == 'preparing')
                            <button type="button" class="btn btn-success" style="padding: 0.45rem 0.85rem; font-size: 0.78rem;" onclick="changeOrderStatus({{ $order->id }}, 'ready')">
                                <i class="fa-solid fa-bell"></i> {{ __('Ready') }}
                            </button>
                        @elseif($order->status == 'ready')
                            <button type="button" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.78rem; color: #10b981; border-color: rgba(16, 185, 129, 0.3);" onclick="changeOrderStatus({{ $order->id }}, 'completed')">
                                <i class="fa-solid fa-check"></i> {{ __('Complete') }}
                            </button>
                        @elseif($order->status == 'cancelled')
                            <span class="badge badge-rose" style="font-size: 0.78rem;"><i class="fa-solid fa-ban"></i> {{ __('Cancelled') }}</span>
                        @else
                            <span class="badge badge-emerald" style="font-size: 0.78rem;"><i class="fa-solid fa-circle-check"></i> {{ __('Completed') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $orders->appends(request()->query())->links() }}
    </div>
@endif
