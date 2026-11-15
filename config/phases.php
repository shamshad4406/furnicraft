<?php

/**
 * The delivery plan, in one place.
 *
 * The admin dashboard draws its roadmap from this list and the admin footer
 * names the current phase from it, so moving the project forward is a single
 * edit to "current" below rather than a hunt through views.
 */
return [

    // The phase being worked on now. Everything before it is treated as done.
    'current' => 8,

    'list' => [
        1 => ['name' => 'Project Setup & Foundation', 'weeks' => '1'],
        2 => ['name' => 'Catalog Management', 'weeks' => '2'],
        3 => ['name' => 'Customer Storefront', 'weeks' => '3'],
        4 => ['name' => 'Cart, Checkout & Payments', 'weeks' => '3'],
        5 => ['name' => 'Orders & Delivery', 'weeks' => '2'],
        6 => ['name' => 'Enquiry & Quotation', 'weeks' => '1.5'],
        7 => ['name' => 'Inventory & Reports', 'weeks' => '2'],
        8 => ['name' => 'Marketing & SEO', 'weeks' => '1'],
        9 => ['name' => 'Testing & Launch', 'weeks' => '1.5'],
    ],

];
