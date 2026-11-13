<div class="row g-3">
    <x-admin.input name="company_name" label="Company name" :value="settings('company_name')" required />
    <x-admin.input name="company_tagline" label="Tagline" :value="settings('company_tagline')"
                   help="Shown under the logo." />

    <x-admin.input name="company_headline" label="Top-bar headline" col="col-12" :value="settings('company_headline')" />

    <x-admin.input name="company_email" label="Email address" type="email" :value="settings('company_email')" required />
    <x-admin.input name="company_phone" label="Phone number" :value="settings('company_phone')" required />
    <x-admin.input name="company_whatsapp" label="WhatsApp number" :value="settings('company_whatsapp')"
                   help="Used by the click-to-chat button in Phase 6." />
    <x-admin.input name="company_copyright" label="Footer copyright" :value="settings('company_copyright')" />

    <x-admin.textarea name="company_address" label="Address" :value="settings('company_address')" col="col-md-6" rows="3" />
    <x-admin.textarea name="company_about" label="Short description" :value="settings('company_about')" col="col-md-6" rows="3"
                      help="Appears in the website footer." />

    <div class="col-md-6">
        <label for="company_logo" class="form-label">Logo</label>
        <div class="d-flex align-items-center gap-3">
            <img src="{{ setting_file('company_logo') ?? asset('images/logo.svg') }}" alt="" id="logoPreview"
                 style="height:46px;width:auto;background:#f4f6f9;border-radius:.3rem;padding:4px">
            <input type="file" id="company_logo" name="company_logo" accept="image/*" data-preview="#logoPreview"
                   class="form-control form-control-sm @error('company_logo') is-invalid @enderror">
            @error('company_logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
        <div class="form-text">PNG, JPG or WebP, up to 1 MB.</div>
    </div>

    <div class="col-md-6">
        <label for="company_favicon" class="form-label">Favicon</label>
        <div class="d-flex align-items-center gap-3">
            <img src="{{ setting_file('company_favicon') ?? asset('images/logo.svg') }}" alt="" id="faviconPreview"
                 style="height:32px;width:32px;object-fit:contain;background:#f4f6f9;border-radius:.3rem;padding:3px">
            <input type="file" id="company_favicon" name="company_favicon" accept="image/*" data-preview="#faviconPreview"
                   class="form-control form-control-sm @error('company_favicon') is-invalid @enderror">
            @error('company_favicon') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
        <div class="form-text">PNG, JPG or WebP, up to 256 KB.</div>
    </div>
</div>
