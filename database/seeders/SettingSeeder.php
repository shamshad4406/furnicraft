<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * group => [key => [value, type]]
     *
     * @var array<string, array<string, array{0: string|null, 1: string}>>
     */
    protected array $settings = [
        'company' => [
            'company_name' => ['FurniCraft', 'text'],
            'company_tagline' => ['Style • Comfort • Durability', 'text'],
            'company_headline' => ['Premium Quality Furniture for a Better Living', 'text'],
            'company_about' => ['Your trusted partner for stylish and durable furniture solutions.', 'textarea'],
            'company_email' => ['info@furnicraft.in', 'text'],
            'company_phone' => ['+91 98765 43210', 'text'],
            'company_whatsapp' => ['+91 98765 43210', 'text'],
            'company_address' => ["123 Furniture Street,\nNew Delhi - 110001", 'textarea'],
            'company_logo' => [null, 'file'],
            'company_favicon' => [null, 'file'],
            'company_copyright' => ['© 2026 FurniCraft. All Rights Reserved.', 'text'],
        ],
        'gst' => [
            'gst_number' => ['07AABCF1234M1Z5', 'text'],
            'gst_legal_name' => ['FurniCraft Furnitures Pvt. Ltd.', 'text'],
            'gst_state' => ['Delhi', 'text'],
            'gst_state_code' => ['07', 'text'],
            'gst_pan' => ['AABCF1234M', 'text'],
            'gst_cin' => ['', 'text'],
            'gst_default_rate' => ['18', 'number'],
        ],
        'mail' => [
            'mail_host' => ['127.0.0.1', 'text'],
            'mail_port' => ['2525', 'number'],
            'mail_username' => ['', 'text'],
            'mail_password' => ['', 'text'],
            'mail_encryption' => ['tls', 'select'],
            'mail_from_address' => ['no-reply@furnicraft.in', 'text'],
            'mail_from_name' => ['FurniCraft', 'text'],
        ],
        'localisation' => [
            'currency_code' => ['INR', 'text'],
            'currency_symbol' => ['₹', 'text'],
            'timezone' => ['Asia/Kolkata', 'text'],
            'date_format' => ['d-m-Y', 'select'],
        ],
        'order' => [
            'order_prefix' => ['FC', 'text'],
            'order_number_format' => ['{PREFIX}/{YY}{MM}/{SEQ}', 'text'],
            'order_sequence_padding' => ['5', 'number'],
            'order_next_sequence' => ['1', 'number'],
            'invoice_prefix' => ['INV', 'text'],
            'invoice_next_sequence' => ['1', 'number'],
            'invoice_terms' => ["Goods once sold will only be taken back as per our return policy.\nWarranty is as per the manufacturer's terms.", 'textarea'],
            'quotation_prefix' => ['QT', 'text'],
            'quotation_next_sequence' => ['1', 'number'],
            'enquiry_prefix' => ['ENQ', 'text'],
            'enquiry_next_sequence' => ['1', 'number'],
            'purchase_prefix' => ['PO', 'text'],
            'purchase_next_sequence' => ['1', 'number'],
            // How long a quotation stands before it lapses.
            'quotation_validity_days' => ['15', 'number'],
            'quotation_terms' => ["Prices hold until the validity date above.\nDelivery timelines are confirmed once the order is placed.\n50% advance is required on made-to-order items.", 'textarea'],
            // Days after delivery in which a customer may raise a return.
            // Zero turns self-service returns off entirely.
            'return_window_days' => ['7', 'number'],
            'return_policy_note' => ['Items can be returned within 7 days of delivery if they are unused and in their original packaging.', 'textarea'],
        ],
        'social' => [
            'social_facebook' => ['https://facebook.com/', 'text'],
            'social_youtube' => ['https://youtube.com/', 'text'],
            'social_instagram' => ['https://instagram.com/', 'text'],
            'social_linkedin' => ['https://linkedin.com/', 'text'],
        ],
        'checkout' => [
            'cod_enabled' => ['1', 'boolean'],
            'cod_advance_limit' => ['20000', 'number'],
            'cod_advance_percent' => ['20', 'number'],
            'guest_checkout_enabled' => ['1', 'boolean'],
            'min_order_value' => ['0', 'number'],
            'checkout_terms_note' => ['Delivery dates are confirmed by phone after the order is placed.', 'textarea'],
        ],
        'marketing' => [
            // Off on staging, so a half-built shop never outranks the real one.
            'seo_allow_indexing' => ['1', 'boolean'],
            'google_analytics_id' => ['', 'text'],
            'google_site_verification' => ['', 'text'],
            'reviews_enabled' => ['1', 'boolean'],
            // Honest but strict: only buyers may review. Most shops leave
            // this off and rely on the "verified purchase" badge instead.
            'reviews_require_purchase' => ['0', 'boolean'],
            'newsletter_enabled' => ['1', 'boolean'],
            'newsletter_double_optin' => ['1', 'boolean'],
            'newsletter_intro' => ['New arrivals, offers and nothing else. One mail a month at most.', 'textarea'],
        ],
        'inventory' => [
            // A product with no threshold of its own falls back to this.
            'low_stock_threshold' => ['5', 'number'],
            'stock_alert_email' => ['', 'text'],
        ],
        'system' => [
            'maintenance_mode' => ['0', 'boolean'],
            'admin_login_throttle' => ['5', 'number'],
        ],
    ];

    public function run(): void
    {
        $count = 0;

        foreach ($this->settings as $group => $rows) {
            foreach ($rows as $key => [$value, $type]) {
                Setting::query()->firstOrCreate(
                    ['key' => $key],
                    ['group' => $group, 'value' => $value, 'type' => $type]
                );
                $count++;
            }
        }

        app(SettingService::class)->flush();

        $this->command?->info("Seeded {$count} settings.");
    }
}
