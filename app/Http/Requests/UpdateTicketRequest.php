<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        return ['version' => 'required|integer|min:1',
            'assigned_to' => ['sometimes', 'nullable', Rule::exists('users', 'id')->where('role', 'support')->where('is_active', true)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'resolution' => 'nullable|string|max:10000'];
    }
}
