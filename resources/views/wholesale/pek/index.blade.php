@extends('layouts.app')

@section('title', __('Batch PEK XML Exports'))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6366f1;">
                    {{ __('Armenian SRC e-Invoicing (ՊԵԿ)') }}
                </span>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Batch PEK XML Export Hub') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Package multiple validated electronic invoices into ZIP archives for direct upload into the Armenian State Revenue Committee portal.') }}
            </p>
        </div>
    </div>

    <!-- Export Packaging Form -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-top: 0; margin-bottom: 0.5rem;">
            {{ __('Pending / Ready for Export Documents') }}
        </h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            {{ __('Select tax documents to package into a single downloadable ZIP archive containing official PEK-compliant XML files.') }}
        </p>

        @if($pendingDocuments->isEmpty())
            <div style="padding: 2rem; text-align: center; color: var(--text-muted); background: var(--bg-main); border-radius: 12px;">
                <i class="fa-solid fa-check-double" style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #10b981; display: block;"></i>
                {{ __('All generated tax documents have already been exported or no documents are currently pending.') }}
            </div>
        @else
            <form method="POST" action="{{ route('admin.wholesale.pek.export_batch') }}">
                @csrf
                <div style="max-height: 280px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 1rem;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr style="background: var(--bg-main); text-align: left; border-bottom: 1px solid var(--border-color);">
                                <th style="padding: 0.6rem 0.75rem; width: 40px; text-align: center;">
                                    <input type="checkbox" id="selectAll" onclick="document.querySelectorAll('.doc-checkbox').forEach(c => c.checked = this.checked)">
                                </th>
                                <th style="padding: 0.6rem 0.75rem;">{{ __('Doc #') }}</th>
                                <th style="padding: 0.6rem 0.75rem;">{{ __('Type') }}</th>
                                <th style="padding: 0.6rem 0.75rem;">{{ __('Buyer') }}</th>
                                <th style="padding: 0.6rem 0.75rem;">{{ __('Total') }}</th>
                                <th style="padding: 0.6rem 0.75rem;">{{ __('XSD Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingDocuments as $doc)
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 0.6rem 0.75rem; text-align: center;">
                                        <input type="checkbox" name="document_ids[]" value="{{ $doc->id }}" class="doc-checkbox">
                                    </td>
                                    <td style="padding: 0.6rem 0.75rem; font-family: monospace; font-weight: 700;">
                                        {{ $doc->series }}{{ $doc->document_number }}
                                    </td>
                                    <td style="padding: 0.6rem 0.75rem;">
                                        {{ $doc->isInvoice() ? __('Հարկային հաշիվ') : __('Հաշիվ վավերագիր') }}
                                    </td>
                                    <td style="padding: 0.6rem 0.75rem;">{{ $doc->customer->company_name ?? '—' }}</td>
                                    <td style="padding: 0.6rem 0.75rem; font-weight: 600;">{{ number_format((float)$doc->total_amount, 2) }} AMD</td>
                                    <td style="padding: 0.6rem 0.75rem;">
                                        @if($doc->is_xsd_valid)
                                            <span style="color: #059669; font-weight: 600;"><i class="fa-solid fa-circle-check"></i> {{ __('Valid') }}</span>
                                        @else
                                            <span style="color: #dc2626; font-weight: 600;"><i class="fa-solid fa-circle-exclamation"></i> {{ __('Invalid') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                    <i class="fa-solid fa-file-zipper"></i> {{ __('Package Selected Documents into ZIP') }}
                </button>
            </form>
        @endif
    </div>

    <!-- Export History -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); font-weight: 700;">
            {{ __('Export Batch Archives') }}
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('Batch #') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Date') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Type') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Documents') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Archive Size') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Created By') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Download') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($exports as $exp)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 700; color: #6366f1;">
                            {{ $exp->export_batch_number }}
                        </td>
                        <td style="padding: 1rem;">{{ $exp->created_at->format('Y-m-d H:i') }}</td>
                        <td style="padding: 1rem;">
                            <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                                {{ $exp->document_type }}
                            </span>
                        </td>
                        <td style="padding: 1rem; font-weight: 600;">{{ __(':count docs', ['count' => $exp->document_count]) }}</td>
                        <td style="padding: 1rem; font-family: monospace;">{{ number_format($exp->file_size_bytes / 1024, 1) }} KB</td>
                        <td style="padding: 1rem; color: var(--text-muted);">{{ $exp->creator->name ?? __('System') }}</td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <a href="{{ route('admin.wholesale.pek.download_zip', $exp) }}" 
                               style="padding: 0.4rem 0.85rem; border-radius: 8px; background: #10b981; color: #fff; font-weight: 700; text-decoration: none; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-file-arrow-down"></i> {{ __('ZIP') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-box-archive" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.4; display: block;"></i>
                            {{ __('No export batches generated yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($exports->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $exports->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
