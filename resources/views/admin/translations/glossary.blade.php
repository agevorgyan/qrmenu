@extends('layouts.app')

@section('title', __('Terminology & Brand Glossary') . ' - ' . $vendor->name)

@section('content')
<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <a href="{{ route('admin.translations.index') }}" class="btn" style="border-color: var(--border-color); padding: 0.5rem 0.85rem;">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Translations') }}
        </a>
        <div>
            <h1 style="font-size: 1.55rem; font-weight: 800; margin: 0; color: var(--text-main);">
                {{ __('Terminology & Brand Glossary') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                {{ __('Guide the AI translation engine with mandatory brand names, verbatim terms, and forbidden words.') }}
            </p>
        </div>
    </div>
</div>

@if(session('success'))
    <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fa-solid fa-circle-check"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

<div style="display: grid; grid-template-columns: 1fr 1.8fr; gap: 1.5rem; align-items: start;">
    <!-- Add Rule Form -->
    <div class="card" style="padding: 1.75rem;">
        <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0 0 1.25rem 0; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-plus-circle" style="color: #6366f1;"></i> {{ __('Add Glossary Rule') }}
        </h3>

        <form action="{{ route('admin.translations.glossary.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
                    {{ __('Source Term or Phrase') }} *
                </label>
                <input type="text" name="term" required placeholder="{{ __('e.g. Khachapuri, Coca-Cola') }}" class="form-control" style="width: 100%; padding: 0.65rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
                    {{ __('Mandatory Translation (Optional)') }}
                </label>
                <input type="text" name="translated_term" placeholder="{{ __('e.g. Хачапури') }}" class="form-control" style="width: 100%; padding: 0.65rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
                    {{ __('Target Language (or All)') }}
                </label>
                <select name="target_locale" class="form-control" style="width: 100%; padding: 0.65rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main);">
                    <option value="">{{ __('All Supported Languages') }}</option>
                    <option value="hy">🇦🇲 {{ __('Armenian') }} (hy)</option>
                    <option value="en">🇬🇧 {{ __('English') }} (en)</option>
                    <option value="ru">🇷🇺 {{ __('Russian') }} (ru)</option>
                </select>
            </div>

            <div style="margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 0.6rem; padding: 0.85rem; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--text-main);">
                    <input type="checkbox" name="is_verbatim" value="1" style="accent-color: #6366f1;">
                    <span>{{ __('Do NOT translate (Keep verbatim)') }}</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--text-main);">
                    <input type="checkbox" name="is_forbidden" value="1" style="accent-color: #ef4444;">
                    <span>{{ __('Forbidden Term (Disallow specific word)') }}</span>
                </label>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
                    {{ __('Context / Explanation for AI') }}
                </label>
                <textarea name="notes" rows="2" placeholder="{{ __('e.g. Traditional cheese-filled bread, must not be translated as cheese pie') }}" class="form-control" style="width: 100%; padding: 0.65rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 0.85rem;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.7rem; font-weight: 700;">
                <i class="fa-solid fa-plus"></i> {{ __('Save Glossary Term') }}
            </button>
        </form>
    </div>

    <!-- Glossary List -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
            <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                {{ __('Configured Terminology Rules') }} ({{ $glossary->count() }})
            </h3>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
                <thead>
                    <tr style="background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">
                        <th style="padding: 0.85rem 1.25rem;">{{ __('Term') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Rule / Translation') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Locale') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Notes') }}</th>
                        <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($glossary as $rule)
                        <tr style="border-bottom: 1px solid var(--table-row-border);">
                            <td style="padding: 0.85rem 1.25rem; font-weight: 700; color: var(--text-main);">
                                {{ $rule->term }}
                            </td>
                            <td style="padding: 0.85rem 1rem;">
                                @if($rule->is_verbatim)
                                    <span class="badge" style="background: rgba(148, 163, 184, 0.2); color: #94a3b8; font-size: 0.72rem; padding: 0.2rem 0.5rem;">
                                        {{ __('Verbatim (Do Not Translate)') }}
                                    </span>
                                @elseif($rule->is_forbidden)
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; font-size: 0.72rem; padding: 0.2rem 0.5rem;">
                                        {{ __('Forbidden') }}
                                    </span>
                                @else
                                    <span style="font-weight: 600; color: var(--text-main);">
                                        &rarr; {{ $rule->translated_term }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 1rem;">
                                <code style="font-size: 0.78rem;">{{ $rule->target_locale ? strtoupper($rule->target_locale) : __('ALL') }}</code>
                            </td>
                            <td style="padding: 0.85rem 1rem; color: var(--text-muted); font-size: 0.8rem;">
                                {{ $rule->notes ?? '—' }}
                            </td>
                            <td style="padding: 0.85rem 1.25rem; text-align: right;">
                                <form action="{{ route('admin.translations.glossary.destroy', $rule) }}" method="POST" style="margin: 0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn" style="padding: 0.3rem 0.6rem; font-size: 0.75rem; color: #ef4444; border-color: rgba(239, 68, 68, 0.3);" onclick="return confirm('{{ __('Delete this glossary rule?') }}');">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                                {{ __('No custom glossary rules created yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
