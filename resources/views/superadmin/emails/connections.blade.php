@extends('layouts.app')

@section('title', 'SMTP Connections - QRMenu SuperAdmin')

@section('styles')
<style>
    .superadmin-hero {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, rgba(59, 130, 246, 0.08) 100%);
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
    testEmailModal: false,
    testingConnectionId: null,
    testStatusMessage: '',
    testStatusSuccess: null,
    activeConn: {
        id: null,
        name: '',
        transport: 'smtp',
        host: '',
        port: 587,
        encryption: 'tls',
        username: '',
        password: '',
        timeout: 15,
        is_default: false,
        is_active: true
    },
    testEmailData: {
        connection_id: null,
        connection_name: '',
        recipient: '',
        notes: ''
    },
    sendingTestEmail: false,
    testEmailResult: null,

    openEdit(conn) {
        this.activeConn = {
            id: conn.id,
            name: conn.name,
            transport: conn.transport,
            host: conn.host || '',
            port: conn.port,
            encryption: conn.encryption || 'tls',
            username: conn.username || '',
            password: '',
            timeout: conn.timeout || 15,
            is_default: Boolean(conn.is_default),
            is_active: Boolean(conn.is_active)
        };
        this.editModal = true;
    },

    openTestEmail(conn) {
        this.testEmailData.connection_id = conn.id;
        this.testEmailData.connection_name = conn.name;
        this.testEmailData.recipient = '';
        this.testEmailData.notes = '';
        this.testEmailResult = null;
        this.testEmailModal = true;
    },

    async runSocketTest(connId) {
        this.testingConnectionId = connId;
        this.testStatusMessage = 'Connecting and authenticating...';
        this.testStatusSuccess = null;

        try {
            const resp = await fetch('{{ url('superadmin/emails/connections') }}/' + connId + '/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await resp.json();
            this.testStatusSuccess = data.success;
            this.testStatusMessage = data.message;
        } catch (err) {
            this.testStatusSuccess = false;
            this.testStatusMessage = 'Network or server error during connection test.';
        } finally {
            this.testingConnectionId = null;
        }
    },

    async dispatchTestEmail() {
        if (!this.testEmailData.recipient) {
            alert('Please specify a recipient email address.');
            return;
        }

        this.sendingTestEmail = true;
        this.testEmailResult = null;

        try {
            const resp = await fetch('{{ url('superadmin/emails/connections') }}/' + this.testEmailData.connection_id + '/send-test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    recipient: this.testEmailData.recipient,
                    notes: this.testEmailData.notes
                })
            });
            const data = await resp.json();
            this.testEmailResult = data;
        } catch (err) {
            this.testEmailResult = {
                success: false,
                message: 'Failed to send test email due to server or network error.'
            };
        } finally {
            this.sendingTestEmail = false;
        }
    }
}">

    @include('superadmin.emails._nav', [
        'title' => __('SMTP Connections & Delivery Transports'),
        'subtitle' => __('Configure secure SMTP servers, sendmail, timeouts, and live socket diagnostics.')
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

    <!-- Test Feedback Banner -->
    <div x-show="testStatusMessage" style="display: none;" 
         :style="testStatusSuccess ? 'background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981;' : (testStatusSuccess === false ? 'background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444;' : 'background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3); color: #3b82f6;')"
         class="stat-kpi-card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 14px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; font-weight: 600;">
                <i :class="testStatusSuccess ? 'fa-solid fa-circle-check' : (testStatusSuccess === false ? 'fa-solid fa-circle-xmark' : 'fa-solid fa-spinner fa-spin')"></i>
                <span x-text="testStatusMessage"></span>
            </div>
            <button @click="testStatusMessage = ''" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 1.1rem;">&times;</button>
        </div>
    </div>

    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                {{ __('Configured SMTP Connections') }} ({{ $connections->count() }})
            </h2>
            <p style="font-size: 0.82rem; color: var(--text-muted);">
                {{ __('Passwords are encrypted using AES-256 and never transmitted to the browser in cleartext.') }}
            </p>
        </div>

        <button @click="createModal = true" class="btn btn-primary" style="border-radius: 12px; font-size: 0.84rem; padding: 0.65rem 1.25rem;">
            <i class="fa-solid fa-plus"></i> {{ __('Add Connection') }}
        </button>
    </div>

    <!-- Connections Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div class="responsive-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Connection Name') }}</th>
                        <th>{{ __('Transport') }}</th>
                        <th>{{ __('Host : Port') }}</th>
                        <th>{{ __('Encryption') }}</th>
                        <th>{{ __('Username') }}</th>
                        <th>{{ __('Password') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Last Tested') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($connections as $c)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.6rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 800; flex-shrink: 0;">
                                        <i class="fa-solid fa-server"></i>
                                    </div>
                                    <div>
                                        <strong style="color: var(--text-main); font-size: 0.9rem;">{{ $c->name }}</strong>
                                        @if($c->is_default)
                                            <span class="badge badge-amber" style="margin-left: 0.35rem;">
                                                <i class="fa-solid fa-star"></i> {{ __('Default') }}
                                            </span>
                                        @endif
                                        <div style="font-size: 0.72rem; color: var(--text-muted);">
                                            {{ $c->senders_count }} {{ __('senders') }} &bull; {{ $c->logs_count }} {{ __('dispatches') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-indigo">
                                    {{ strtoupper($c->transport) }}
                                </span>
                            </td>
                            <td>
                                <code style="font-size: 0.82rem; background: var(--input-bg); padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid var(--border-color); color: var(--text-main);">
                                    {{ $c->host ?? 'localhost' }}:{{ $c->port }}
                                </code>
                            </td>
                            <td>
                                <span style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">
                                    {{ $c->encryption ?? 'none' }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.8rem; color: var(--text-main);">
                                    {{ $c->username ?: '—' }}
                                </span>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 0.85rem; color: var(--text-muted);" title="Protected credential">
                                    {{ $c->maskedPassword() ?: 'None' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $c->is_active ? 'badge-emerald' : 'badge-amber' }}">
                                    <i class="fa-solid {{ $c->is_active ? 'fa-check' : 'fa-pause' }}"></i>
                                    {{ $c->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td>
                                @if($c->last_tested_at)
                                    <div style="font-size: 0.78rem; color: var(--text-main);">{{ $c->last_tested_at->diffForHumans() }}</div>
                                    <div style="font-size: 0.7rem; color: {{ $c->last_test_status === 'success' ? '#10b981' : '#ef4444' }}; font-weight: 700;">
                                        <i class="fa-solid {{ $c->last_test_status === 'success' ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>
                                        {{ ucfirst($c->last_test_status) }}
                                    </div>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.78rem;">{{ __('Never tested') }}</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.35rem;">
                                    <!-- Test Connection Action -->
                                    <button @click="runSocketTest({{ $c->id }})" 
                                            class="btn btn-secondary" 
                                            :disabled="testingConnectionId === {{ $c->id }}"
                                            style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 8px;" title="Test Socket & Auth (No Email Sent)">
                                        <i class="fa-solid" :class="testingConnectionId === {{ $c->id }} ? 'fa-spinner fa-spin' : 'fa-vial'"></i>
                                    </button>

                                    <!-- Send Test Email Action -->
                                    <button @click="openTestEmail({{ json_encode($c) }})" 
                                            class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 8px; color: #3b82f6;" title="Send Live Test Email">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </button>

                                    <!-- Edit Action -->
                                    <button @click="openEdit({{ json_encode($c) }})" 
                                            class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 8px;" title="Edit Connection">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    @if(!$c->is_default)
                                        <!-- Set as Default -->
                                        <form action="{{ route('superadmin.emails.connections.default', $c) }}" method="POST" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 8px;" title="Set as Primary Default">
                                                <i class="fa-regular fa-star"></i>
                                            </button>
                                        </form>

                                        <!-- Delete Connection -->
                                        <form action="{{ route('superadmin.emails.connections.destroy', $c) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this SMTP connection?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 8px; color: #ef4444;" title="Delete Connection">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                {{ __('No SMTP connections configured yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Connection Modal -->
    <div x-show="createModal" class="modern-modal-overlay" style="display: none;" @keydown.escape.window="createModal = false">
        <div class="modern-modal-box" @click.away="createModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    <i class="fa-solid fa-plus-circle" style="color: #f59e0b;"></i> {{ __('Add SMTP Connection') }}
                </h3>
                <button @click="createModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <form action="{{ route('superadmin.emails.connections.store') }}" method="POST">
                @csrf
                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Connection Name') }} *</label>
                    <input type="text" name="name" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. Primary cPanel Shared SMTP" required>
                </div>

                <div class="grid-2" style="margin-bottom: 1.15rem;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Transport Type') }} *</label>
                        <select name="transport" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                            <option value="smtp">SMTP</option>
                            <option value="sendmail">Sendmail</option>
                            <option value="log">Log (Testing)</option>
                            <option value="array">Array (In-memory testing)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Timeout (Seconds)') }}</label>
                        <input type="number" name="timeout" value="15" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" min="2" max="120" required>
                    </div>
                </div>

                <div class="grid-2" style="margin-bottom: 1.15rem;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('SMTP Host') }}</label>
                        <input type="text" name="host" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. mail.menu.elab.am">
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Port') }} *</label>
                        <input type="number" name="port" value="587" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" required>
                    </div>
                </div>

                <div class="grid-2" style="margin-bottom: 1.15rem;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Encryption') }}</label>
                        <select name="encryption" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                            <option value="tls">TLS (STARTTLS - Port 587)</option>
                            <option value="ssl">SSL / SMTPS (Port 465)</option>
                            <option value="none">None (Insecure / Port 25)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Username') }}</label>
                        <input type="text" name="username" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. no-reply@menu.elab.am">
                    </div>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Password / Secret') }}</label>
                    <input type="password" name="password" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="••••••••••••" autocomplete="new-password">
                </div>

                <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
                    <input type="checkbox" name="is_default" value="1" id="create_conn_default" style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <label for="create_conn_default" style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); cursor: pointer;">
                        {{ __('Set as primary default system connection') }}
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="createModal = false" class="btn btn-secondary" style="border-radius: 10px; padding: 0.6rem 1.25rem;">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; padding: 0.6rem 1.5rem;">{{ __('Save Connection') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Connection Modal -->
    <div x-show="editModal" class="modern-modal-overlay" style="display: none;" @keydown.escape.window="editModal = false">
        <div class="modern-modal-box" @click.away="editModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    <i class="fa-solid fa-pen-to-square" style="color: #3b82f6;"></i> {{ __('Edit Connection Settings') }}
                </h3>
                <button @click="editModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <form :action="'{{ url('superadmin/emails/connections') }}/' + activeConn.id" method="POST">
                @csrf
                @method('PUT')
                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Connection Name') }} *</label>
                    <input type="text" name="name" x-model="activeConn.name" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" required>
                </div>

                <div class="grid-2" style="margin-bottom: 1.15rem;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Transport Type') }} *</label>
                        <select name="transport" x-model="activeConn.transport" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                            <option value="smtp">SMTP</option>
                            <option value="sendmail">Sendmail</option>
                            <option value="log">Log (Testing)</option>
                            <option value="array">Array (In-memory testing)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Timeout (Seconds)') }}</label>
                        <input type="number" name="timeout" x-model="activeConn.timeout" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" min="2" max="120" required>
                    </div>
                </div>

                <div class="grid-2" style="margin-bottom: 1.15rem;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('SMTP Host') }}</label>
                        <input type="text" name="host" x-model="activeConn.host" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Port') }} *</label>
                        <input type="number" name="port" x-model="activeConn.port" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" required>
                    </div>
                </div>

                <div class="grid-2" style="margin-bottom: 1.15rem;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Encryption') }}</label>
                        <select name="encryption" x-model="activeConn.encryption" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                            <option value="tls">TLS (STARTTLS - Port 587)</option>
                            <option value="ssl">SSL / SMTPS (Port 465)</option>
                            <option value="none">None (Insecure / Port 25)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Username') }}</label>
                        <input type="text" name="username" x-model="activeConn.username" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                    </div>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('New Password / Secret') }} <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">({{ __('Leave empty to keep current password') }})</span>
                    </label>
                    <input type="password" name="password" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="••••••••••••" autocomplete="new-password">
                </div>

                <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
                    <input type="checkbox" name="is_default" value="1" x-model="activeConn.is_default" id="edit_conn_default" style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <label for="edit_conn_default" style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); cursor: pointer;">
                        {{ __('Set as primary default system connection') }}
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="editModal = false" class="btn btn-secondary" style="border-radius: 10px; padding: 0.6rem 1.25rem;">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; padding: 0.6rem 1.5rem;">{{ __('Update Connection') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Send Live Test Email Modal -->
    <div x-show="testEmailModal" class="modern-modal-overlay" style="display: none;" @keydown.escape.window="testEmailModal = false">
        <div class="modern-modal-box" @click.away="testEmailModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    <i class="fa-solid fa-paper-plane" style="color: #3b82f6;"></i> {{ __('Send Live Test Email') }}
                </h3>
                <button @click="testEmailModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.84rem;">
                {{ __('Testing Connection:') }} <strong style="color: var(--text-main);" x-text="testEmailData.connection_name"></strong>
            </div>

            <!-- Result Box -->
            <div x-show="testEmailResult" style="display: none; margin-bottom: 1.25rem; padding: 0.9rem 1.15rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600;"
                 :style="testEmailResult && testEmailResult.success ? 'background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981;' : 'background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444;'">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i :class="testEmailResult && testEmailResult.success ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark'"></i>
                    <span x-text="testEmailResult ? testEmailResult.message : ''"></span>
                </div>
            </div>

            <div style="margin-bottom: 1.15rem;">
                <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Recipient Email Address') }} *
                </label>
                <input type="email" x-model="testEmailData.recipient" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. operator@elab.am" required>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Diagnostic Notes (Optional)') }}
                </label>
                <input type="text" x-model="testEmailData.notes" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. Pre-launch cPanel SMTP verification test">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" @click="testEmailModal = false" class="btn btn-secondary" style="border-radius: 10px; padding: 0.6rem 1.25rem;">{{ __('Close') }}</button>
                <button type="button" @click="dispatchTestEmail()" :disabled="sendingTestEmail" class="btn btn-primary" style="border-radius: 10px; padding: 0.6rem 1.5rem;">
                    <span x-show="!sendingTestEmail"><i class="fa-solid fa-paper-plane"></i> {{ __('Send Test') }}</span>
                    <span x-show="sendingTestEmail"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Dispatching...') }}</span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
