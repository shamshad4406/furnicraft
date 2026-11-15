@component('mail::message')
# Your quotation {{ $quotation->number }}

Hello {{ Str::before($quotation->customer_name, ' ') }},

Thank you for your enquiry. Here is what we can do for you.

@component('mail::table')
| Item | Qty | Amount |
|:---- |:---:| ------:|
@foreach ($quotation->items as $item)
| {{ $item->name }} | {{ $item->quantity }} | {{ money($item->line_total) }} |
@endforeach
| **Subtotal** | | **{{ money($quotation->subtotal) }}** |
@if ($quotation->discount > 0)
| Discount | | −{{ money($quotation->discount) }} |
@endif
| Delivery | | {{ $quotation->delivery_charge > 0 ? money($quotation->delivery_charge) : 'Free' }} |
| **Total** | | **{{ money($quotation->grand_total) }}** |
@endcomponent

@if ($quotation->tax_total > 0)
*All prices include {{ money($quotation->tax_total) }} GST.*
@endif

@if ($quotation->delivery_days)
We would expect to deliver about **{{ $quotation->delivery_days }} days** after the order is confirmed.
@endif

@if ($quotation->valid_until)
@component('mail::panel')
**This quotation holds until {{ $quotation->valid_until->format('D, d M Y') }}.**
@endcomponent
@endif

@component('mail::button', ['url' => $url])
View and accept online
@endcomponent

You can accept or decline on that page — no account needed. Nothing is charged when you accept;
we will confirm everything with you first.

@if ($quotation->terms)
**Terms**

{!! nl2br(e($quotation->terms)) !!}
@endif

@php
    $contactLine = 'Questions, or want something changed? Reply to this email'
        .($supportPhone ? ' or call us on '.$supportPhone : '')
        .'.';
@endphp
{{ $contactLine }}

Thanks,<br>
{{ $company }}
@endcomponent
