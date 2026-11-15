@component('mail::message')
# Thank you for your order

Hello {{ Str::before($order->customer_name, ' ') }},

We have received your order **{{ $order->order_number }}** and it is confirmed.

@component('mail::table')
| Item | Qty | Amount |
|:---- |:---:| ------:|
@foreach ($order->items as $item)
| {{ $item->name }}{{ $item->variant_label ? ' ('.$item->variant_label.')' : '' }} | {{ $item->quantity }} | {{ money($item->line_total) }} |
@endforeach
| **Subtotal** | | **{{ money($order->subtotal) }}** |
@if ($order->discount > 0)
| Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }} | | −{{ money($order->discount) }} |
@endif
| Delivery | | {{ $order->delivery_charge > 0 ? money($order->delivery_charge) : 'Free' }} |
| **Total** | | **{{ money($order->grand_total) }}** |
@endcomponent

@if ($order->tax_total > 0)
*Includes {{ money($order->tax_total) }} GST.*
@endif

@if ($order->amount_due > 0)
@component('mail::panel')
**{{ money($order->amount_due) }} is payable on delivery.** Please keep the amount ready for our delivery team.
@endcomponent
@else
Payment of **{{ money($order->amount_paid) }}** has been received in full.
@endif

## Delivering to

{{ $order->shipping_address['name'] }}
{{ collect([
    $order->shipping_address['line1'] ?? null,
    $order->shipping_address['line2'] ?? null,
    $order->shipping_address['landmark'] ?? null,
])->filter()->implode(', ') }}
{{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }} — {{ $order->shipping_address['pincode'] }}
Phone: {{ $order->shipping_address['phone'] }}

@if ($order->delivery_days)
We expect to deliver by **{{ ($order->confirmed_at ?? now())->addDays($order->delivery_days)->format('D, d M Y') }}**.
Our team will call you to confirm a delivery slot.
@else
Our team will call you shortly to confirm the delivery date.
@endif

{{-- A guest has no account to come back to, so their link is the tracking
     page, which the token alone opens. Keep this email: it is how they
     check on the order later. --}}
@component('mail::button', ['url' => $order->customer_id
    ? route('account.order', $order->public_token)
    : route('track.status', $order->public_token)])
{{ $order->customer_id ? 'View your order' : 'Track your order' }}
@endcomponent

@unless ($order->customer_id)
You can also check on it any time at [{{ route('track.form') }}]({{ route('track.form') }})
using order number **{{ $order->order_number }}** and this email address.
@endunless

{{-- Built in PHP because this sentence has to run straight into a
     condition: Blade never compiles a directive glued to a word
     character, so the opening tag would be left in the email. --}}
@php
    $contactLine = 'Any questions? Reply to this email'
        .($supportPhone ? ' or call us on '.$supportPhone : '')
        .'.';
@endphp
{{ $contactLine }}

Thanks,<br>
{{ $company }}
@endcomponent
