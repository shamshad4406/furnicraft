<?php

namespace App\Services;

/**
 * Static storefront content that is not catalog data.
 *
 * Categories and products now come from the database (Phase 2). What is left
 * here is the hero banner and the trust strip, which become admin-managed in
 * Phase 8 (banner module).
 */
class ShowcaseCatalog
{
    /**
     * @return array<int, array<string, string>>
     */
    public function slides(): array
    {
        return [
            [
                'title' => 'Modern Furniture',
                'highlight' => 'for a Better Living',
                'subtitle' => 'Stylish • Durable • Affordable',
                'cta' => 'Explore Collection',
                'image' => 'images/hero/hero-1.svg',
            ],
            [
                'title' => 'Handcrafted Comfort',
                'highlight' => 'for Every Room',
                'subtitle' => 'Solid Wood • Premium Fabric • Lifetime Finish',
                'cta' => 'Browse Living Room',
                'image' => 'images/hero/hero-2.svg',
            ],
            [
                'title' => 'Office Furniture',
                'highlight' => 'Built to Work Harder',
                'subtitle' => 'Ergonomic • Modular • Warranty Backed',
                'cta' => 'See Office Range',
                'image' => 'images/hero/hero-3.svg',
            ],
        ];
    }

    /**
     * Placeholder testimonials. Replaced by approved customer reviews when the
     * review module lands in Phase 8.
     *
     * @return array<int, array<string, string>>
     */
    public function testimonials(): array
    {
        return [
            [
                'quote' => 'The sofa arrived on the day they promised and the carpenter assembled it in under an hour. Three years on it still looks new.',
                'name' => 'Ananya Mehta',
                'place' => 'New Delhi',
            ],
            [
                'quote' => 'We fitted out our whole office — 18 workstations and chairs. They matched our drawings exactly and delivered in two batches so we could keep working.',
                'name' => 'Rohit Bansal',
                'place' => 'Gurugram',
            ],
            [
                'quote' => 'I asked for the dining table 20 cm shorter than the standard size. No fuss, no extra delay, and the finish is beautiful.',
                'name' => 'Sunita Rao',
                'place' => 'Noida',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function usps(): array
    {
        return [
            ['icon' => 'bi-shield-check', 'title' => 'Premium Quality', 'subtitle' => 'Guaranteed'],
            ['icon' => 'bi-headset', 'title' => 'Dedicated Support', 'subtitle' => '24/7'],
            ['icon' => 'bi-truck', 'title' => 'Best Price', 'subtitle' => 'Always'],
            ['icon' => 'bi-hand-thumbs-up', 'title' => 'Trusted by Thousands', 'subtitle' => ''],
        ];
    }
}
