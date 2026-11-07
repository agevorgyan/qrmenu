<?php

declare(strict_types=1);

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Models\WholesaleTaxDocument;
use App\Domains\Wholesale\Services\WholesaleTaxDocumentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class WholesaleTaxDocumentController extends Controller
{
    public function __construct(
        protected WholesaleTaxDocumentService $taxDocumentService
    ) {}

    public function index(Request $request): View
    {
        $vendor = $request->user()->vendor ?? abort(403, 'Tenant context missing');

        $query = WholesaleTaxDocument::with(['seller', 'customer', 'sale']);

        if ($request->filled('type')) {
            $query->where('document_type', $request->query('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'ilike', $search)
                    ->orWhereHas('customer', fn ($c) => $c->where('legal_name', 'ilike', $search)->orWhere('name', 'ilike', $search));
            });
        }

        $documents = $query->latest()->paginate(15);

        return view('wholesale.tax_documents.index', compact('documents'));
    }

    public function show(WholesaleTaxDocument $taxDocument): View
    {
        $taxDocument->load(['seller', 'customer', 'sale', 'items.product']);

        return view('wholesale.tax_documents.show', compact('taxDocument'));
    }

    public function generateFromSale(Request $request, WholesaleSale $sale): RedirectResponse
    {
        $document = $this->taxDocumentService->createTaxDocumentFromSale($sale, $request->user());

        return redirect()->route('admin.wholesale.tax_documents.show', $document)
            ->with('success', "Tax Document {$document->series}{$document->document_number} generated successfully.");
    }

    public function revalidate(WholesaleTaxDocument $taxDocument): RedirectResponse
    {
        $this->taxDocumentService->generateAndValidateXml($taxDocument);

        $msg = $taxDocument->is_xsd_valid
            ? 'XML re-validated successfully against official XSD.'
            : 'XML validation generated errors: '.implode('; ', $taxDocument->xsd_validation_errors ?? []);

        return redirect()->route('admin.wholesale.tax_documents.show', $taxDocument)
            ->with($taxDocument->is_xsd_valid ? 'success' : 'error', $msg);
    }

    public function downloadXml(WholesaleTaxDocument $taxDocument): Response
    {
        if (empty($taxDocument->xml_content)) {
            $this->taxDocumentService->generateAndValidateXml($taxDocument);
        }

        $fileName = "{$taxDocument->series}{$taxDocument->document_number}.xml";

        return response($taxDocument->xml_content, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
