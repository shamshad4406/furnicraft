<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

/**
 * The eight content pages the storefront links to. Marked is_system so the
 * admin cannot delete a page the footer or checkout still points at — the
 * content itself is fully editable.
 */
class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        $count = 0;

        foreach ($this->pages() as $order => $page) {
            $existing = CmsPage::withTrashed()->where('slug', $page['slug'])->first();

            if ($existing) {
                continue;
            }

            CmsPage::create($page + [
                'is_active' => true,
                'is_system' => true,
                'sort_order' => $order,
                'meta_title' => $page['title'].' — FurniCraft',
                'meta_description' => $page['excerpt'],
            ]);

            $count++;
        }

        // is_system is guarded, so set it outside the fillable path.
        CmsPage::whereIn('slug', collect($this->pages())->pluck('slug'))->update(['is_system' => true]);

        $this->command?->info("Seeded {$count} CMS pages.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function pages(): array
    {
        return [
            [
                'title' => 'About Us',
                'slug' => 'about-us',
                'show_in_footer' => true,
                'excerpt' => 'FurniCraft builds furniture meant to be used every day and kept for years.',
                'content' => <<<'TEXT'
                ## Who we are

                FurniCraft has been building furniture for Indian homes and offices since 2012. We started as a single workshop in New Delhi and now serve customers across the country, with our own delivery fleet in the NCR and trusted transport partners elsewhere.

                ## What we make

                Everything we sell is built to be used. Solid sheesham and teak frames, powder-coated steel that does not rust through, upholstery that survives a family. We publish exact dimensions, materials and weights on every product page so there are no surprises when the delivery arrives.

                ## How we work

                - **Made to last** — every piece carries a written warranty against manufacturing defects.
                - **Made to order** — most of our range can be built to your dimensions and finish. Look for "Custom order allowed" on the product page.
                - **Installed properly** — our carpenters assemble your furniture in your home, not on the pavement outside.

                ## Visit us

                Come and sit on the sofa before you buy it. Our showroom address and opening hours are on the Contact page.
                TEXT,
            ],
            [
                'title' => 'Contact Us',
                'slug' => 'contact-us',
                'show_in_footer' => true,
                'excerpt' => 'Phone, email, showroom address and opening hours.',
                'content' => <<<'TEXT'
                ## Talk to us

                We answer the phone between **10:00 and 19:00, Monday to Saturday**. Outside those hours, email or WhatsApp us and we will reply the next working day.

                ## Showroom

                Our full range is on display, including fabrics and finishes that are hard to judge from a photograph. Parking is available at the rear.

                ## Bulk and office orders

                For offices, hotels and builders we quote separately and deliver on a schedule that suits your site. Use the enquiry form and mention the quantity and timeline.
                TEXT,
            ],
            [
                'title' => 'FAQ',
                'slug' => 'faq',
                'show_in_footer' => true,
                'excerpt' => 'Delivery, assembly, warranty, returns and custom orders.',
                'content' => <<<'TEXT'
                ## How long does delivery take?

                It depends on your pincode. Enter it on any product page and you will see the exact number of days before you order. Delhi NCR is usually 3 days; the rest of India is 6 to 10 days.

                ## Do you assemble the furniture?

                Yes. Assembly by our carpenter is included in the price everywhere we deliver with our own fleet. Elsewhere our transport partner delivers to your door and we arrange a local fitter.

                ## Can I order a different size or finish?

                Most of our range can be. Products that allow it are marked **Custom order allowed**. Send an enquiry with your dimensions and we will quote within two working days.

                ## What is covered by the warranty?

                Manufacturing defects in the frame, joinery and finish, for the period printed on the product page. Normal wear, damage from misuse and fabric staining are not covered.

                ## Can I return something?

                Yes, within 7 days of delivery if it is unused and in its original condition. See the Return Policy page for the full terms.
                TEXT,
            ],
            [
                'title' => 'Shipping Policy',
                'slug' => 'shipping-policy',
                'show_in_footer' => true,
                'excerpt' => 'Delivery timelines, charges and what happens on the day.',
                'content' => <<<'TEXT'
                ## Timelines

                Delivery time is shown per pincode on every product page. Made-to-order and custom pieces add the build time stated in the quotation.

                ## Charges

                Delivery inside Delhi NCR is free. Elsewhere the charge depends on distance and the weight of the order and is shown at checkout before you pay. Orders above the threshold for your zone ship free.

                ## On the day

                Our team calls you before leaving. Please make sure the piece will fit through your doors, lift and stairwell — the dimensions on the product page are the assembled size. If a delivery fails because access was not possible, a re-delivery charge applies.

                ## Damage in transit

                Check the furniture before signing. If anything arrives damaged, note it on the delivery sheet and tell us within 48 hours and we will repair or replace it.
                TEXT,
            ],
            [
                'title' => 'Return & Refund Policy',
                'slug' => 'return-policy',
                'show_in_footer' => true,
                'excerpt' => 'Seven-day returns on unused items, and how refunds are paid.',
                'content' => <<<'TEXT'
                ## Returns

                You may return an item within **7 days of delivery** if it is unused, undamaged and in its original packaging. Raise the request from *My Orders* or by calling us.

                ## What cannot be returned

                - Custom-made and made-to-order furniture built to your specification
                - Items damaged by misuse, or altered after delivery
                - Mattresses and other hygiene-sensitive products once unwrapped

                ## Refunds

                Approved refunds are paid to the original payment method within 7 working days of the item reaching our warehouse. Delivery charges are refunded only when the return is because of our error.

                ## Replacements

                If a piece arrives with a manufacturing defect we replace it rather than refund, unless you ask otherwise.
                TEXT,
            ],
            [
                'title' => 'Warranty',
                'slug' => 'warranty',
                'show_in_footer' => true,
                'excerpt' => 'What the warranty covers and how to claim.',
                'content' => <<<'TEXT'
                ## What is covered

                Manufacturing defects in the frame, joinery, welding and finish, for the period shown on each product page — typically 2 to 5 years depending on the range.

                ## What is not covered

                Normal wear, fading from direct sunlight, stains, damage from moving the furniture yourself, and any piece that has been repaired or modified by someone else.

                ## How to claim

                Send us your order number and photographs of the problem. We inspect, then repair on site where possible. If the piece cannot be repaired we replace it.
                TEXT,
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'show_in_footer' => true,
                'excerpt' => 'What we collect, why, and how to have it removed.',
                'content' => <<<'TEXT'
                ## What we collect

                Your name, phone number, email address and delivery address, so we can take and deliver your order. If you pay online, the payment is handled by our payment provider — card and bank details never reach our servers.

                ## Why we keep it

                To process orders, arrange delivery, issue GST invoices, and handle warranty and return requests. We keep invoice records for as long as tax law requires.

                ## Who we share it with

                Only our delivery partners and payment provider, and only what they need to do the job. We do not sell your data.

                ## Your choices

                Write to us and we will correct or delete your details, except records we are legally required to retain. You can unsubscribe from marketing emails with the link in any of them.
                TEXT,
            ],
            [
                'title' => 'Terms & Conditions',
                'slug' => 'terms-conditions',
                'show_in_footer' => true,
                'excerpt' => 'The terms you accept when ordering from FurniCraft.',
                'content' => <<<'TEXT'
                ## Orders

                An order is confirmed once payment, or the agreed advance for cash on delivery, has been received. We may decline an order where a product is mispriced or out of stock, and will refund any amount paid in full.

                ## Prices

                Prices are in Indian Rupees and include GST at the rate shown. Delivery charges are added at checkout.

                ## Product appearance

                Wood grain, colour and fabric texture vary naturally between pieces. Photographs are indicative; the written dimensions and materials are definitive.

                ## Liability

                Our liability for any order is limited to the amount you paid for it.

                ## Governing law

                These terms are governed by Indian law, and the courts at New Delhi have exclusive jurisdiction.
                TEXT,
            ],
        ];
    }
}
