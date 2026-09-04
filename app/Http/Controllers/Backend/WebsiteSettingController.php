<?php

namespace App\Http\Controllers\Backend;

use App\Models\Business;
use App\Models\CartSetting;
use Illuminate\Http\Request;
use App\Rules\ValidImageType;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use App\Models\InvoiceSetting;
use App\Models\quantity_validation;
use App\Trait\FileHandler;
use Illuminate\Support\Facades\DB;

class WebsiteSettingController extends Controller
{
    public $fileHandler;

    public function __construct(FileHandler $fileHandler)
    {
        $this->fileHandler = $fileHandler;
    }

    public function websiteGeneral(Request $request)
    {
        $businessId = session('business_id');
        $invoiceSetting = null;
        $fbrToken = null;

        if ($businessId) {
            $invoiceSetting = \App\Models\InvoiceSetting::where('business_id', $businessId)->first();
            $fbrToken = DB::table('fbr_tokens')
                ->where('business_id', $businessId)
                ->latest('id')
                ->first();

            $status = quantity_validation::select('product_quantity_validation')
                ->where('business_id', $businessId)
                ->first();

            $quantityValidationStatus = $status ? $status->product_quantity_validation : 'disabled'; // default

            $cartSetting = CartSetting::where('business_id', $businessId)->first()
                ?? new CartSetting(['draft_btn' => 0, 'custom_field_btn' => 0]);

            $businessType = Business::where('id', $businessId)->value('business_type');


        }
        return view('backend.settings.website-settings.general', compact('invoiceSetting', 'fbrToken', 'quantityValidationStatus', 'cartSetting', 'businessType'));
    }

    public function websiteInfoUpdate(Request $request)
    {
        $request->validate([
            'site_name' => 'required',
            'site_url' => 'url'
        ]);

        foreach ($request->except('_token') as $key => $value) {
            writeConfig($key, $value);
        }
        Artisan::call('config:clear');
        return to_route('backend.admin.settings.website.general', ['active-tab' => 'website-info'])
            ->with('success', 'Updated successfully');
    }

    public function websiteContactsUpdate(Request $request)
    {
        foreach ($request->except('_token') as $key => $value) {
            writeConfig($key, $value);
        }
        Artisan::call('config:clear');
        return to_route('backend.admin.settings.website.general', ['active-tab' => 'contacts'])
            ->with('success', 'Updated successfully');
    }

    public function websiteSocialLinkUpdate(Request $request)
    {
        foreach ($request->except('_token') as $key => $value) {
            writeConfig($key, $value);
        }
        Artisan::call('config:clear');
        return to_route('backend.admin.settings.website.general', ['active-tab' => 'social-links'])
            ->with('success', 'Updated successfully');
    }

    public function websiteStyleSettingsUpdate(Request $request)
    {
        $request->validate([
            'site_logo' => ['file', new ValidImageType],
            'favicon_icon' => ['file', new ValidImageType],
            'favicon_icon_apple' => ['file', new ValidImageType],
        ]);

        writeConfig('newsletter_subscribe', $request->newsletter_subscribe);

        if ($request->hasFile("site_logo")) {
            $this->fileHandler->securePublicUnlink(readConfig('site_logo'));
            $site_logo = $this->fileHandler->uploadToPublic($request->file("site_logo"), "/assets/images/logo");
            writeConfig('site_logo', $site_logo);
        }
        if ($request->hasFile("favicon_icon")) {
            $this->fileHandler->securePublicUnlink(readConfig('favicon_icon'));
            $favicon_icon = $this->fileHandler->uploadToPublic($request->file("favicon_icon"), "/assets/images/logo");
            writeConfig('favicon_icon', $favicon_icon);
        }
        if ($request->hasFile("favicon_icon_apple")) {
            $this->fileHandler->securePublicUnlink(readConfig('favicon_icon_apple'));
            $favicon_icon_apple = $this->fileHandler->uploadToPublic($request->file("favicon_icon_apple"), "/assets/images/logo");
            writeConfig('favicon_icon_apple', $favicon_icon_apple);
        }
        Artisan::call('config:clear');
        return to_route('backend.admin.settings.website.general', ['active-tab' => 'style-settings'])
            ->with('success', 'Updated successfully');
    }

    public function websiteCustomCssUpdate(Request $request)
    {
        writeConfig('custom_css', $request->custom_css);
        Artisan::call('config:clear');
        return to_route('backend.admin.settings.website.general', ['active-tab' => 'custom-css'])
            ->with('success', 'Updated successfully');
    }

    public function websiteNotificationSettingsUpdate(Request $request)
    {
        foreach ($request->except('_token') as $key => $value) {
            writeConfig($key, $value);
        }
        Artisan::call('config:clear');
        return to_route('backend.admin.settings.website.general', ['active-tab' => 'notification-settings'])
            ->with('success', 'Updated successfully');
    }


    public function websiteStatusUpdate(Request $request)
    {
        writeConfig('is_live', $request->is_live);
        Artisan::call('config:clear');
        return to_route('backend.admin.settings.website.general', ['active-tab' => 'website-status'])
            ->with('success', 'Updated successfully');
    }

    public function websiteInvoiceUpdate(Request $request)
    {
        $businessId = session('business_id');

        if (!$businessId) {
            return back()->with('error', 'Business not found in session');
        }
        // Invoice settings ko database mein save karein
        $invoiceSetting = InvoiceSetting::firstOrNew(['business_id' => $businessId]);

        $invoiceSetting->logo = $request->is_show_logo_invoice ?? 0;
        $invoiceSetting->site_name = $request->is_show_site_invoice ?? 0;
        $invoiceSetting->phone = $request->is_show_phone_invoice ?? 0;
        $invoiceSetting->email = $request->is_show_email_invoice ?? 0;
        $invoiceSetting->address = $request->is_show_address_invoice ?? 0;
        $invoiceSetting->customer = $request->is_show_customer_invoice ?? 0;
        $invoiceSetting->unit_price = $request->is_show_unit_price_invoice ?? 0;
        $invoiceSetting->invoice_number = $request->is_show_invoice_number_invoice ?? 0;
        $invoiceSetting->po_number = $request->is_show_po_number ?? 0;
        $invoiceSetting->imc_logo = $request->is_show_imc_logo_invoice ?? 0;
        $invoiceSetting->thanks_for_shopping = $request->is_show_thanks_for_shopping_invoice ?? 0;
        $invoiceSetting->note_to_customer = $request->note_to_customer ?? 0;
        $invoiceSetting->note_msg = $request->note_msg ?? null;
        $invoiceSetting->post_invoice_width = $request->receiptMaxwidth;

        $invoiceSetting->save();

        return to_route('backend.admin.settings.website.general', ['active-tab' => 'invoice-settings'])
            ->with('success', 'Invoice settings updated successfully');
    }


    public function websiteFbrTokenUpdate(Request $request)
    {
        $businessId = session('business_id');

        if (!$businessId) {
            return back()->with('error', 'Business not found in session');
        }

        // Update or insert record
        DB::table('fbr_tokens')->updateOrInsert(
            ['business_id' => $businessId], // condition to match existing record
            [
                'fbr_integration' => $request->fbr_integration ?? 'disabled',
                'pos_sandbox_token' => $request->pos_sandbox_token ?? null,
                'pos_production_token' => $request->pos_production_token ?? null,
                'digital_sandbox_token' => $request->digital_sandbox_token ?? null,
                'digital_production_token' => $request->digital_production_token ?? null,
                'pral_sandbox_token' => $request->pral_sandbox_token ?? null,
                'pral_production_token' => $request->pral_production_token ?? null,
                'POSID_sandbox' => $request->POSID_sandbox ?? null,
                'POSID_production' => $request->POSID_production ?? null,
                'environment' => $request->environment ?? 0, // 0=sandbox, 1=production
                'updated_at' => now(),
            ]
        );

        return to_route('backend.admin.settings.website.general', ['active-tab' => 'fbr-token-settings'])
            ->with('success', 'FBR Tokens updated successfully');
    }

    public function updateCartSettings(Request $request)
    {
        $businessId = session('business_id');

        if (!$businessId) {
            return redirect()->back()->with('error', 'No business selected.');
        }

        $validated = $request->validate([
            'draft_btn' => ['required', 'boolean'],
            'custom_field_btn' => ['required', 'boolean'],
        ]);

        CartSetting::updateOrCreate(
            [
                'business_id' => $businessId,
            ],
            [
                'draft_btn' => $request->boolean('draft_btn'),
                'custom_field_btn' => $request->boolean('custom_field_btn'),
            ]
        );

        return to_route('backend.admin.settings.website.general', ['active-tab' => 'cart-settings'])
            ->with('success', 'Cart settings updated successfully');
    }
}
