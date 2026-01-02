<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'product_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:products,slug',
            'sku' => 'required|string|max:255|unique:products,sku',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            // 'unit_id' => 'required|exists:units,id', 
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|required_with:discount_type',
            'discount_type' => 'nullable|required_with:discount',
            'purchase_price' => 'required|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'expire_date' => 'nullable|date',
            'status' => 'nullable|boolean',
              // ✅ New fields
            'hsCode' => 'required|string|max:255',
            'hs_description' => 'required|string',
            'rate_of_tax' => 'required|string|max:255',
            'salesTaxWithheldAtSource' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'sale_type' => 'required|string|max:255',
            'sro_item' => 'nullable|string|max:10',
            'uom' => 'required|string|max:255',
            'sro_schedule' => 'nullable|string|max:255',

        ];
    }
}
