<?php

declare(strict_types=1);

namespace App\Http\Controllers\AP;

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorCreditNote;
use App\Domains\AP\Services\VendorCreditNoteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VendorCreditNoteController extends Controller
{
    public function __construct(
        private readonly VendorCreditNoteService $service,
    ) {}

    /**
     * List vendor credit/debit notes.
     * ?vendor_id=X  ?note_type=credit|debit  ?status=draft|posted
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VendorCreditNote::class);

        $notes = VendorCreditNote::with(['vendor', 'vendorInvoice'])
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')))
            ->when($request->filled('note_type'), fn ($q) => $q->where('note_type', $request->input('note_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->paginate(30);

        return response()->json($notes);
    }

    /**
     * Create a new vendor credit/debit note (status = draft).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', VendorCreditNote::class);

        $data = $request->validate([
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'vendor_invoice_id' => ['nullable', 'integer', 'exists:vendor_invoices,id'],
            'note_type' => ['required', 'in:credit,debit'],
            'note_date' => ['required', 'date'],
            'amount_centavos' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'ap_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $vendor = Vendor::findOrFail($data['vendor_id']);
        $note = $this->service->create($vendor, $data, $request->user());

        return response()->json($note->load('vendor', 'vendorInvoice'), 201);
    }

    /**
     * Show a single vendor credit/debit note.
     */
    public function show(VendorCreditNote $vendorCreditNote): JsonResponse
    {
        $this->authorize('view', $vendorCreditNote);

        return response()->json(
            $vendorCreditNote->load('vendor', 'vendorInvoice', 'createdBy'),
        );
    }

    /**
     * Post the draft note to the GL.
     */
    public function post(VendorCreditNote $vendorCreditNote, Request $request): JsonResponse
    {
        $this->authorize('update', $vendorCreditNote);

        $posted = $this->service->post($vendorCreditNote, $request->user());

        return response()->json($posted->load('vendor', 'vendorInvoice'));
    }
}
