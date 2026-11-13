<div class="row g-3">
    <x-admin.input name="admin_login_throttle" label="Failed logins before lockout" type="number" min="1" max="20"
                   :value="settings('admin_login_throttle')" required
                   help="Counted per email address and IP address." />

    <x-admin.switcher name="maintenance_mode" label="Storefront maintenance mode"
                      :checked="settings()->bool('maintenance_mode')"
                      help="Reserved for Phase 9 — the admin panel always stays reachable.">
        Show a maintenance notice to visitors
    </x-admin.switcher>
</div>

<hr class="my-4">

<h3 class="h6 fw-bold mb-3" style="color:var(--fc-navy)">Environment</h3>
<div class="row g-3 small">
    <div class="col-md-4"><span class="text-muted d-block">Laravel</span><strong>{{ app()->version() }}</strong></div>
    <div class="col-md-4"><span class="text-muted d-block">PHP</span><strong>{{ PHP_VERSION }}</strong></div>
    <div class="col-md-4"><span class="text-muted d-block">Environment</span><strong>{{ app()->environment() }}</strong></div>
    <div class="col-md-4"><span class="text-muted d-block">Database</span><strong>{{ config('database.default') }}</strong></div>
    <div class="col-md-4"><span class="text-muted d-block">Debug mode</span>
        <strong class="{{ config('app.debug') ? 'text-danger' : 'text-success' }}">
            {{ config('app.debug') ? 'On — turn off before go-live' : 'Off' }}
        </strong>
    </div>
    <div class="col-md-4"><span class="text-muted d-block">Queue</span><strong>{{ config('queue.default') }}</strong></div>
</div>
