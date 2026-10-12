<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('ticket'));
    }

    public static function fileRules(): array
    {
        return ['files' => 'nullable|array|max:5', 'files.*' => 'required|file|mimes:jpg,jpeg,png,pdf,txt|max:10240'];
    }

    public function rules(): array
    {
        return [...self::fileRules(), 'files' => 'required|array|min:1|max:5'];
    }
}
