<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Ticket::class);
    }

    public function rules(): array
    {
        return ['subject' => 'required|string|max:180', 'description' => 'required|string|max:10000',
            'category_id' => ['required', Rule::exists('categories', 'id')->where('is_active', true)],
            ...AttachmentRequest::fileRules()];
    }
}
