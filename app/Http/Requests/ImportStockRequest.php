<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportStockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'stock_file' => 'required|file|mimes:csv,xlsx,xls|max:2048',
        ];
    }

    public function messages()
    {
        return [
            'stock_file.required' => 'Please select a file to import.',
            'stock_file.mimes' => 'File must be CSV or Excel format.',
            'stock_file.max' => 'File size must not exceed 2MB.',
        ];
    }
}
