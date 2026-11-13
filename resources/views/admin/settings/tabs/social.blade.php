<p class="text-muted small">Leave a field empty to hide that icon in the website footer.</p>

<div class="row g-3">
    <x-admin.input name="social_facebook" label="Facebook URL" type="url" :value="settings('social_facebook')" />
    <x-admin.input name="social_youtube" label="YouTube URL" type="url" :value="settings('social_youtube')" />
    <x-admin.input name="social_instagram" label="Instagram URL" type="url" :value="settings('social_instagram')" />
    <x-admin.input name="social_linkedin" label="LinkedIn URL" type="url" :value="settings('social_linkedin')" />
</div>
