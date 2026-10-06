@extends('layouts.app')

@section('title', 'Platform Languages & i18n - QRMenu SaaS')

@section('content')
<div class="superadmin-hero" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.12) 0%, rgba(59, 130, 246, 0.08) 100%); border-radius: 24px; padding: 1.75rem 2rem; margin-bottom: 2rem; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem;">
                <i class="fa-solid fa-language"></i>
            </div>
            <div>
                <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Platform Language Management') }}
                </h1>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.2rem 0 0 0;">
                    {{ __('Manage system-wide supported locales, default application language, and translation availability.') }}
                </p>
            </div>
        </div>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('addLangModal').style.display='flex';" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; font-weight: 700; border-radius: 12px;">
            <i class="fa-solid fa-plus"></i> {{ __('Register Language') }}
        </button>
    </div>
</div>

@if(session('success'))
    <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fa-solid fa-circle-check"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if($errors->any())
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem;">
        <div style="font-weight: 700; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ __('Please correct the following errors:') }}</span>
        </div>
        <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- Stats Overview -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <div class="card" style="padding: 1.25rem 1.5rem;">
        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Configured Locales') }}</span>
        <div style="font-size: 1.85rem; font-weight: 900; color: var(--text-main); margin-top: 0.35rem;">{{ $stats['total'] }}</div>
    </div>
    <div class="card" style="padding: 1.25rem 1.5rem;">
        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Active Locales') }}</span>
        <div style="font-size: 1.85rem; font-weight: 900; color: #10b981; margin-top: 0.35rem;">{{ $stats['active'] }}</div>
    </div>
    <div class="card" style="padding: 1.25rem 1.5rem;">
        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('System Default') }}</span>
        <div style="font-size: 1.35rem; font-weight: 800; color: #8b5cf6; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-star" style="font-size: 1rem; color: #f59e0b;"></i> {{ $stats['default'] }}
        </div>
    </div>
    <div class="card" style="padding: 1.25rem 1.5rem;">
        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Translations') }}</span>
        <div style="font-size: 1.85rem; font-weight: 900; color: var(--text-main); margin-top: 0.35rem;">{{ number_format($stats['translations_count']) }}</div>
    </div>
</div>

<!-- Languages Table Card -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0; color: var(--text-main);">
            {{ __('Registered Language Registry') }}
        </h3>
        <span style="font-size: 0.82rem; color: var(--text-muted);">
            {{ __('Adding a language makes it immediately selectable by tenant restaurants.') }}
        </span>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
            <thead>
                <tr style="background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <th style="padding: 1rem 1.5rem;">{{ __('Language') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Code') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Direction') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Status') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Translated Content') }}</th>
                    <th style="padding: 1rem 1.5rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($languages as $lang)
                    <tr style="border-bottom: 1px solid var(--table-row-border); transition: background-color 0.15s ease;">
                        <td style="padding: 1rem 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.85rem;">
                                <span style="font-size: 1.6rem; line-height: 1;">{{ $lang->flag ?? '🌐' }}</span>
                                <div>
                                    <div style="font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                                        {{ $lang->name }}
                                        @if($lang->is_default)
                                            <span class="badge badge-amber" style="font-size: 0.68rem; padding: 0.15rem 0.5rem;">
                                                <i class="fa-solid fa-star"></i> {{ __('Default') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                                        {{ $lang->native_name }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 1rem 1rem;">
                            <code style="background: rgba(255,255,255,0.06); padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 700; color: var(--text-main);">
                                {{ $lang->code }}
                            </code>
                        </td>
                        <td style="padding: 1rem 1rem;">
                            <span style="font-size: 0.78rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted);">
                                {{ $lang->direction }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1rem;">
                            @if($lang->is_active)
                                <span class="badge badge-emerald" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                    {{ __('Active') }}
                                </span>
                            @else
                                <span class="badge" style="background: rgba(100, 116, 139, 0.2); color: #94a3b8; font-size: 0.75rem; padding: 0.25rem 0.6rem;">
                                    {{ __('Inactive') }}
                                </span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1rem;">
                            <span style="font-weight: 700; color: var(--text-main);">
                                {{ number_format($translationCounts[$lang->code] ?? 0) }}
                            </span>
                            <span style="font-size: 0.78rem; color: var(--text-muted);">{{ __('items') }}</span>
                        </td>
                        <td style="padding: 1rem 1.5rem; text-align: right;">
                            <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                @if(! $lang->is_default)
                                    <form action="{{ route('superadmin.languages.default', $lang) }}" method="POST" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn" style="padding: 0.4rem 0.75rem; font-size: 0.78rem; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #f59e0b;" title="{{ __('Set as Default System Language') }}">
                                            <i class="fa-regular fa-star"></i> {{ __('Make Default') }}
                                        </button>
                                    </form>

                                    <form action="{{ route('superadmin.languages.toggle', $lang) }}" method="POST" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn" style="padding: 0.4rem 0.75rem; font-size: 0.78rem; {{ $lang->is_active ? 'color: #ef4444; border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.08);' : 'color: #10b981; border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.08);' }}">
                                            {{ $lang->is_active ? __('Deactivate') : __('Activate') }}
                                        </button>
                                    </form>
                                @endif

                                <button type="button" class="btn" onclick="openEditModal({{ json_encode($lang) }})" style="padding: 0.4rem 0.75rem; font-size: 0.78rem; border-color: var(--border-color);">
                                    <i class="fa-solid fa-pen-to-square"></i> {{ __('Edit') }}
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            {{ __('No languages configured.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Language -->
<div id="addLangModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="width: 100%; max-width: 520px; padding: 2rem; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--text-main);">
                {{ __('Register New Language') }}
            </h3>
            <button type="button" onclick="document.getElementById('addLangModal').style.display='none';" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="{{ route('superadmin.languages.store') }}" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                        {{ __('ISO Code (e.g. fr, de, es)') }} *
                    </label>
                    <input type="text" name="code" required maxlength="10" placeholder="e.g. es" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                        {{ __('Flag Emoji') }}
                    </label>
                    <input type="text" name="flag" maxlength="10" placeholder="e.g. 🇪🇸" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                    {{ __('English Name') }} *
                </label>
                <input type="text" name="name" required maxlength="60" placeholder="e.g. Spanish" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                    {{ __('Native Name') }} *
                </label>
                <input type="text" name="native_name" required maxlength="60" placeholder="e.g. Español" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                        {{ __('Text Direction') }}
                    </label>
                    <select name="direction" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
                        <option value="ltr">LTR (Left to Right)</option>
                        <option value="rtl">RTL (Right to Left)</option>
                    </select>
                </div>
                <div style="display: flex; align-items: center; margin-top: 1.4rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; font-weight: 600; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" name="is_active" value="1" checked style="width: 18px; height: 18px; accent-color: #8b5cf6;">
                        {{ __('Active Immediately') }}
                    </label>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('addLangModal').style.display='none';" class="btn" style="border-color: var(--border-color);">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.25rem;">
                    <i class="fa-solid fa-check"></i> {{ __('Save & Enable') }}
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Language -->
<div id="editLangModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="width: 100%; max-width: 520px; padding: 2rem; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--text-main);">
                {{ __('Edit Language') }}
            </h3>
            <button type="button" onclick="document.getElementById('editLangModal').style.display='none';" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="editLangForm" method="POST">
            @csrf
            @method('PUT')
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                        {{ __('ISO Code') }}
                    </label>
                    <input type="text" id="edit_code" disabled class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: rgba(255,255,255,0.04); color: var(--text-muted);">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                        {{ __('Flag Emoji') }}
                    </label>
                    <input type="text" id="edit_flag" name="flag" maxlength="10" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                    {{ __('English Name') }} *
                </label>
                <input type="text" id="edit_name" name="name" required maxlength="60" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                    {{ __('Native Name') }} *
                </label>
                <input type="text" id="edit_native_name" name="native_name" required maxlength="60" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                    {{ __('Text Direction') }}
                </label>
                <select id="edit_direction" name="direction" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
                    <option value="ltr">LTR (Left to Right)</option>
                    <option value="rtl">RTL (Right to Left)</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('editLangModal').style.display='none';" class="btn" style="border-color: var(--border-color);">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.25rem;">
                    <i class="fa-solid fa-check"></i> {{ __('Update') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(lang) {
    document.getElementById('edit_code').value = lang.code;
    document.getElementById('edit_flag').value = lang.flag || '';
    document.getElementById('edit_name').value = lang.name;
    document.getElementById('edit_native_name').value = lang.native_name;
    document.getElementById('edit_direction').value = lang.direction || 'ltr';
    document.getElementById('editLangForm').action = "{{ url('superadmin/languages') }}/" + lang.id;
    document.getElementById('editLangModal').style.display = 'flex';
}
</script>
@endsection
