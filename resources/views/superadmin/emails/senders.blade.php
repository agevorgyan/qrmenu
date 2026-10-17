@extends('layouts.app')

@section('title', 'Sender Identities - QRMenu SuperAdmin')

@section('styles')
<style>
    .superadmin-hero {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(59, 130, 246, 0.08) 100%);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 1.75rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.25rem;
        box-shadow: var(--shadow-card);
    }
</style>
@endsection

@section('content')
<div x-data="{
    createModal: false,
    editModal: false,
    activeSender: {
        id: null,
        name: '',
        email: '',
        description: '',
        reply_to_email: '',
        reply_to_name: '',
        email_connection_id: '',
        is_default: false,
        is_active: true,
        assigned_categories: []
    },
    openEdit(sender) {
        this.activeSender = {
            id: sender.id,
            name: sender.name,
            email: sender.email,
            description: sender.description || '',
            reply_to_email: sender.reply_to_email || '',
            reply_to_name: sender.reply_to_name || '',
            email_connection_id: sender.email_connection_id || '',
            is_default: Boolean(sender.is_default),
            is_active: Boolean(sender.is_active),
            assigned_categories: sender.assigned_categories || []
        };
        this.editModal = true;
    }
}">

    @include('superadmin.emails._nav', [
        'title' => __('Sender Identities Management'),
        'subtitle' => __('Configure verified from addresses, reply-to routing, and identity associations.')
    ])

    <!-- Flash Messages -->
    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 0.9rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; font-weight: 600;">
            <i class="fa-solid fa-circle-check"></i> {{ __(session('success')) }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 0.9rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; font-weight: 600;">
            <i class="fa-solid fa-circle-exclamation"></i> {{ __(session('error')) }}
        </div>
    @endif

    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                {{ __('Configured Sender Identities') }} ({{ $senders->count() }})
            </h2>
            <p style="font-size: 0.82rem; color: var(--text-muted);">
                {{ __('Addresses must align with SPF, DKIM, and DMARC policies for optimal deliverability.') }}
            </p>
        </div>

        <button @click="createModal = true" class="btn btn-primary" style="border-radius: 12px; font-size: 0.84rem; padding: 0.65rem 1.25rem;">
            <i class="fa-solid fa-plus"></i> {{ __('Add Sender Identity') }}
        </button>
    </div>

    <!-- Senders Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div class="responsive-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Sender') }}</th>
                        <th>{{ __('Email Address') }}</th>
                        <th>{{ __('Reply-To') }}</th>
                        <th>{{ __('Associated Connection') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Routing Rules') }}</th>
                        <th>{{ __('Total Dispatches') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($senders as $s)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.6rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 800; flex-shrink: 0;">
                                        <i class="fa-solid fa-at"></i>
                                    </div>
                                    <div>
                                        <strong style="color: var(--text-main); font-size: 0.9rem;">{{ $s->name }}</strong>
                                        @if($s->is_default)
                                            <span class="badge badge-emerald" style="margin-left: 0.35rem;">
                                                <i class="fa-solid fa-star"></i> {{ __('Default') }}
                                            </span>
                                        @endif
                                        @if($s->description)
                                            <div style="font-size: 0.72rem; color: var(--text-muted); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                {{ $s->description }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code style="font-size: 0.82rem; background: var(--input-bg); padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid var(--border-color); color: var(--text-main);">
                                    {{ $s->email }}
                                </code>
                            </td>
                            <td>
                                @if($s->reply_to_email)
                                    <span style="font-size: 0.8rem; color: var(--text-main);">{{ $s->reply_to_email }}</span>
                                @else
                                    <span style="color: var(--text-subtle); font-size: 0.78rem;">{{ __('Same as sender') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($s->connection)
                                    <span style="font-size: 0.82rem; color: var(--text-main);">
                                        <i class="fa-solid fa-server" style="color: #f59e0b; margin-right: 0.25rem;"></i>
                                        {{ $s->connection->name }}
                                    </span>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.78rem;">
                                        {{ __('Default Connection') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $s->is_active ? 'badge-emerald' : 'badge-amber' }}">
                                    <i class="fa-solid {{ $s->is_active ? 'fa-check' : 'fa-pause' }}"></i>
                                    {{ $s->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-indigo">
                                    {{ $s->routing_rules_count }} {{ __('categories') }}
                                </span>
                            </td>
                            <td>
                                <strong style="color: var(--text-main);">{{ number_format($s->logs_count) }}</strong>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                    <button @click="openEdit({{ json_encode($s) }})" 
                                            class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.7rem; border-radius: 8px;" title="Edit Sender">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <form action="{{ route('superadmin.emails.senders.toggle', $s) }}" method="POST" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.7rem; border-radius: 8px;" title="{{ $s->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="fa-solid {{ $s->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                        </button>
                                    </form>

                                    @if(!$s->is_default)
                                        <form action="{{ route('superadmin.emails.senders.destroy', $s) }}" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this sender identity? Logs will be preserved.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.7rem; border-radius: 8px; color: #ef4444;" title="Delete Sender">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                {{ __('No sender identities configured yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Sender Modal -->
    <div x-show="createModal" class="modern-modal-overlay" style="display: none;" @keydown.escape.window="createModal = false">
        <div class="modern-modal-box" @click.away="createModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    <i class="fa-solid fa-plus-circle" style="color: #10b981;"></i> {{ __('Add Sender Identity') }}
                </h3>
                <button @click="createModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <form action="{{ route('superadmin.emails.senders.store') }}" method="POST">
                @csrf
                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Display Name') }} *</label>
                    <input type="text" name="name" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. QRMenu Notifications" required>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('From Email Address') }} *</label>
                    <input type="email" name="email" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. no-reply@menu.elab.am" required>
                </div>

                <div class="grid-2" style="margin-bottom: 1.15rem;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Reply-To Email') }}</label>
                        <input type="email" name="reply_to_email" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. support@menu.elab.am">
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Reply-To Name') }}</label>
                        <input type="text" name="reply_to_name" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. Support Team">
                    </div>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Dedicated Connection (Optional)') }}</label>
                    <select name="email_connection_id" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                        <option value="">{{ __('Use System Default Connection') }}</option>
                        @foreach($connections as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ strtoupper($c->transport) }})</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Description') }}</label>
                    <textarea name="description" class="form-textarea" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" rows="2" placeholder="Internal notes regarding this sender identity..."></textarea>
                </div>

                <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
                    <input type="checkbox" name="is_default" value="1" id="create_is_default" style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <label for="create_is_default" style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); cursor: pointer;">
                        {{ __('Set as primary default sender identity') }}
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="createModal = false" class="btn btn-secondary" style="border-radius: 10px; padding: 0.6rem 1.25rem;">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; padding: 0.6rem 1.5rem;">{{ __('Save Sender') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Sender Modal -->
    <div x-show="editModal" class="modern-modal-overlay" style="display: none;" @keydown.escape.window="editModal = false">
        <div class="modern-modal-box" @click.away="editModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    <i class="fa-solid fa-pen-to-square" style="color: #3b82f6;"></i> {{ __('Edit Sender Identity') }}
                </h3>
                <button @click="editModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <form :action="'{{ url('superadmin/emails/senders') }}/' + activeSender.id" method="POST">
                @csrf
                @method('PUT')
                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Display Name') }} *</label>
                    <input type="text" name="name" x-model="activeSender.name" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" required>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('From Email Address') }} *</label>
                    <input type="email" name="email" x-model="activeSender.email" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" required>
                </div>

                <div class="grid-2" style="margin-bottom: 1.15rem;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Reply-To Email') }}</label>
                        <input type="email" name="reply_to_email" x-model="activeSender.reply_to_email" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Reply-To Name') }}</label>
                        <input type="text" name="reply_to_name" x-model="activeSender.reply_to_name" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                    </div>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Dedicated Connection (Optional)') }}</label>
                    <select name="email_connection_id" x-model="activeSender.email_connection_id" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                        <option value="">{{ __('Use System Default Connection') }}</option>
                        @foreach($connections as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ strtoupper($c->transport) }})</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Description') }}</label>
                    <textarea name="description" x-model="activeSender.description" class="form-textarea" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" rows="2"></textarea>
                </div>

                <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
                    <input type="checkbox" name="is_default" value="1" x-model="activeSender.is_default" id="edit_is_default" style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <label for="edit_is_default" style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); cursor: pointer;">
                        {{ __('Set as primary default sender identity') }}
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="editModal = false" class="btn btn-secondary" style="border-radius: 10px; padding: 0.6rem 1.25rem;">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; padding: 0.6rem 1.5rem;">{{ __('Update Sender') }}</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
