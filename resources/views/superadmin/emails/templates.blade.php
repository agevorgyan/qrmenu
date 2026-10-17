@extends('layouts.app')

@section('title', 'Email Templates - QRMenu SuperAdmin')

@section('styles')
<style>
    .superadmin-hero {
        background: linear-gradient(135deg, rgba(236, 72, 153, 0.12) 0%, rgba(99, 102, 241, 0.08) 100%);
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
    .var-chip {
        display: inline-block;
        font-family: monospace;
        font-size: 0.72rem;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        padding: 0.15rem 0.45rem;
        border-radius: 6px;
        color: var(--primary);
        cursor: pointer;
        user-select: all;
    }
</style>
@endsection

@section('content')
<div x-data="{
    editModal: false,
    previewModal: false,
    previewLoading: false,
    previewData: null,
    activeTemplate: {
        id: null,
        key: '',
        name: '',
        category: '',
        subject: '',
        intro_text: '',
        cta_text: '',
        cta_url_template: '',
        footer_text: '',
        support_contact: '',
        is_active: true,
        allowed_variables: []
    },
    openEdit(tpl) {
        this.activeTemplate = {
            id: tpl.id,
            key: tpl.key,
            name: tpl.name,
            category: tpl.category,
            subject: tpl.subject,
            intro_text: tpl.intro_text || '',
            cta_text: tpl.cta_text || '',
            cta_url_template: tpl.cta_url_template || '',
            footer_text: tpl.footer_text || '',
            support_contact: tpl.support_contact || '',
            is_active: Boolean(tpl.is_active),
            allowed_variables: tpl.allowed_variables || []
        };
        this.editModal = true;
    },
    async openPreview(tplId) {
        this.previewLoading = true;
        this.previewModal = true;
        this.previewData = null;

        try {
            const resp = await fetch('{{ url('superadmin/emails/templates') }}/' + tplId + '/preview');
            this.previewData = await resp.json();
        } catch (err) {
            alert('Failed to load preview');
        } finally {
            this.previewLoading = false;
        }
    }
}">

    @include('superadmin.emails._nav', [
        'title' => __('Structured Email Templates'),
        'subtitle' => __('Manage subjects, introductions, call-to-actions, and footers without exposing arbitrary PHP execution.')
    ])

    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 0.9rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; font-weight: 600;">
            <i class="fa-solid fa-circle-check"></i> {{ __(session('success')) }}
        </div>
    @endif

    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                {{ __('Managed System Email Templates') }} ({{ $templates->count() }})
            </h3>
            <p style="font-size: 0.82rem; color: var(--text-muted);">
                {{ __('Placeholders like') }} <code>&#123;&#123; user_name &#125;&#125;</code> {{ __('are securely sanitized and bound during rendering.') }}
            </p>
        </div>

        <div class="responsive-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Template Name') }}</th>
                        <th>{{ __('Key') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Subject Line') }}</th>
                        <th>{{ __('Version') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($templates as $tpl)
                        <tr>
                            <td>
                                <strong style="color: var(--text-main); font-size: 0.88rem;">{{ $tpl->name }}</strong>
                            </td>
                            <td>
                                <code style="font-size: 0.78rem; background: var(--input-bg); padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid var(--border-color); color: var(--text-main);">
                                    {{ $tpl->key }}
                                </code>
                            </td>
                            <td>
                                <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                    {{ str_replace('_', ' ', $tpl->category) }}
                                </span>
                            </td>
                            <td style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $tpl->subject }}
                            </td>
                            <td>
                                <span class="badge badge-indigo">v{{ $tpl->version }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $tpl->is_active ? 'badge-emerald' : 'badge-amber' }}">
                                    <i class="fa-solid {{ $tpl->is_active ? 'fa-check' : 'fa-pause' }}"></i>
                                    {{ $tpl->is_active ? __('Active') : __('Disabled') }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                    <button @click="openPreview({{ $tpl->id }})" 
                                            class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.7rem; border-radius: 8px;" title="Live Preview">
                                        <i class="fa-solid fa-eye"></i> {{ __('Preview') }}
                                    </button>
                                    <button @click="openEdit({{ json_encode($tpl) }})" 
                                            class="btn btn-primary" style="font-size: 0.75rem; padding: 0.35rem 0.7rem; border-radius: 8px;" title="Edit Content">
                                        <i class="fa-solid fa-pen-to-square"></i> {{ __('Edit') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Template Modal -->
    <div x-show="editModal" class="modern-modal-overlay" style="display: none;" @keydown.escape.window="editModal = false">
        <div class="modern-modal-box" style="max-width: 700px;" @click.away="editModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    <i class="fa-solid fa-envelope-open-text" style="color: #ec4899;"></i> {{ __('Edit Template Content') }}
                </h3>
                <button @click="editModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <!-- Allowed Variables Helper -->
            <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.25rem;">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem;">
                    {{ __('Supported Variables (Click to copy):') }}
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                    <template x-for="v in activeTemplate.allowed_variables" :key="v">
                        <span class="var-chip" x-text="'@{{ ' + v + ' }}'"></span>
                    </template>
                </div>
            </div>

            <form :action="'{{ url('superadmin/emails/templates') }}/' + activeTemplate.id" method="POST">
                @csrf
                @method('PUT')

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Subject Line') }} *</label>
                    <input type="text" name="subject" x-model="activeTemplate.subject" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" required>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Introductory Body Text') }}</label>
                    <textarea name="intro_text" x-model="activeTemplate.intro_text" class="form-textarea" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" rows="4"></textarea>
                </div>

                <div class="grid-2" style="margin-bottom: 1.15rem;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('CTA Button Label') }}</label>
                        <input type="text" name="cta_text" x-model="activeTemplate.cta_text" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('CTA Destination URL Template') }}</label>
                        <input type="text" name="cta_url_template" x-model="activeTemplate.cta_url_template" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. @{{ verification_url }}">
                    </div>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Footer Content / Disclaimer') }}</label>
                    <textarea name="footer_text" x-model="activeTemplate.footer_text" class="form-textarea" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" rows="2"></textarea>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">{{ __('Support Contact Email') }}</label>
                    <input type="email" name="support_contact" x-model="activeTemplate.support_contact" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;">
                </div>

                <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
                    <input type="checkbox" name="is_active" value="1" x-model="activeTemplate.is_active" id="tpl_active" style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <label for="tpl_active" style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); cursor: pointer;">
                        {{ __('Active and enabled in production mail flow') }}
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="editModal = false" class="btn btn-secondary" style="border-radius: 10px; padding: 0.6rem 1.25rem;">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; padding: 0.6rem 1.5rem;">{{ __('Save Changes') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Live Preview Modal -->
    <div x-show="previewModal" class="modern-modal-overlay" style="display: none;" @keydown.escape.window="previewModal = false">
        <div class="modern-modal-box" style="max-width: 600px;" @click.away="previewModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main);">
                    <i class="fa-solid fa-eye" style="color: #06b6d4;"></i> {{ __('Template Preview') }}
                </h3>
                <button @click="previewModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <div x-show="previewLoading" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.5rem;"></i> {{ __('Rendering preview with mock data...') }}
            </div>

            <div x-show="!previewLoading && previewData" style="background: #ffffff; color: #0f172a; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 16px;">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #64748b;">Subject</div>
                    <div style="font-size: 16px; font-weight: 800; color: #0f172a;" x-text="previewData ? previewData.preview.subject : ''"></div>
                </div>

                <div style="font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 20px; white-space: pre-line;" x-text="previewData ? previewData.preview.intro : ''"></div>

                <div x-show="previewData && previewData.preview.cta_text" style="text-align: center; margin: 24px 0;">
                    <span style="display: inline-block; padding: 12px 24px; background: #e11d48; color: #ffffff; font-weight: 700; border-radius: 9999px; font-size: 14px; text-decoration: none;" x-text="previewData ? previewData.preview.cta_text : ''"></span>
                </div>

                <div style="border-top: 1px solid #f1f5f9; padding-top: 12px; font-size: 12px; color: #64748b; line-height: 1.5;" x-text="previewData ? previewData.preview.footer : ''"></div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
                <button type="button" @click="previewModal = false" class="btn btn-secondary" style="border-radius: 10px; padding: 0.5rem 1.25rem;">{{ __('Close') }}</button>
            </div>
        </div>
    </div>

</div>
@endsection
