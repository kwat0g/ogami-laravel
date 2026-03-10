<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'subject'        => ['required', 'string', 'max:200'],
            'description'    => ['required', 'string', 'min:10'],
            'type'           => ['required', 'in:complaint,inquiry,request'],
            'priority'       => ['in:low,normal,high,critical'],
            'customer_id'    => ['nullable', 'integer', 'exists:customers,id'],
            'client_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
