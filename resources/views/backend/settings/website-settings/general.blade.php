@extends('backend.master')

@section('title', 'General Settings')

@section('content')

<div class="row">
    <div class="col-4 col-sm-2">
        <div class="nav flex-column nav-tabs h-100" id="vert-tabs-tab" role="tablist" aria-orientation="vertical">
            @can('website_settings')
            <a class="nav-link {{ @$_GET['active-tab'] == 'website-info' ? 'active' : '' }}" id="vert-tabs-1"
                data-toggle="pill" href="#tabs-1" role="tab" aria-controls="tabs-1" aria-selected="true">
                <i class="fas fa-desktop"></i>
                &nbsp;Website Info
            </a>
            @endcan
            @can('contact_settings')
            <a class="nav-link {{ @$_GET['active-tab'] == 'contacts' ? 'active' : '' }}" id="vert-tabs-2"
                data-toggle="pill" href="#tabs-2" role="tab" aria-controls="tabs-2" aria-selected="false">
                <i class="fas fa-address-book"></i>
                &nbsp;Contacts
            </a>
            @endcan
            @can('socials_settings')
            <a class="nav-link {{ @$_GET['active-tab'] == 'social-links' ? 'active' : '' }}" id="vert-tabs-3"
                data-toggle="pill" href="#tabs-3" role="tab" aria-controls="tabs-3" aria-selected="false">
                <i class="fas fa-share-alt"></i>
                &nbsp;Social Links
            </a>
            @endcan
            @can('style_settings')
            <a class="nav-link {{ @$_GET['active-tab'] == 'style-settings' ? 'active' : '' }}" id="vert-tabs-4"
                data-toggle="pill" href="#tabs-4" role="tab" aria-controls="tabs-4" aria-selected="false">
                <i class="fas fa-swatchbook"></i>
                &nbsp;Style Settings
            </a>
            @endcan
            @can('custom_settings')
            <a class="nav-link {{ @$_GET['active-tab'] == 'custom-css' ? 'active' : '' }}" id="vert-tabs-5"
                data-toggle="pill" href="#tabs-5" role="tab" aria-controls="tabs-5" aria-selected="false">
                <i class="fas fa-code"></i>
                &nbsp;Custom CSS
            </a>
            @endcan
            @can('notification_settings')
            <a class="nav-link {{ @$_GET['active-tab'] == 'notification-settings' ? 'active' : '' }}" id="vert-tabs-6"
                data-toggle="pill" href="#tabs-6" role="tab" aria-controls="tabs-6" aria-selected="false">
                <i class="fas fa-envelope"></i>
                &nbsp;Notification Settings
            </a>
            @endcan
            @can('website_status_settings')
            <a class="nav-link {{ @$_GET['active-tab'] == 'website-status' ? 'active' : '' }}" id="vert-tabs-7"
                data-toggle="pill" href="#tabs-7" role="tab" aria-controls="tabs-7" aria-selected="false">
                <i class="fas fa-power-off"></i>
                &nbsp;Website Status
            </a>
            @endcan
            @can('invoice_settings')
            <a class="nav-link {{ @$_GET['active-tab'] == 'invoice-settings' ? 'active' : '' }}" id="vert-tabs-8"
                data-toggle="pill" href="#tabs-8" role="tab" aria-controls="tabs-8" aria-selected="false">
                <i class="fas fa-file-invoice"></i>
                &nbsp;Invoice Settings
            </a>
            @endcan
            <a class="nav-link {{ @$_GET['active-tab'] == 'fbr-token-settings' ? 'active' : '' }}" id="vert-tabs-9"
                data-toggle="pill" href="#tabs-9" role="tab" aria-controls="tabs-9" aria-selected="false">
                <i class="fas fa-key"></i>
                &nbsp;Token Settings
            </a>

            <a class="nav-link {{ @$_GET['active-tab'] == 'turn_off_stock_restriction' ? 'active' : '' }}"
                id="vert-tabs-10" data-toggle="pill" href="#tabs-10" role="tab" aria-controls="tabs-10"
                aria-selected="false">
                <i class="fas fa-toggle-off"></i>
                &nbsp; Turn off Stock Restriction
            </a>

        </div>
    </div>
    <div class="col-8 col-sm-10">
        <div class="tab-content" id="vert-tabs-tabContent">
            @can('website_settings')
            <div class="tab-pane fade {{ @$_GET['active-tab'] == 'website-info' ? 'active show' : '' }}" id="tabs-1"
                role="tabpanel" aria-labelledby="vert-tabs-1">

                <form action="{{ route('backend.admin.settings.website.info.update') }}" method="post">
                    @csrf
                    <div class="col-md-12 d-flex justify-content-between">
                        <h5>
                            <i class="fas fa-desktop"></i>
                            &nbsp;&nbsp;Website Info
                        </h5>
                        <button type="submit" class="btn action-button">
                            <i class="fas fa-reply"></i>
                            &nbsp;Save Changes
                        </button>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Website Title</label>
                            <input class="form-control" name="site_name" type="text"
                                value="{{ readConfig('site_name') }}" placeholder="Enter Site Title">
                        </div>
                        <div class="form-group">
                            <label>Meta Description</label>
                            <textarea class="form-control" rows="2" name="meta_description" cols="50"
                                placeholder="Enter Meta Description">{{ readConfig('meta_description') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Meta Keywords</label>
                            <textarea class="form-control" rows="2" name="meta_keywords" cols="50"
                                placeholder="Enter Keywords">{{ readConfig('meta_keywords') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Website URL</label>
                            <input class="form-control" name="site_url" type="url" value="{{ readConfig('site_url') }}"
                                placeholder="Enter Site URL">
                        </div>
                    </div>
                </form>

            </div>
            @endcan
            @can('contact_settings')
            <div class="tab-pane fade {{ @$_GET['active-tab'] == 'contacts' ? 'active show' : '' }}" id="tabs-2"
                role="tabpanel" aria-labelledby="vert-tabs-2">

                <form action="{{ route('backend.admin.settings.website.contacts.update') }}" method="post">
                    @csrf
                    <div class="col-md-12 d-flex justify-content-between">
                        <h5>
                            <i class="fas fa-address-book"></i>
                            &nbsp;&nbsp;Contacts
                        </h5>
                        <button type="submit" class="btn action-button">
                            <i class="fas fa-reply"></i>
                            &nbsp;Save Changes
                        </button>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Address</label>
                            <input placeholder="" class="form-control" name="contact_address" type="text"
                                value="{{ readConfig('contact_address') }}">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input placeholder="Phone" class="form-control" name="contact_phone" type="tel"
                                value="{{ readConfig('contact_phone') }}">
                        </div>
                        <div class="form-group">
                            <label>Fax</label>
                            <input placeholder="Fax" class="form-control" name="contact_fax" type="tel"
                                value="{{ readConfig('contact_fax') }}">
                        </div>
                        <div class="form-group">
                            <label>Mobile</label>
                            <input placeholder="Mobile" class="form-control" name="contact_mobile" type="tel"
                                value="{{ readConfig('contact_mobile') }}">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input placeholder="Email" class="form-control" name="contact_email" type="email"
                                value="{{ readConfig('contact_email') }}">
                        </div>
                        <div class="form-group">
                            <label>Working Time</label>
                            <input placeholder="Sunday to Thursday 08:00 AM to 05:00 PM" class="form-control"
                                name="working_hour" type="text" value="{{ readConfig('working_hour') }}">
                        </div>
                    </div>
                </form>

            </div>
            @endcan
            @can('socials_settings')
            <div class="tab-pane fade {{ @$_GET['active-tab'] == 'social-links' ? 'active show' : '' }}" id="tabs-3"
                role="tabpanel" aria-labelledby="vert-tabs-3">
                <form action="{{ route('backend.admin.settings.website.social.link.update') }}" method="post">
                    @csrf
                    <div class="col-md-12 d-flex justify-content-between">
                        <h5>
                            <i class="fas fa-share-alt"></i>
                            &nbsp;&nbsp;Social Links
                        </h5>
                        <button type="submit" class="btn action-button">
                            <i class="fas fa-reply"></i>
                            &nbsp;Save Changes
                        </button>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>
                                <i class="fab fa-facebook"></i>
                                &nbsp; Facebook
                            </label>
                            <input placeholder="Facebook" class="form-control" name="facebook_link" type="url"
                                value="{{ readConfig('facebook_link') }}">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fab fa-twitter"></i>
                                &nbsp; Twitter
                            </label>
                            <input placeholder="Twitter" class="form-control" name="twitter_link" type="url"
                                value="{{ readConfig('twitter_link') }}">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fab fa-linkedin"></i>
                                &nbsp; Linkedin
                            </label>
                            <input placeholder="Linkedin" class="form-control" name="linkedin_link" type="url"
                                value="{{ readConfig('linkedin_link') }}">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fab fa-youtube"></i>
                                &nbsp; Youtube
                            </label>
                            <input placeholder="Youtube" class="form-control" name="youtube_link" type="url"
                                value="{{ readConfig('youtube_link') }}">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fab fa-instagram"></i>
                                &nbsp; Instagram
                            </label>
                            <input placeholder="Instagram" class="form-control" name="instagram_link" type="url"
                                value="{{ readConfig('instagram_link') }}">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fab fa-pinterest"></i>
                                &nbsp; Pinterest
                            </label>
                            <input placeholder="Pinterest" class="form-control" name="pinterest_link" type="url"
                                value="{{ readConfig('pinterest_link') }}">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fab fa-tumblr"></i>
                                &nbsp; Tumblr
                            </label>
                            <input placeholder="Tumblr" class="form-control" name="tumblr_link" type="url"
                                value="{{ readConfig('tumblr_link') }}">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fab fa-snapchat"></i>
                                &nbsp; Snapchat
                            </label>
                            <input placeholder="Snapchat" class="form-control" name="snapchat_link" type="url"
                                value="{{ readConfig('snapchat_link') }}">
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fab fa-whatsapp"></i>
                                &nbsp; Whatsapp
                            </label>
                            <input placeholder="Whatsapp" class="form-control" name="whatsapp_link" type="url"
                                value="{{ readConfig('whatsapp_link') }}">
                        </div>
                    </div>
                </form>
            </div>
            @endcan
            @can('style_settings')
            <div class="tab-pane fade {{ @$_GET['active-tab'] == 'style-settings' ? 'active show' : '' }}" id="tabs-4"
                role="tabpanel" aria-labelledby="vert-tabs-4">

                <form action="{{ route('backend.admin.settings.website.style.settings.update') }}" method="post"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="col-md-12 d-flex justify-content-between">
                        <h5>
                            <i class="fas fa-swatchbook"></i>
                            &nbsp;&nbsp;Style Settings
                        </h5>
                        <button type="submit" class="btn action-button">
                            <i class="fas fa-reply"></i>
                            &nbsp;Save Changes
                        </button>
                    </div>

                    <div class="col-12 my-2">
                        <label>Site Logo</label>

                        <div class="row">
                            <div class="col-sm-12">
                                <div class="col-sm-12 box p-a-xs text-center">
                                    <img src="{{ assetImage(readconfig('site_logo')) }}"
                                        class="img-fluid thumbnail-preview site-logo-placeholder">
                                </div>
                            </div>
                        </div>
                        <input class="form-control" accept="image/*" name="site_logo" type="file"
                            onchange="previewThumbnail(this)">
                        <small>
                            <i class="far fa-question-circle"></i>
                            ( 260x60 px ) - Extensions: .png, .jpg, .jpeg, .gif, .svg
                        </small>
                    </div>
                    <hr>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="style_fav">Favicon</label>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="col-sm-12 box p-a-xs text-center">
                                        <a target="_blank" href="{{ assetImage(readconfig('favicon_icon')) }}">
                                            <img src="{{ assetImage(readconfig('favicon_icon')) }}"
                                                class="img-fluid thumbnail-preview site-logo-placeholder">
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <input class="form-control" accept="image/*" name="favicon_icon" type="file"
                                onchange="previewThumbnail(this)">
                            <small>
                                <i class="far fa-question-circle"></i>
                                ( 32x32 px ) - Extensions: .png, .jpg, .jpeg, .gif, .svg
                            </small>
                        </div>
                        <div class="col-sm-6">
                            <label for="style_apple">Apple Icon</label>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="col-sm-12 box p-a-xs text-center">
                                        <a target="_blank" href="{{ assetImage(readconfig('favicon_icon_apple')) }}">
                                            <img src="{{ assetImage(readconfig('favicon_icon_apple')) }}"
                                                class="img-fluid thumbnail-preview site-logo-placeholder">
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <input class="form-control" accept="image/*" name="favicon_icon_apple" type="file"
                                onchange="previewThumbnail(this)">
                            <small>
                                <i class="far fa-question-circle"></i>
                                ( 180x180 px ) - Extensions: .png, .jpg, .jpeg, .gif, .svg
                            </small>
                        </div>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label>Newsletter Subscribe</label>
                        <div class="radio bg-white rounded pt-2 pl-2 border">
                            <label class="ui-check ui-check-md">
                                <input {{ readConfig('newsletter_subscribe') == 1 ? 'checked' : '' }}
                                    name="newsletter_subscribe" type="radio" value="1">
                                <i class="dark-white"></i>
                                Active
                            </label>
                            &nbsp; &nbsp;
                            <label class="ui-check ui-check-md">
                                <input {{ readConfig('newsletter_subscribe') == 0 ? 'checked' : '' }}
                                    name="newsletter_subscribe" type="radio" value="0">
                                <i class="dark-white"></i>
                                Not Active
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            @endcan
            @can('custom_settings')
            <div class="tab-pane fade {{ @$_GET['active-tab'] == 'custom-css' ? 'active show' : '' }}" id="tabs-5"
                role="tabpanel" aria-labelledby="vert-tabs-5">
                <form action="{{ route('backend.admin.settings.website.custom.css.update') }}" method="post">
                    @csrf
                    <div class="col-md-12 d-flex justify-content-between">
                        <h5>
                            <i class="fas fa-code"></i>
                            &nbsp;&nbsp;Custom CSS
                        </h5>
                        <button type="submit" class="btn action-button">
                            <i class="fas fa-reply"></i>
                            &nbsp;Save Changes
                        </button>
                    </div>
                    <div class="col-md-12 mt-2">
                        <div class="form-group">
                            <textarea placeholder="" class="form-control" rows="17" name="custom_css"
                                cols="50">{{ readConfig('custom_css') }}</textarea>
                        </div>
                    </div>
                </form>
            </div>
            @endcan
            @can('notification_settings')
            <div class="tab-pane fade {{ @$_GET['active-tab'] == 'notification-settings' ? 'active show' : '' }}"
                id="tabs-6" role="tabpanel" aria-labelledby="vert-tabs-6">
                <form action="{{ route('backend.admin.settings.website.notification.settings.update') }}" method="post">
                    @csrf
                    <div class="col-md-12 d-flex justify-content-between">
                        <h5>
                            <i class="fas fa-envelope"></i>
                            &nbsp;&nbsp;Notification Settings
                        </h5>
                        <button type="submit" class="btn action-button">
                            <i class="fas fa-reply"></i>
                            &nbsp;Save Changes
                        </button>
                    </div>
                    <div class="p-a-md col-md-12">
                        <div class="form-group">
                            <label>Website Notification Email</label>
                            <input placeholder="Enter email" class="form-control" name="notify_email_address"
                                type="email" value="{{ readConfig('notify_email_address') }}">
                        </div>
                        <div class="form-group">
                            <label>Send me an email on new contact Messages : </label>
                            <div class="radio bg-white rounded pt-2 pl-2 border">
                                <label class="ui-check ui-check-md">
                                    <input {{ readConfig('notify_messages_status') == 1 ? 'checked' : '' }}
                                        name="notify_messages_status" type="radio" value="1">
                                    <i class="dark-white"></i>
                                    Yes
                                </label>
                                &nbsp; &nbsp;
                                <label class="ui-check ui-check-md">
                                    <input {{ readConfig('notify_messages_status') == 0 ? 'checked' : '' }}
                                        name="notify_messages_status" type="radio" value="0">
                                    <i class="dark-white"></i>
                                    No
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Send me an email on new Comments : </label>
                            <div class="radio bg-white rounded pt-2 pl-2 border">
                                <label class="ui-check ui-check-md">
                                    <input {{ readConfig('notify_comments_status') == 1 ? 'checked' : '' }}
                                        name="notify_comments_status" type="radio" value="1">
                                    <i class="dark-white"></i>
                                    Yes
                                </label>
                                &nbsp; &nbsp;
                                <label class="ui-check ui-check-md">
                                    <input {{ readConfig('notify_comments_status') == 0 ? 'checked' : '' }}
                                        name="notify_comments_status" type="radio" value="0">
                                    <i class="dark-white"></i>
                                    No
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            @endcan
            @can('website_status_settings')
            <div class="tab-pane fade {{ @$_GET['active-tab'] == 'website-status' ? 'active show' : '' }}" id="tabs-7"
                role="tabpanel" aria-labelledby="vert-tabs-7">
                <form action="{{ route('backend.admin.settings.website.status.update') }}" method="post">
                    @csrf
                    <div class="col-md-12 d-flex justify-content-between">
                        <h5>
                            <i class="fas fa-power-off"></i>
                            &nbsp;&nbsp;Website Status
                        </h5>
                        <button type="submit" class="btn action-button">
                            <i class="fas fa-reply"></i>
                            &nbsp;Save Changes
                        </button>
                    </div>
                    <div class="p-a-md col-md-12">
                        <div class="form-group">
                            <label>Website Status : </label>
                            <div class="radio bg-white rounded pt-2 pl-2 border">
                                <label class="ui-check ui-check-md">
                                    <input {{ readConfig('is_live') == 1 ? 'checked' : '' }} name="is_live" type="radio"
                                        value="1">
                                    <i class="dark-white"></i>
                                    Active
                                </label>
                                &nbsp; &nbsp;
                                <label class="ui-check ui-check-md">
                                    <input {{ readConfig('is_live') == 0 ? 'checked' : '' }} name="is_live" type="radio"
                                        value="0">
                                    <i class="dark-white"></i>
                                    Not Active
                                </label>
                            </div>
                        </div>

                        <div class="form-group {{ readConfig('is_live') == 1 ? 'd-none' : '' }}" id="close_msg_div">
                            <label>Close Message</label>
                            <textarea placeholder="Close Message" class="form-control" rows="4" name="close_msg"
                                cols="50">Website under maintenance&lt;h1&gt;Comming SOON&lt;/h1&gt;</textarea>
                        </div>
                    </div>
                </form>
            </div>
            @endcan
            @can('invoice_settings')
            <div class="tab-pane fade {{ @$_GET['active-tab'] == 'invoice-settings' ? 'active show' : '' }}" id="tabs-8"
                role="tabpanel" aria-labelledby="vert-tabs-8">

                <form action="{{ route('backend.admin.settings.website.invoice.update') }}" method="post">
                    @csrf
                    <div class="col-md-12 d-flex justify-content-between">
                        <h5>
                            <i class="fas fa-file-invoice"></i>
                            &nbsp;&nbsp;Invoice Settings
                        </h5>
                        <button type="submit" class="btn action-button">
                            <i class="fas fa-reply"></i>
                            &nbsp;Save Changes
                        </button>
                    </div>

                    <div class="col-md-12 mt-3">
                        {{-- <div class="form-group">
                <label>Note to customer message (Optional)</label>
                <input type="text" class="form-control" placeholder="Enter message for invoice" 
                       name="note_message" value="{{ old('note_message', $invoiceSetting->note_message ?? '') }}">
                        <small class="text-muted">Yeh message tab show hoga jab "Note to customer" switch ON
                            hogi</small>
                    </div> --}}

                    <div class="form-group">
                        <label for="note_msg">Above Note Message</label>
                        <input type="text" class="form-control" id="note_msg" name="note_msg"
                            value="{{ $invoiceSetting->note_msg ?? '' }}"
                            placeholder="Enter message to display above note">
                    </div>

                    <!-- Logo Switch -->
                    <div class="form-group d-flex align-items-center">
                        <label class="switch">
                            <input type="hidden" name="is_show_logo_invoice" value="0">
                            <input type="checkbox" name="is_show_logo_invoice" value="1"
                                {{ isset($invoiceSetting) && $invoiceSetting->logo == 1 ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                        <span class="ml-3">Show Logo on Invoice</span>
                    </div>

                    <!-- Site Name Switch -->
                    <div class="form-group d-flex align-items-center">
                        <label class="switch">
                            <input type="hidden" name="is_show_site_invoice" value="0">
                            <input type="checkbox" name="is_show_site_invoice" value="1"
                                {{ isset($invoiceSetting) && $invoiceSetting->site_name == 1 ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                        <span class="ml-3">Show Site Name</span>
                    </div>

                    <!-- Phone Switch -->
                    <div class="form-group d-flex align-items-center">
                        <label class="switch">
                            <input type="hidden" name="is_show_phone_invoice" value="0">
                            <input type="checkbox" name="is_show_phone_invoice" value="1"
                                {{ isset($invoiceSetting) && $invoiceSetting->phone == 1 ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                        <span class="ml-3">Show Phone Number</span>
                    </div>

                    <!-- Email Switch -->
                    <div class="form-group d-flex align-items-center">
                        <label class="switch">
                            <input type="hidden" name="is_show_email_invoice" value="0">
                            <input type="checkbox" name="is_show_email_invoice" value="1"
                                {{ isset($invoiceSetting) && $invoiceSetting->email == 1 ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                        <span class="ml-3">Show Email Address</span>
                    </div>

                    <!-- Address Switch -->
                    <div class="form-group d-flex align-items-center">
                        <label class="switch">
                            <input type="hidden" name="is_show_address_invoice" value="0">
                            <input type="checkbox" name="is_show_address_invoice" value="1"
                                {{ isset($invoiceSetting) && $invoiceSetting->address == 1 ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                        <span class="ml-3">Show Address</span>
                    </div>

                    <!-- Customer Switch -->
                    <div class="form-group d-flex align-items-center">
                        <label class="switch">
                            <input type="hidden" name="is_show_customer_invoice" value="0">
                            <input type="checkbox" name="is_show_customer_invoice" value="1"
                                {{ isset($invoiceSetting) && $invoiceSetting->customer == 1 ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                        <span class="ml-3">Show Customer Details</span>
                    </div>

                    <!-- Invoice Number Switch -->
                    <div class="form-group d-flex align-items-center">
                        <label class="switch">
                            <input type="hidden" name="is_show_invoice_number_invoice" value="0">
                            <input type="checkbox" name="is_show_invoice_number_invoice" value="1"
                                {{ isset($invoiceSetting) && $invoiceSetting->invoice_number == 1 ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                        <span class="ml-3">Show Custom Invoice Number</span>
                    </div>

                    <!-- Imc Logo Switch -->
                    <div class="form-group d-flex align-items-center">
                        <label class="switch">
                            <input type="hidden" name="is_show_imc_logo_invoice" value="0">
                            <input type="checkbox" name="is_show_imc_logo_invoice" value="1"
                                {{ isset($invoiceSetting) && $invoiceSetting->imc_logo == 1 ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                        <span class="ml-3">Show IMC Logo</span>
                    </div>

                    <!-- Thanks for Shopping Switch -->
                    <div class="form-group d-flex align-items-center">
                        <label class="switch">
                            <input type="hidden" name="is_show_thanks_for_shopping_invoice" value="0">
                            <input type="checkbox" name="is_show_thanks_for_shopping_invoice" value="1"
                                {{ isset($invoiceSetting) && $invoiceSetting->thanks_for_shopping == 1 ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                        <span class="ml-3">Show Thanks for Shopping</span>
                    </div>

                    <!-- Note to Customer Switch -->
                    <div class="form-group d-flex align-items-center">
                        <label class="switch">
                            <input type="hidden" name="note_to_customer" value="0">
                            <input type="checkbox" name="note_to_customer" value="1"
                                {{ isset($invoiceSetting) && $invoiceSetting->note_to_customer == 1 ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                        <span class="ml-3">Show Note to Customer</span>
                    </div>

                    <!-- POS Invoice Width -->
                    <div class="form-group">
                        <label>POS Invoice Width</label>
                        <select name="receiptMaxwidth" class="form-control col-6">
                            <option value="300px"
                                {{ (isset($invoiceSetting) && $invoiceSetting->post_invoice_width == '300px') ? 'selected' : '' }}>
                                Small</option>
                            <option value="400px"
                                {{ (isset($invoiceSetting) && $invoiceSetting->post_invoice_width == '400px') ? 'selected' : '' }}>
                                Medium</option>
                            <option value="500px"
                                {{ (isset($invoiceSetting) && $invoiceSetting->post_invoice_width == '500px') ? 'selected' : '' }}>
                                Large</option>
                        </select>
                    </div>
            </div>
            </form>
        </div>
        @endcan
        @can('invoice_settings')
        <div class="tab-pane fade {{ @$_GET['active-tab'] == 'fbr-token-settings' ? 'active show' : '' }}" id="tabs-9"
            role="tabpanel" aria-labelledby="vert-tabs-9">

            <form action="{{ route('backend.admin.settings.website.fbr.token.update') }}" method="post">
                @csrf

                <div class="col-md-12 d-flex justify-content-between">
                    <h5>
                        <i class="fas fa-key"></i>
                        &nbsp;&nbsp;FBR Token Settings
                    </h5>
                    <button type="submit" class="btn action-button">
                        <i class="fas fa-save"></i>
                        &nbsp;Save Changes
                    </button>
                </div>

                <div class="col-md-12 mt-3">

                    {{-- FBR Integration --}}
                    <div class="card p-3 mb-3">
                        <div class="form-group mb-0">
                            <label>
                                <strong>FBR Integration</strong>
                            </label>

                            <div class="custom-control custom-switch">
                                <input type="checkbox"
                                    class="custom-control-input"
                                    id="fbr_integration"
                                    name="fbr_integration"
                                    value="enabled"
                                    {{ ($fbrToken->fbr_integration ?? 'disabled') === 'enabled' ? 'checked' : '' }}>

                                <label class="custom-control-label" for="fbr_integration">
                                    Enable FBR Integration
                                </label>
                            </div>

                            <small class="text-muted">
                                Enable FBR integration to configure FBR tokens.
                            </small>
                        </div>
                    </div>
                    <div id="fbr-token-fields"
                        style="{{ ($fbrToken->fbr_integration ?? 'disabled') === 'enabled' ? '' : 'display: none;' }}">
                        {{-- POS Tokens --}}
                        <div class="card p-3 mb-3">
                            <h6 class="mb-3"><strong>POS Tokens</strong></h6>

                            <div class="form-group">
                                <label>POS Sandbox Token</label>
                                <input type="text" name="pos_sandbox_token" class="form-control"
                                    value="{{ $fbrToken->pos_sandbox_token ?? '' }}" placeholder="Enter POS sandbox token">
                            </div>

                            <div class="form-group">
                                <label>POS Production Token</label>
                                <input type="text" name="pos_production_token" class="form-control"
                                    value="{{ $fbrToken->pos_production_token ?? '' }}"
                                    placeholder="Enter POS production token">
                            </div>
                        </div>

                        {{-- PRAL Tokens --}}
                        <div class="card p-3 mb-3">
                            <h6 class="mb-3"><strong>PRA Tokens</strong></h6>

                            <div class="form-group">
                                <label>PRA Sandbox Token</label>
                                <input type="text" name="pral_sandbox_token" class="form-control"
                                    value="{{ $fbrToken->pral_sandbox_token ?? '' }}" placeholder="Enter PRA Sandbox Token">
                            </div>

                            <div class="form-group">
                                <label>PRA Production Token</label>
                                <input type="text" name="pral_production_token" class="form-control"
                                    value="{{ $fbrToken->pral_production_token ?? '' }}"
                                    placeholder="Enter PRA Production Token">
                            </div>
                        </div>

                        {{-- Digital Tokens --}}
                        <div class="card p-3 mb-3">
                            <h6 class="mb-3"><strong>Digital Invoice Tokens</strong></h6>

                            <div class="form-group">
                                <label>Digital Sandbox Token</label>
                                <input type="text" name="digital_sandbox_token" class="form-control"
                                    value="{{ $fbrToken->digital_sandbox_token ?? '' }}"
                                    placeholder="Enter Digital sandbox token">
                            </div>

                            <div class="form-group">
                                <label>Digital Production Token</label>
                                <input type="text" name="digital_production_token" class="form-control"
                                    value="{{ $fbrToken->digital_production_token ?? '' }}"
                                    placeholder="Enter Digital production token">
                            </div>
                        </div>

                        {{-- Environment Selector --}}
                        <div class="form-group col-md-6">
                            <label>Select Environment</label>
                            <select name="environment" class="form-control">
                                <option value="0" {{ (isset($fbrToken) && $fbrToken->environment == 0) ? 'selected' : '' }}>
                                    Sandbox
                                </option>

                                <option value="1" {{ (isset($fbrToken) && $fbrToken->environment == 1) ? 'selected' : '' }}>
                                    Production
                                </option>
                            </select>
                            <small class="text-muted">
                                System will automatically use selected environment token.
                            </small>
                        </div>
                    </div>
                 </div>
            </form>
        </div>
        @endcan

        <div class="tab-pane fade {{ @$_GET['active-tab'] == 'turn_off_stock_restriction' ? 'active show' : '' }}"
            id="tabs-10" role="tabpanel" aria-labelledby="vert-tabs-10">

           
                <div class="col-md-12 d-flex justify-content-between">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="customSwitch1"
                        {{ $quantityValidationStatus == 'enabled' ? 'checked' : '' }}>
                        
                        <label class="custom-control-label" for="customSwitch1">switch to turn off stock restriction</label>
                    </div>
                </div>
            
        </div>

    </div>
</div>
</div>
@endsection

@push('script')
<script>
$('input[type=radio][name=is_live]').on("change", function() {
    if (this.value == '0') {
        $("#close_msg_div").removeClass('d-none');
    } else {
        $("#close_msg_div").addClass('d-none');
    }
});

// Checkbox values automatically update karne ke liye - CORRECTED VERSION
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.switch input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Hidden input update karein - agar checkbox checked hai toh hidden value 0 rahegi
            const hiddenInput = this.parentElement.querySelector('input[type="hidden"]');
            if (hiddenInput) {
                hiddenInput.value = this.checked ? '0' : '0'; // Hidden input always 0 rahega
            }

            // Debugging ke liye
            console.log('Checkbox changed:', this.name, 'Checked:', this.checked, 'Value:', this
                .value);
        });

        // Page load par bhi check karein
        const hiddenInput = checkbox.parentElement.querySelector('input[type="hidden"]');
        if (hiddenInput) {
            hiddenInput.value = checkbox.checked ? '0' : '0';
        }
    });
});

// Agar aapko manually value change karni hai toh yeh function use karein
function updateCheckboxValue(checkbox) {
    checkbox.value = checkbox.checked ? '1' : '0';
    // Hidden input ko bhi update karein
    const hiddenInput = checkbox.parentElement.querySelector('input[type="hidden"]');
    if (hiddenInput) {
        hiddenInput.value = checkbox.checked ? '0' : '0';
    }
}

$(document).ready(function(){
    

    $('#customSwitch1').on('change', function() {

        $('#fbr_integration').on('change', function () {

            if ($(this).is(':checked')) {
                $('#fbr-token-fields').slideDown();
            } else {
                $('#fbr-token-fields').slideUp();
            }

        });

        let status = $(this).is(':checked') ? 'enabled' : 'disabled';

        $.ajax({
           url: "{{ route('backend.admin.products.update-stock-setting') }}", 
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                status: status
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                } else {
                    showToast('Something went wrong', 'error');
                }
            },
            error: function() {
                showToast('Server error', 'error');
            }
        });

    });

});
</script>
@endpush