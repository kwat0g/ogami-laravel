<?php

declare(strict_types=1);

namespace App\Domains\CRM\Services;

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Opportunity;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class LeadService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Lead::with(['assignedTo', 'createdBy'])
            ->orderByDesc('id');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (isset($filters['assigned_to_id'])) {
            $query->where('assigned_to_id', $filters['assigned_to_id']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters): void {
                $q->where('company_name', 'ilike', "%{$filters['search']}%")
                    ->orWhere('contact_name', 'ilike', "%{$filters['search']}%")
                    ->orWhere('email', 'ilike', "%{$filters['search']}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, User $actor): Lead
    {
        return Lead::create([
            'company_name' => $data['company_name'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'source' => $data['source'] ?? 'website',
            'status' => 'new',
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_id' => $actor->id,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(Lead $lead, array $data): Lead
    {
        if ($lead->isConverted()) {
            throw new DomainException(
                'Cannot update a converted lead.',
                'CRM_LEAD_ALREADY_CONVERTED',
                422
            );
        }

        $lead->update(array_filter([
            'company_name' => $data['company_name'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'source' => $data['source'] ?? null,
            'status' => $data['status'] ?? null,
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return $lead->fresh() ?? $lead;
    }

    /**
     * Convert a lead into a Customer + Contact + optionally an Opportunity.
     *
     * @return array{customer: Customer, contact: Contact, opportunity: Opportunity|null}
     */
    public function convert(Lead $lead, User $actor, ?array $opportunityData = null): array
    {
        if ($lead->isConverted()) {
            throw new DomainException('Lead is already converted.', 'CRM_LEAD_ALREADY_CONVERTED', 422);
        }

        if ($lead->isDisqualified()) {
            throw new DomainException('Cannot convert a disqualified lead.', 'CRM_LEAD_DISQUALIFIED', 422);
        }

        return DB::transaction(function () use ($lead, $actor, $opportunityData): array {
            // Create Customer
            $customer = Customer::create([
                'name' => $lead->company_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'address' => '',
                'created_by_id' => $actor->id,
            ]);

            // Create Contact from lead info
            $nameParts = explode(' ', $lead->contact_name, 2);
            $contact = Contact::create([
                'customer_id' => $customer->id,
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'email' => $lead->email,
                'phone' => $lead->phone,
                'role' => 'decision_maker',
                'is_primary' => true,
            ]);

            // Optionally create Opportunity
            $opportunity = null;
            if ($opportunityData !== null) {
                $opportunity = Opportunity::create([
                    'customer_id' => $customer->id,
                    'contact_id' => $contact->id,
                    'title' => $opportunityData['title'] ?? "Opportunity from {$lead->company_name}",
                    'expected_value_centavos' => $opportunityData['expected_value_centavos'] ?? 0,
                    'probability_pct' => $opportunityData['probability_pct'] ?? 10,
                    'expected_close_date' => $opportunityData['expected_close_date'] ?? null,
                    'stage' => 'prospecting',
                    'assigned_to_id' => $lead->assigned_to_id,
                    'created_by_id' => $actor->id,
                ]);
            }

            // Mark lead as converted
            $lead->update([
                'status' => 'converted',
                'converted_customer_id' => $customer->id,
                'converted_at' => now(),
            ]);

            return [
                'customer' => $customer,
                'contact' => $contact,
                'opportunity' => $opportunity,
            ];
        });
    }

    public function disqualify(Lead $lead, string $reason): Lead
    {
        if ($lead->isConverted()) {
            throw new DomainException('Cannot disqualify a converted lead.', 'CRM_LEAD_ALREADY_CONVERTED', 422);
        }

        $lead->update([
            'status' => 'disqualified',
            'notes' => ($lead->notes ? $lead->notes . "\n" : '') . "Disqualified: {$reason}",
        ]);

        return $lead->fresh() ?? $lead;
    }
}
