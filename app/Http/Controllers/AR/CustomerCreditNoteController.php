<?php

declare(strict_types=1);

namespace App\Http\Controllers\AR;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerCreditNote;
use App\Domains\AR\Services\CustomerCreditNoteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerCreditNoteController extends Controller
{
    public function __construct(
        private readonly CustomerCreditNoteService $service,
    ) {}

    /**
     * List customer credit/debit notes.
     * ?customer_id=X  ?note_type=credit|debit  ?status=draft|posted
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerCreditNote::class);

        $notes = CustomerCreditNote::with(['customer', 'customerInvoice'])
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('note_type'), fn ($q) => $q->where('note_type', $request->input('note_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->paginate(30);

        return response()->json($notes);
    }

    /**
     * Create a new customer credit/debit note (status = draft).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerCreditNote::class);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'customer_invoice_id' => ['nullable', 'integer', 'exists:customer_invoices,id'],
            'note_type' => ['required', 'in:credit,debit'],
            'note_date' => ['required', 'date'],
            'amount_centavos' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'ar_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $note = $this->service->create($customer, $data, $request->user());

        return response()->json($note->load('customer', 'customerInvoice'), 201);
    }

    /**
     * Show a single customer credit/debit note.
     */
    public function show(CustomerCreditNote $customerCreditNote): JsonResponse
    {
        $this->authorize('view', $customerCreditNote);

        return response()->json(
            $customerCreditNote->load('customer', 'customerInvoice', 'createdBy'),
        );
    }

    /**
     * Post the draft note to the GL.
     */
    public function post(CustomerCreditNote $customerCreditNote, Request $request): JsonResponse
    {
        $this->authorize('update', $customerCreditNote);

        $posted = $this->service->post($customerCreditNote, $request->user());

        return response()->json($posted->load('customer', 'customerInvoice'));
    }
}
