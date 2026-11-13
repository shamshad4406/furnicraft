<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SettingController extends Controller implements HasMiddleware
{
    /** Tabs rendered on the settings screen. */
    public const TABS = [
        'company' => ['label' => 'Company', 'icon' => 'bi-building'],
        'gst' => ['label' => 'GST & Legal', 'icon' => 'bi-receipt'],
        'mail' => ['label' => 'SMTP / Email', 'icon' => 'bi-envelope-at'],
        'localisation' => ['label' => 'Currency & Locale', 'icon' => 'bi-currency-rupee'],
        'order' => ['label' => 'Order Numbering', 'icon' => 'bi-hash'],
        'social' => ['label' => 'Social Links', 'icon' => 'bi-share'],
        'system' => ['label' => 'System', 'icon' => 'bi-sliders'],
    ];

    public function __construct(protected SettingService $settings) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings.view', only: ['index']),
            new Middleware('permission:settings.edit', only: ['update']),
        ];
    }

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'company');
        $tab = array_key_exists($tab, self::TABS) ? $tab : 'company';

        $values = Setting::query()->get()->groupBy('group');

        return view('admin.settings.index', [
            'tabs' => self::TABS,
            'activeTab' => $tab,
            'values' => $values,
            'settings' => $this->settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $group = $request->input('group', 'company');
        abort_unless(array_key_exists($group, self::TABS), 404);

        $validated = $request->validate($this->rulesFor($group));

        $files = ['company_logo', 'company_favicon'];

        foreach ($files as $key) {
            if ($request->hasFile($key)) {
                $this->settings->putFile($key, $request->file($key), 'branding', $group);
            }
            unset($validated[$key]);
        }

        // A blank secret means "keep the stored one", not "erase it".
        foreach (SettingService::ENCRYPTED as $key) {
            if (array_key_exists($key, $validated) && ($validated[$key] === null || $validated[$key] === '')) {
                unset($validated[$key]);
            }
        }

        // Checkboxes that are off are absent from the request.
        foreach ($this->booleanKeys($group) as $key) {
            $validated[$key] = $request->boolean($key) ? '1' : '0';
        }

        $this->settings->setMany($validated, $group);

        activity('settings')->causedBy(auth('admin')->user())
            ->withProperties(['group' => $group, 'keys' => array_keys($validated)])
            ->log('Updated '.self::TABS[$group]['label'].' settings');

        return redirect()->route('admin.settings.index', ['tab' => $group])
            ->with('success', self::TABS[$group]['label'].' settings saved.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rulesFor(string $group): array
    {
        return match ($group) {
            'company' => [
                'company_name' => ['required', 'string', 'max:120'],
                'company_tagline' => ['nullable', 'string', 'max:120'],
                'company_headline' => ['nullable', 'string', 'max:160'],
                'company_about' => ['nullable', 'string', 'max:500'],
                'company_email' => ['required', 'email', 'max:120'],
                'company_phone' => ['required', 'string', 'max:20'],
                'company_whatsapp' => ['nullable', 'string', 'max:20'],
                'company_address' => ['nullable', 'string', 'max:300'],
                'company_copyright' => ['nullable', 'string', 'max:160'],
                'company_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:1024'],
                'company_favicon' => ['nullable', 'image', 'mimes:png,jpg,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:256'],
            ],
            'gst' => [
                'gst_number' => ['nullable', 'string', 'regex:/^[0-9A-Z]{15}$/'],
                'gst_legal_name' => ['nullable', 'string', 'max:160'],
                'gst_state' => ['nullable', 'string', 'max:60'],
                'gst_state_code' => ['nullable', 'string', 'max:2'],
                'gst_pan' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
                'gst_cin' => ['nullable', 'string', 'max:30'],
                'gst_default_rate' => ['required', 'numeric', 'between:0,28'],
            ],
            'mail' => [
                'mail_host' => ['required', 'string', 'max:160'],
                'mail_port' => ['required', 'integer', 'between:1,65535'],
                'mail_username' => ['nullable', 'string', 'max:160'],
                'mail_password' => ['nullable', 'string', 'max:160'],
                'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
                'mail_from_address' => ['required', 'email', 'max:160'],
                'mail_from_name' => ['required', 'string', 'max:120'],
            ],
            'localisation' => [
                'currency_code' => ['required', 'string', 'size:3'],
                'currency_symbol' => ['required', 'string', 'max:5'],
                'timezone' => ['required', 'timezone'],
                'date_format' => ['required', 'in:d-m-Y,d/m/Y,Y-m-d,d M Y'],
            ],
            'order' => [
                'order_prefix' => ['required', 'string', 'max:6', 'regex:/^[A-Z0-9]+$/'],
                'order_number_format' => ['required', 'string', 'max:60'],
                'order_sequence_padding' => ['required', 'integer', 'between:1,10'],
                'order_next_sequence' => ['required', 'integer', 'min:1'],
                'invoice_prefix' => ['required', 'string', 'max:6', 'regex:/^[A-Z0-9]+$/'],
                'quotation_prefix' => ['required', 'string', 'max:6', 'regex:/^[A-Z0-9]+$/'],
            ],
            'social' => [
                'social_facebook' => ['nullable', 'url', 'max:200'],
                'social_youtube' => ['nullable', 'url', 'max:200'],
                'social_instagram' => ['nullable', 'url', 'max:200'],
                'social_linkedin' => ['nullable', 'url', 'max:200'],
            ],
            'system' => [
                'admin_login_throttle' => ['required', 'integer', 'between:1,20'],
            ],
            default => [],
        };
    }

    /**
     * @return string[]
     */
    protected function booleanKeys(string $group): array
    {
        return $group === 'system' ? ['maintenance_mode'] : [];
    }
}
