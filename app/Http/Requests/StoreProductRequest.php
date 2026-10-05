<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class StoreProductRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422));
    }

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

        $businessId = session('business_id');
        if (!$businessId && $this->attributes->has('business')) {
            $businessId = $this->attributes->get('business')->id;
        }

        $businessType = \App\Models\Business::where('id', $businessId)
                        ->value('business_type');
        $rules =  [
            'product_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:products,slug',
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            // 'unit_id' => 'required|exists:units,id', 
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|required_with:discount_type',
            'discount_type' => 'nullable|required_with:discount',
            'purchase_price' => 'required|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'expire_date' => 'nullable|date',
            'status' => 'nullable|boolean',
            'fixed_notified_value_or_retail_price' => 'nullable|string|min:1',
        ];
        if ($businessType === 'pos' || $businessType === 'pral') {
            $rules['PCTCode'] = 'required|string|max:255';
            $rules['pos_sale_tax'] = 'required|string|max:255';
        }else{
            // ✅ New fields
            $rules['hsCode'] = 'required|string|max:255';
            $rules['hs_description'] = 'required|string';
            $rules['rate_of_tax'] = 'required|string|max:255';
            $rules['salesTaxWithheldAtSource'] = ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'];
            $rules['sale_type'] = 'required|string|max:255';
            $rules['sro_item'] = 'nullable|string|max:10';
            $rules['uom'] = 'required|string|max:255';
            $rules['sro_schedule'] = 'nullable|string|max:255';
        }
            
        return $rules;
    }
}
