<div class="alert alert-light border small d-flex gap-2">
    <i class="bi bi-info-circle-fill text-primary"></i>
    <div>
        These values are stored for the live mail server. Until they are switched on, Laravel uses the
        <code>MAIL_*</code> entries in <code>.env</code> (currently <code>{{ config('mail.default') }}</code>).
    </div>
</div>

<div class="row g-3">
    <x-admin.input name="mail_host" label="SMTP host" :value="settings('mail_host')" required />
    <x-admin.input name="mail_port" label="SMTP port" type="number" :value="settings('mail_port')" required />
    <x-admin.input name="mail_username" label="SMTP username" :value="settings('mail_username')" autocomplete="off" />
    {{-- Never echoed back into the HTML; blank means "keep what is stored". --}}
    <x-admin.input name="mail_password" label="SMTP password" type="password" :value="null"
                   autocomplete="new-password"
                   :placeholder="settings()->hasSecret('mail_password') ? '•••••••• (saved)' : ''"
                   :help="settings()->hasSecret('mail_password')
                        ? 'A password is saved and encrypted. Leave blank to keep it, or type a new one to replace it.'
                        : 'Encrypted at rest. Use an app password, not the account password.'" />
    <x-admin.select name="mail_encryption" label="Encryption" :value="settings('mail_encryption')"
                    :options="['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None']" />
    <x-admin.input name="mail_from_name" label="From name" :value="settings('mail_from_name')" required />
    <x-admin.input name="mail_from_address" label="From address" type="email" :value="settings('mail_from_address')" required />
</div>
