@if(isset($waiterCalls) && $waiterCalls->count() > 0)
    <div style="margin-bottom: 1.5rem; background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 16px; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <h3 style="font-family: 'Outfit'; font-size: 1.1rem; font-weight: 700; color: #f59e0b; display: flex; align-items: center; gap: 0.5rem;">
                <span class="pulse-dot" style="width: 10px; height: 10px; background: #f59e0b; border-radius: 50%; display: inline-block;"></span>
                🛎️ {{ __('Table Calls & Bill Requests') }} ({{ $waiterCalls->count() }})
            </h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">{{ __('Requires prompt staff attention') }}</span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
            @foreach($waiterCalls as $call)
                <div style="background: var(--bg-card); border: 1px solid {{ $call->type == 'call_waiter' ? '#f59e0b' : ($call->type == 'bill_cash' ? '#10b981' : '#3b82f6') }}; border-radius: 12px; padding: 1rem; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <div>
                            <span style="font-size: 1.15rem; font-weight: 800; font-family: 'Outfit'; color: var(--text-main);">
                                <i class="fa-solid fa-chair" style="color: var(--primary);"></i> {{ $call->table_number ? (str_starts_with($call->table_number, 'Table') ? str_replace('Table', __('Table'), $call->table_number) : __('Table') . ' ' . $call->table_number) : __('Table') }}
                            </span>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                <i class="fa-solid fa-clock"></i> {{ $call->created_at->diffForHumans() }}
                            </div>
                        </div>

                        <span style="padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem; background: {{ $call->type == 'call_waiter' ? 'rgba(245, 158, 11, 0.15)' : ($call->type == 'bill_cash' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(59, 130, 246, 0.15)') }}; color: {{ $call->type == 'call_waiter' ? '#d97706' : ($call->type == 'bill_cash' ? '#059669' : '#2563eb') }};">
                            <i class="{{ $call->getTypeIcon() }}"></i> {{ $call->getTypeLabel() }}
                        </span>
                    </div>

                    @if($call->notes)
                        <div style="font-size: 0.8rem; color: var(--text-muted); background: var(--bg-main); padding: 0.4rem 0.6rem; border-radius: 6px; margin-bottom: 0.75rem;">
                            {{ $call->notes }}
                        </div>
                    @endif

                    <div style="margin-top: 0.75rem; display: flex; justify-content: flex-end;">
                        <button onclick="markWaiterCallAttended({{ $call->id }})" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                            <i class="fa-solid fa-check"></i> {{ __('Attended') }}
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
