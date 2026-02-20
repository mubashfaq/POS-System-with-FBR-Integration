<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Http\Request;
use App\Rules\ValidImageType;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use App\Models\InvoiceSetting;
use App\Trait\FileHandler;

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

        if ($businessId) {
            $invoiceSetting = \App\Models\InvoiceSetting::where('business_id', $businessId)->first();
        }

        return view('backend.settings.website-settings.general', compact('invoiceSetting'));
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
    // public function websiteInvoiceUpdate(Request $request)
    // {
    //     foreach ($request->except('_token') as $key => $value) {
    //         writeConfig($key, $value);
    //     }
    //     Artisan::call('config:clear');
    //     return to_route('backend.admin.settings.website.general', ['active-tab' => 'invoice-settings'])
    //         ->with('success', 'Updated successfully');
    // }

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
        $invoiceSetting->note_to_customer = $request->note_to_customer ?? 0;
        $invoiceSetting->note_msg = $request->note_msg ?? null;
        $invoiceSetting->post_invoice_width = $request->receiptMaxwidth;

        $invoiceSetting->save();

        return to_route('backend.admin.settings.website.general', ['active-tab' => 'invoice-settings'])
            ->with('success', 'Invoice settings updated successfully');
    }
}
