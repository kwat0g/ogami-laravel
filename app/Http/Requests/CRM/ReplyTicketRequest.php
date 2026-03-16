<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

final class ReplyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1'],
            'is_internal' => ['boolean'],
        ];
    }
}
