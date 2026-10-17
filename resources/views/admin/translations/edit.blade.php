@extends('layouts.app')

@section('title', __('Translation Editor') . ' - ' . ($translation->translatable?->name ?? 'Item'))

@section('content')
<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <a href="{{ route('admin.translations.index', ['locale' => $translation->locale]) }}" class="btn" style="border-color: var(--border-color); padding: 0.5rem 0.85rem;">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Back to List') }}
        </a>
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main);">
                {{ __('Translation Editor') }}: {{ $translation->translatable?->name ?? 'Item #' . $translation->translatable_id }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                {{ __('Field') }}: <code style="font-weight: 700; color: var(--text-main);">{{ $translation->field }}</code> | 
                {{ strtoupper($translation->source_locale) }} &rarr; <strong>{{ strtoupper($translation->locale) }}</strong>
            </p>
        </div>
    </div>

    <!-- Status Badges -->
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        @if($translation->status === 'published')
            <span class="badge badge-emerald" style="font-size: 0.82rem; padding: 0.4rem 0.85rem;">
                <i class="fa-solid fa-check-double"></i> {{ __('Published & Live') }}
            </span>
        @elseif($translation->status === 'approved')
            <span class="badge badge-amber" style="font-size: 0.82rem; padding: 0.4rem 0.85rem;">
                <i class="fa-solid fa-check"></i> {{ __('Approved (Pending Publish)') }}
            </span>
        @elseif($translation->status === 'draft')
            <span class="badge" style="background: rgba(148, 163, 184, 0.2); color: #94a3b8; font-size: 0.82rem; padding: 0.4rem 0.85rem;">
                <i class="fa-regular fa-file"></i> {{ __('Draft') }}
            </span>
        @elseif($translation->status === 'needs_review' || $translation->is_outdated)
            <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-size: 0.82rem; padding: 0.4rem 0.85rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Needs Review / Source Changed') }}
            </span>
        @else
            <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; font-size: 0.82rem; padding: 0.4rem 0.85rem;">
                {{ $translation->status }}
            </span>
        @endif
    </div>
</div>

@if(session('success'))
    <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fa-solid fa-circle-check"></i>
        <span>{{ __(session('success')) }}</span>
    </div>
@endif

@if(session('error'))
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>{{ __(session('error')) }}</span>
    </div>
@endif

@if($translation->error_message)
    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem;">
        <div style="font-weight: 700; margin-bottom: 0.25rem;"><i class="fa-solid fa-triangle-exclamation"></i> {{ __('Validation Warning / Issue') }}:</div>
        <div style="font-size: 0.88rem;">{{ $translation->error_message }}</div>
    </div>
@endif

<!-- AI Retranslation Proposal Banner (Section 7 Protected Rule) -->
@if(!empty($translation->metadata['ai_proposal']))
    @php $proposal = $translation->metadata['ai_proposal']; @endphp
    <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%); border: 1px solid rgba(99, 102, 241, 0.35); padding: 1.5rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="badge" style="background: #6366f1; color: #fff; font-size: 0.75rem; padding: 0.25rem 0.65rem;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('AI Proposed Retranslation') }}
                </span>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">
                    {{ __('Because this translation was already approved/published, the AI proposal was kept as a separate suggestion to protect the live menu from automatic overwrites.') }}
                </p>
                <div style="margin-top: 0.75rem; padding: 0.85rem 1rem; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 8px; font-weight: 600; color: var(--text-main);">
                    {{ $proposal['proposed_text'] ?? '' }}
                </div>
            </div>

            <form action="{{ route('admin.translations.action', $translation) }}" method="POST" style="margin: 0;">
                @csrf
                <input type="hidden" name="action" value="apply_proposal">
                <button type="submit" class="btn btn-primary" style="font-weight: 700;">
                    <i class="fa-solid fa-check"></i> {{ __('Apply Proposal into Editor') }}
                </button>
            </form>
        </div>
    </div>
@endif

<!-- Two Column Layout: Editor & Version History -->
<div style="display: grid; grid-template-columns: 2fr 1.1fr; gap: 1.5rem; align-items: start;">
    <!-- Left Column: Source vs Translation Editor -->
    <div>
        <div class="card" style="padding: 1.75rem; margin-bottom: 1.5rem;">
            <div style="margin-bottom: 1.5rem;">
                <label style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                    <span><i class="fa-solid fa-quote-left"></i> {{ __('Original Source Text') }} ({{ strtoupper($translation->source_locale) }})</span>
                    <span style="font-weight: normal; font-size: 0.78rem;">{{ mb_strlen($translation->source_text) }} {{ __('chars') }}</span>
                </label>
                <div style="padding: 1rem 1.25rem; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 10px; font-size: 0.95rem; line-height: 1.6; color: var(--text-main);">
                    {{ $translation->source_text }}
                </div>
            </div>

            <form action="{{ route('admin.translations.action', $translation) }}" method="POST" id="translationForm">
                @csrf
                <input type="hidden" name="action" id="formAction" value="save_draft">

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
                        <span><i class="fa-solid fa-pen"></i> {{ __('Translation') }} ({{ strtoupper($translation->locale) }}) *</span>
                        <span id="charCount" style="font-weight: normal; font-size: 0.78rem; color: var(--text-muted);">
                            {{ mb_strlen($translation->translated_text ?? '') }} {{ __('chars') }}
                        </span>
                    </label>

                    @if(in_array($translation->field, ['description', 'notes', 'ingredients']))
                        <textarea name="translated_text" id="translatedText" rows="5" required class="form-control" style="width: 100%; padding: 0.85rem 1rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 0.95rem; line-height: 1.6;">{{ $translation->translated_text }}</textarea>
                    @else
                        <input type="text" name="translated_text" id="translatedText" value="{{ $translation->translated_text }}" required class="form-control" style="width: 100%; padding: 0.85rem 1rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 1rem; font-weight: 600;">
                    @endif
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                        {{ __('Review / Edit Notes (Optional)') }}
                    </label>
                    <input type="text" name="notes" placeholder="{{ __('e.g. Corrected culinary terms, refined grammar...') }}" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 0.85rem;">
                </div>

                <!-- Action Buttons Toolbar -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                    <div>
                        <button type="button" onclick="submitWithAction('retranslate_ai')" class="btn" style="border-color: #8b5cf6; color: #8b5cf6; font-weight: 600;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Regenerate with AI') }}
                        </button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                        <button type="button" onclick="submitWithAction('save_draft')" class="btn" style="border-color: var(--border-color); font-weight: 600;">
                            <i class="fa-regular fa-floppy-disk"></i> {{ __('Save Draft') }}
                        </button>

                        <button type="button" onclick="submitWithAction('approve')" class="btn" style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); color: #f59e0b; font-weight: 700;">
                            <i class="fa-solid fa-check"></i> {{ __('Approve') }}
                        </button>

                        <button type="button" onclick="submitWithAction('publish')" class="btn btn-primary" style="padding: 0.65rem 1.4rem; font-weight: 700;">
                            <i class="fa-solid fa-check-double"></i> {{ __('Publish & Sync') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Version History & Metadata -->
    <div>
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.05rem; font-weight: 800; margin: 0 0 1rem 0; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-history" style="color: #6366f1;"></i> {{ __('Version History') }}
            </h3>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @forelse($translation->histories as $idx => $history)
                    <div style="padding: 0.85rem 1rem; border: 1px solid var(--border-color); border-radius: 10px; background: rgba(255,255,255,0.02);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <span style="font-size: 0.75rem; font-weight: 800; color: #6366f1;">
                                v{{ $history->version_number }} &middot; {{ strtoupper($history->action ?? $history->status) }}
                            </span>
                            <span style="font-size: 0.72rem; color: var(--text-muted);">
                                {{ $history->created_at->diffForHumans() }}
                            </span>
                        </div>

                        <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.35rem; line-height: 1.4;">
                            {{ $history->translation }}
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; border-top: 1px dashed var(--border-color); padding-top: 0.35rem;">
                            <span>{{ $history->creator?->name ?? __('System / AI') }}</span>

                            @if($idx > 0)
                                <form action="{{ route('admin.translations.restore', ['translation' => $translation, 'history' => $history->id]) }}" method="POST" style="margin: 0;">
                                    @csrf
                                    <button type="submit" class="btn" style="padding: 0.15rem 0.5rem; font-size: 0.72rem; border-color: var(--border-color); color: var(--text-muted);" onclick="return confirm('{{ __('Restore this version to draft?') }}');">
                                        <i class="fa-solid fa-rotate-left"></i> {{ __('Restore') }}
                                    </button>
                                </form>
                            @else
                                <span style="font-size: 0.7rem; color: #10b981; font-weight: 700;">{{ __('Current') }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1.5rem 0;">
                        {{ __('No history recorded yet.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
function submitWithAction(actionName) {
    document.getElementById('formAction').value = actionName;
    document.getElementById('translationForm').submit();
}

const inputEl = document.getElementById('translatedText');
const counterEl = document.getElementById('charCount');
if (inputEl && counterEl) {
    inputEl.addEventListener('input', () => {
        counterEl.textContent = inputEl.value.length + ' chars';
    });
}
</script>
@endsection
