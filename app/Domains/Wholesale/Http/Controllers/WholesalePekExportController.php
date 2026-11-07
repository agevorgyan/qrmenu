<?php

declare(strict_types=1);

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesalePekExport;
use App\Domains\Wholesale\Models\WholesaleTaxDocument;
use App\Domains\Wholesale\Services\WholesaleTaxDocumentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WholesalePekExportController extends Controller
{
    public function __construct(
        protected WholesaleTaxDocumentService $taxDocumentService
    ) {}

    public function index(Request $request): View
    {
        $vendor = $request->user()->vendor ?? abort(403, 'Tenant context missing');

        $exports = WholesalePekExport::with('creator')->latest()->paginate(15);
        $pendingDocuments = WholesaleTaxDocument::whereIn('status', ['DRAFT', 'GENERATED'])
            ->with(['seller', 'customer'])
            ->latest()
            ->get();

        return view('wholesale.pek.index', compact('exports', 'pendingDocuments'));
    }

    public function exportBatch(Request $request): RedirectResponse|BinaryFileResponse
    {
        $validated = $request->validate([
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'integer|exists:wholesale_tax_documents,id',
        ]);

        $vendor = $request->user()->vendor ?? abort(403, 'Tenant context missing');

        $export = $this->taxDocumentService->exportTaxDocumentsAsZip(
            $vendor,
            $validated['document_ids'],
            $request->user()
        );

        return redirect()->route('admin.wholesale.pek.index')
            ->with('success', "Batch {$export->export_batch_number} created with {$export->document_count} tax documents.");
    }

    public function downloadZip(WholesalePekExport $export): BinaryFileResponse
    {
        $fullPath = storage_path('app/'.$export->storage_path);

        if (! file_exists($fullPath)) {
            abort(404, 'Export ZIP file not found on disk.');
        }

        return response()->download($fullPath, "{$export->export_batch_number}.zip", [
            'Content-Type' => 'application/zip',
        ]);
    }
}
