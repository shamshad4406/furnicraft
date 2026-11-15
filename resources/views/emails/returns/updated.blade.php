@component('mail::message')
# {{ $return->status === 'rejected' ? 'About your return' : 'Your return '.\App\Models\OrderReturn::STATUSES[$return->status] }}

Hello {{ Str::before($order->customer_name, ' ') }},

@switch ($return->status)
@case ('approved')
We have approved **{{ $return->reference }}** against order {{ $order->order_number }}. Our team will be in touch to arrange collection.
@break

@case ('rejected')
We are not able to accept **{{ $return->reference }}** against order {{ $order->order_number }}.
@break

@case ('received')
We have received the items from **{{ $return->reference }}** and are checking them now.
@break

@case ('refunded')
Your refund for **{{ $return->reference }}** has been sent.
@break

@case ('replaced')
A replacement for **{{ $return->reference }}** is on its way to you.
@break

@default
There is an update on **{{ $return->reference }}**, raised against order {{ $order->order_number }}.
@endswitch

@if ($return->customer_message)
@component('mail::panel')
{{ $return->customer_message }}
@endcomponent
@endif

@component('mail::table')
| Item | Qty |
|:---- |:---:|
@foreach ($return->items as $line)
| {{ $line->orderItem?->name ?? 'Item' }} | {{ $line->quantity }} |
@endforeach
@endcomponent

@if ($return->status === 'refunded' && $return->refund_amount > 0)
@php
    $refundLine = 'Amount refunded: '.money($return->refund_amount)
        .match ($return->refund_method) {
            'original' => ', back to the way you paid. It usually reaches the account within 5 to 7 working days.',
            'bank' => ', by bank transfer.',
            'store_credit' => ', as store credit.',
            default => '.',
        };
@endphp
**{{ $refundLine }}**

@if ($return->refund_reference)
Reference: {{ $return->refund_reference }}
@endif
@endif

@component('mail::button', ['url' => $trackUrl])
View your order
@endcomponent

@php
    $contactLine = 'Any questions? Reply to this email'
        .($supportPhone ? ' or call us on '.$supportPhone : '')
        .'.';
@endphp
{{ $contactLine }}

Thanks,<br>
{{ $company }}
@endcomponent
