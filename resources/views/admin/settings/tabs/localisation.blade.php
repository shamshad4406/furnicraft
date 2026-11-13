<div class="row g-3">
    <x-admin.input name="currency_code" label="Currency code" :value="settings('currency_code')" required
                   help="Three letters, for example INR." />
    <x-admin.input name="currency_symbol" label="Currency symbol" :value="settings('currency_symbol')" required
                   help="Used everywhere prices are shown." />
    <x-admin.select name="timezone" label="Timezone" :value="settings('timezone')" required
                    :options="['Asia/Kolkata' => 'Asia/Kolkata (IST)', 'Asia/Dubai' => 'Asia/Dubai', 'UTC' => 'UTC']" />
    <x-admin.select name="date_format" label="Date format" :value="settings('date_format')" required
                    :options="[
                        'd-m-Y' => 'DD-MM-YYYY ('.now()->format('d-m-Y').')',
                        'd/m/Y' => 'DD/MM/YYYY ('.now()->format('d/m/Y').')',
                        'Y-m-d' => 'YYYY-MM-DD ('.now()->format('Y-m-d').')',
                        'd M Y' => 'DD Mon YYYY ('.now()->format('d M Y').')',
                    ]" />
</div>
