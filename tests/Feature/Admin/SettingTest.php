<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_settings_page_opens_with_every_tab(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.settings.index'))
            ->assertOk();

        foreach (['Company', 'GST &amp; Legal', 'SMTP / Email', 'Order Numbering'] as $tab) {
            $response->assertSee($tab, false);
        }
    }

    public function test_company_settings_are_saved_and_read_back(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.settings.update'), [
                'group' => 'company',
                'company_name' => 'FurniCraft Interiors',
                'company_tagline' => 'Built to last',
                'company_headline' => 'Handmade furniture',
                'company_about' => 'About us copy.',
                'company_email' => 'hello@furnicraft.in',
                'company_phone' => '+91 90000 00000',
                'company_whatsapp' => '+91 90000 00000',
                'company_address' => 'New Delhi',
                'company_copyright' => '© 2026 FurniCraft',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'company']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'key' => 'company_name',
            'value' => 'FurniCraft Interiors',
        ]);

        // The cached payload must be refreshed, not stale.
        $this->assertSame('FurniCraft Interiors', app(SettingService::class)->get('company_name'));
    }

    public function test_invalid_gst_number_is_rejected(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.settings.update'), [
                'group' => 'gst',
                'gst_number' => 'not-a-gstin',
                'gst_default_rate' => 18,
            ])
            ->assertSessionHasErrors('gst_number');
    }

    public function test_gst_rate_above_twenty_eight_is_rejected(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.settings.update'), [
                'group' => 'gst',
                'gst_default_rate' => 40,
            ])
            ->assertSessionHasErrors('gst_default_rate');
    }

    public function test_logo_upload_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.settings.update'), [
                'group' => 'company',
                'company_name' => 'FurniCraft',
                'company_email' => 'info@furnicraft.in',
                'company_phone' => '+91 98765 43210',
                'company_logo' => UploadedFile::fake()->image('logo.png', 200, 80),
            ])
            ->assertSessionHas('success');

        $path = app(SettingService::class)->get('company_logo');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_saving_settings_is_recorded_in_the_activity_log(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.settings.update'), [
                'group' => 'social',
                'social_facebook' => 'https://facebook.com/furnicraft',
            ]);

        $this->assertTrue(
            Activity::where('log_name', 'settings')->where('causer_id', $this->admin->id)->exists(),
            'Settings changes should be written to the activity log.'
        );
    }

    public function test_a_role_without_edit_permission_cannot_save_settings(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager'); // Manager has no settings permissions.

        $this->actingAs($manager, 'admin')
            ->put(route('admin.settings.update'), ['group' => 'company'])
            ->assertForbidden();
    }
}
