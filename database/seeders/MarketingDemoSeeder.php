<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Customer;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo marketing data, so the Phase 8 screens have something in them.
 *
 * Separate from ShopDemoSeeder because this is the part a shop owner will
 * delete first: a banner for a sale that is over, reviews written by nobody.
 * Keeping it apart means `db:seed --class=MarketingDemoSeeder` is the only
 * thing that puts it there, and nothing else depends on it.
 */
class MarketingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->banners();
        $this->reviews();
        $this->subscribers();
    }

    /**
     * Three banners: one running, one finished, one waiting its turn — so the
     * states on the list screen are all visible at once.
     */
    protected function banners(): void
    {
        $slides = [
            ['hero-1.svg', 'Modern Furniture', 'for a Better Living', 'Stylish • Durable • Affordable', 'Explore Collection', '/products'],
            ['hero-2.svg', 'Handcrafted Comfort', 'for Every Room', 'Solid Wood • Premium Fabric • Lifetime Finish', 'Browse Living Room', '/products?sort=newest'],
            ['hero-3.svg', 'Office Furniture', 'Built to Work Harder', 'Ergonomic • Modular • Warranty Backed', 'See Office Range', '/products'],
        ];

        $windows = [
            [null, null],
            [now()->subMonth(), now()->subWeek()],
            [now()->addWeek(), now()->addMonth()],
        ];

        foreach ($slides as $i => [$file, $title, $highlight, $subtitle, $cta, $url]) {
            $source = public_path('images/hero/'.$file);
            $path = 'banners/demo-'.pathinfo($file, PATHINFO_FILENAME).'.svg';

            if (is_file($source) && ! Storage::disk('public')->exists($path)) {
                Storage::disk('public')->put($path, (string) file_get_contents($source));
            }

            Banner::firstOrCreate(
                ['title' => $title, 'placement' => 'hero'],
                [
                    'highlight' => $highlight,
                    'subtitle' => $subtitle,
                    'cta_label' => $cta,
                    'cta_url' => $url,
                    'image_path' => Storage::disk('public')->exists($path) ? $path : null,
                    'image_alt' => $title.' '.$highlight,
                    'starts_at' => $windows[$i][0],
                    'ends_at' => $windows[$i][1],
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
        }

        $this->command?->info('Banners: '.Banner::count().' ('.Banner::live()->count().' on screen now).');
    }

    /**
     * Reviews on whatever products exist, in every state a moderator meets:
     * published, published and featured, and one still waiting.
     */
    protected function reviews(): void
    {
        $products = Product::where('status', 'active')->orderBy('id')->limit(3)->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No products to review — run the catalogue seeder first.');

            return;
        }

        $reviews = app(ReviewService::class);
        $moderator = User::query()->orderBy('id')->first();

        $written = [
            ['Asha Verma', 'asha.verma@example.com', 'Pune', 5, 'Worth every rupee',
                'Delivered in four days and assembled by the delivery team. The finish is exactly as it looked on the site.', true, true],
            ['Ravi Kumar', 'ravi.kumar@example.com', 'New Delhi', 4, 'Solid, slightly firm',
                'Very well made and the wood is clearly solid. The cushions are firmer than I expected, which suits me but may not suit everybody.', true, false],
            ['Mohan Lal', 'mohan.lal@example.com', 'Jaipur', 5, null,
                'Second piece I have bought from here. Same quality as the first one, which is the whole reason I came back.', false, false],
        ];

        foreach ($written as $i => [$name, $email, $city, $rating, $title, $body, $publish, $feature]) {
            $product = $products[$i % $products->count()];

            if (Review::where('product_id', $product->id)->where('email', $email)->exists()) {
                continue;
            }

            $review = $reviews->submit($product, [
                'name' => $name,
                'email' => $email,
                'city' => $city,
                'rating' => $rating,
                'title' => $title,
                'body' => $body,
                'customer_id' => Customer::where('email', $email)->value('id'),
            ]);

            if ($publish) {
                $review = $reviews->approve($review, $moderator);

                if ($feature) {
                    $reviews->feature($review);
                    $reviews->reply($review,
                        'Thank you, Asha — glad the team got it assembled for you.', $moderator);
                }
            }
        }

        $this->command?->info('Reviews: '.Review::count().' ('.Review::pending()->count().' waiting to be read).');
    }

    protected function subscribers(): void
    {
        $rows = [
            ['asha.verma@example.com', 'Asha Verma', 'subscribed', 'footer'],
            ['ravi.kumar@example.com', 'Ravi Kumar', 'subscribed', 'checkout'],
            ['sunil.rao@example.com', 'Sunil Rao', 'pending', 'footer'],
            ['left@example.com', 'Deepa Nair', 'unsubscribed', 'footer'],
        ];

        foreach ($rows as [$email, $name, $status, $source]) {
            NewsletterSubscriber::firstOrCreate(['email' => $email], [
                'name' => $name,
                'status' => $status,
                'source' => $source,
                'customer_id' => Customer::where('email', $email)->value('id'),
                'confirm_token' => $status === 'pending' ? Str::lower(Str::random(40)) : null,
                'confirmed_at' => $status === 'subscribed' ? now()->subDays(10) : null,
                'unsubscribed_at' => $status === 'unsubscribed' ? now()->subDays(2) : null,
                'unsubscribe_reason' => $status === 'unsubscribed' ? 'Too many emails' : null,
            ]);
        }

        $this->command?->info('Newsletter: '.NewsletterSubscriber::mailable()->count().' mailable of '
            .NewsletterSubscriber::count().'.');
    }
}
