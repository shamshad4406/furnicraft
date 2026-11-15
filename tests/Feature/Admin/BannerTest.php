<?php

namespace Tests\Feature\Admin;

use App\Models\Banner;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Banners, which are the one bit of the shop's front page the owner changes
 * most often and a developer should never have to touch.
 *
 * Two things are worth guarding: the schedule (a sale banner has to stop on
 * its own) and the link (a banner that can point anywhere is a phishing link
 * wearing the shop's name).
 */
class BannerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function banner(array $overrides = []): Banner
    {
        return Banner::create(array_merge([
            'placement' => 'hero',
            'title' => 'Festive Sale',
            'image_path' => 'banners/sale.jpg',
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    /* ----------------------------------------------------- scheduling */

    public function test_a_banner_outside_its_window_is_not_live(): void
    {
        $running = $this->banner(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $finished = $this->banner(['title' => 'Diwali', 'starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()]);
        $upcoming = $this->banner(['title' => 'Republic Day', 'starts_at' => now()->addWeek()]);
        $off = $this->banner(['title' => 'Old', 'is_active' => false]);

        $live = Banner::live()->pluck('id');

        $this->assertTrue($live->contains($running->id));
        $this->assertFalse($live->contains($finished->id));
        $this->assertFalse($live->contains($upcoming->id));
        $this->assertFalse($live->contains($off->id));

        // And the reason is said in words somebody can act on.
        $this->assertSame('Live', $running->state_label);
        $this->assertSame('Finished', $finished->state_label);
        $this->assertSame('Scheduled', $upcoming->state_label);
        $this->assertSame('Off', $off->state_label);
    }

    public function test_a_finished_banner_leaves_the_homepage_without_anybody_switching_it_off(): void
    {
        $banner = $this->banner(['ends_at' => now()->addHour()]);

        $this->assertTrue(Banner::live('hero')->pluck('id')->contains($banner->id));

        $this->travel(2)->hours();

        $this->assertFalse(Banner::live('hero')->pluck('id')->contains($banner->id));
        // Still switched on — it simply stopped showing.
        $this->assertTrue($banner->fresh()->is_active);
    }

    /* --------------------------------------------------------- the link */

    public function test_the_button_link_has_to_stay_on_this_site(): void
    {
        foreach (['https://example.com/offer', '//example.com', 'javascript:alert(1)', '\\\\example.com'] as $bad) {
            $this->actingAs($this->admin, 'admin')
                ->post(route('admin.banners.store'), [
                    'placement' => 'hero',
                    'title' => 'Sale',
                    'cta_url' => $bad,
                    'image' => UploadedFile::fake()->image('sale.jpg', 1600, 600),
                ])
                ->assertSessionHasErrors('cta_url');
        }

        $this->assertSame(0, Banner::count());
    }

    public function test_a_path_on_this_site_is_accepted(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.banners.store'), [
                'placement' => 'hero',
                'title' => 'Festive Sale',
                'highlight' => 'up to 40% off',
                'cta_label' => 'Shop the sale',
                'cta_url' => '/products?sort=newest',
                'is_active' => '1',
                'image' => UploadedFile::fake()->image('sale.jpg', 1600, 600),
            ])
            ->assertRedirect(route('admin.banners.index'));

        $banner = Banner::sole();

        $this->assertSame('/products?sort=newest', $banner->cta_url);
        $this->assertNotNull($banner->image_path);
        Storage::disk('public')->assertExists($banner->image_path);
    }

    public function test_the_end_date_cannot_come_before_the_start(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.banners.store'), [
                'placement' => 'hero',
                'title' => 'Sale',
                'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
                'ends_at' => now()->format('Y-m-d\TH:i'),
                'image' => UploadedFile::fake()->image('sale.jpg'),
            ])
            ->assertSessionHasErrors('ends_at');
    }

    /* ----------------------------------------------------------- order */

    public function test_moving_a_banner_swaps_it_with_its_neighbour(): void
    {
        $first = $this->banner(['title' => 'First', 'sort_order' => 0]);
        $second = $this->banner(['title' => 'Second', 'sort_order' => 1]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.banners.reorder', $second), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(0, $second->fresh()->sort_order);
    }

    /**
     * Two banners on the same number would leave the slider in whatever
     * order the database felt like.
     */
    public function test_equal_positions_are_nudged_apart(): void
    {
        $first = $this->banner(['title' => 'First', 'sort_order' => 2]);
        $second = $this->banner(['title' => 'Second', 'sort_order' => 2]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.banners.reorder', $second), ['direction' => 'up']);

        $this->assertNotSame($first->fresh()->sort_order, $second->fresh()->sort_order);
    }

    public function test_the_last_banner_cannot_move_further_down(): void
    {
        $only = $this->banner();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.banners.reorder', $only), ['direction' => 'down'])
            ->assertSessionHas('error');
    }

    /* ---------------------------------------------------- the shopfront */

    public function test_a_click_is_counted_and_sent_to_the_banners_own_link(): void
    {
        $banner = $this->banner(['cta_url' => '/products']);

        $this->get(route('banner.click', $banner))
            ->assertRedirect('/products');

        $this->assertSame(1, (int) $banner->fresh()->click_count);
    }

    /**
     * The counter takes the destination from the stored banner, never from
     * the request — otherwise the shop's own address becomes a redirector.
     */
    public function test_the_click_counter_will_not_redirect_off_site(): void
    {
        $banner = $this->banner(['cta_url' => '/products']);

        $this->get(route('banner.click', $banner).'?to=https://example.com')
            ->assertRedirect('/products');
    }

    public function test_switching_a_banner_off_takes_it_off_the_homepage(): void
    {
        $banner = $this->banner();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.banners.toggle', $banner))
            ->assertRedirect();

        $this->assertFalse($banner->fresh()->is_active);
        $this->assertSame(0, Banner::live('hero')->count());
    }

    /* ------------------------------------------------------ permissions */

    public function test_a_delivery_user_cannot_touch_the_banners(): void
    {
        $driver = User::factory()->create();
        $driver->assignRole('Delivery');

        $this->actingAs($driver, 'admin')
            ->get(route('admin.banners.index'))
            ->assertForbidden();
    }
}
