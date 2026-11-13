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
            'quotation_prefix' => ['QT', 'text'],
        ],
        'social' => [
            'social_facebook' => ['https://facebook.com/', 'text'],
            'social_youtube' => ['https://youtube.com/', 'text'],
            'social_instagram' => ['https://instagram.com/', 'text'],
            'social_linkedin' => ['https://linkedin.com/', 'text'],
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
