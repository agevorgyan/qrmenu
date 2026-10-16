@extends('layouts.app')

@section('title', 'Email Routing Rules - QRMenu SuperAdmin')

@section('styles')
<style>
    .superadmin-hero {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.12) 0%, rgba(59, 130, 246, 0.08) 100%);
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
    editModal: false,
    activeRule: {
        id: null,
        name: '',
        category: '',
        sender_identity_id: '',
        email_connection_id: '',
        fallback_behavior: 'default_sender',
        is_active: true
    },
    openEdit(rule) {
        this.activeRule = {
            id: rule.id,
            name: rule.name,
            category: rule.category,
            sender_identity_id: rule.sender_identity_id || '',
            email_connection_id: rule.email_connection_id || '',
            fallback_behavior: rule.fallback_behavior || 'default_sender',
            is_active: Boolean(rule.is_active)
        };
        this.editModal = true;
    }
}">

    @include('superadmin.emails._nav', [
        'title' => __('Category-Based Email Routing'),
        'subtitle' => __('Map business email events to dedicated sender identities, fallback policies, and isolated connections.')
    ])

    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 0.9rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; font-weight: 600;">
            <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
        </div>
    @endif

    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                {{ __('Active Email Routing Matrix') }}
            </h3>
            <p style="font-size: 0.82rem; color: var(--text-muted);">
                {{ __('If a category has no dedicated connection assigned, it automatically inherits the primary default SMTP connection.') }}
            </p>
        </div>

        <div class="responsive-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Assigned Sender Identity') }}</th>
                        <th>{{ __('Assigned Transport Connection') }}</th>
                        <th>{{ __('Fallback Behavior') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rules as $r)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <div style="width: 34px; height: 34px; border-radius: 10px; background: rgba(139, 92, 246, 0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 800; flex-shrink: 0;">
                                        <i class="fa-solid fa-route"></i>
                                    </div>
                                    <div>
                                        <strong style="color: var(--text-main); font-size: 0.88rem;">{{ $r->name }}</strong>
                                        <div style="font-size: 0.72rem; color: var(--text-muted); font-family: monospace;">
                                            {{ $r->category }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($r->sender)
                                    <div>
                                        <strong style="color: var(--text-main); font-size: 0.82rem;">{{ $r->sender->name }}</strong>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $r->sender->email }}</div>
                                    </div>
                                @else
                                    <span class="badge badge-amber">
                                        <i class="fa-solid fa-arrow-turn-down"></i> {{ __('Default Sender Fallback') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($r->connection)
                                    <div>
                                        <strong style="color: var(--text-main); font-size: 0.82rem;">{{ $r->connection->name }}</strong>
                                        <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">{{ $r->connection->transport }} &bull; {{ $r->connection->host }}</div>
                                    </div>
                                @else
                                    <span class="badge badge-indigo">
                                        <i class="fa-solid fa-server"></i> {{ __('Default Connection') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">
                                    {{ ucfirst(str_replace('_', ' ', $r->fallback_behavior)) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $r->is_active ? 'badge-emerald' : 'badge-amber' }}">
                                    <i class="fa-solid {{ $r->is_active ? 'fa-check' : 'fa-pause' }}"></i>
                                    {{ $r->is_active ? __('Active') : __('Disabled') }}
                                </span>
                            </td>
                            <td>
                                <button @click="openEdit({{ json_encode($r) }})" 
                                        class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 8px;">
                                    <i class="fa-solid fa-pen-to-square"></i> {{ __('Configure') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Routing Modal -->
    <div x-show="editModal" class="modern-modal-overlay" style="display: none;" @keydown.escape.window="editModal = false">
        <div class="modern-modal-box" @click.away="editModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    <i class="fa-solid fa-sliders" style="color: #8b5cf6;"></i> {{ __('Configure Routing Rule') }}
                </h3>
                <button @click="editModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem;">
                {{ __('Category:') }} <strong style="color: var(--text-main);" x-text="activeRule.name"></strong>
                <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: 0.4rem;">(<code x-text="activeRule.category"></code>)</span>
            </div>

            <form :action="'{{ url('superadmin/emails/routing') }}/' + activeRule.id" method="POST">
                @csrf
                @method('PUT')

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Assigned Sender Identity') }}
                    </label>
                    <select name="sender_identity_id" x-model="activeRule.sender_identity_id" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                        <option value="">{{ __('Inherit Default System Sender') }}</option>
                        @foreach($senders as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} &lt;{{ $s->email }}&gt;</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Assigned SMTP Connection') }}
                    </label>
                    <select name="email_connection_id" x-model="activeRule.email_connection_id" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                        <option value="">{{ __('Inherit Default System Connection') }}</option>
                        @foreach($connections as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ strtoupper($c->transport) }})</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Fallback Policy') }}
                    </label>
                    <select name="fallback_behavior" x-model="activeRule.fallback_behavior" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                        <option value="default_sender">{{ __('Fallback to default active sender & connection') }}</option>
                        <option value="system_fallback">{{ __('Fallback to .env system mail configuration') }}</option>
                        <option value="fail">{{ __('Fail and log error if assigned sender is offline') }}</option>
                    </select>
                </div>

                <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
                    <input type="checkbox" name="is_active" value="1" x-model="activeRule.is_active" id="edit_rule_active" style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <label for="edit_rule_active" style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); cursor: pointer;">
                        {{ __('Rule active') }}
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="editModal = false" class="btn btn-secondary" style="border-radius: 10px; padding: 0.6rem 1.25rem;">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; padding: 0.6rem 1.5rem;">{{ __('Save Rule') }}</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
