@component('mail::message')
# {{ $headline }}

Hello {{ Str::before($order->customer_name, ' ') }},

@switch ($status)
@case ('confirmed')
Your order **{{ $order->order_number }}** is confirmed and we have started getting it ready.
@break

@case ('processing')
We are preparing order **{{ $order->order_number }}** for dispatch. We will write again the moment it leaves us.
@break

@case ('shipped')
Order **{{ $order->order_number }}** has left our warehouse.
@break

@case ('delivered')
@php
    // Built in PHP because the date runs straight on from a word: Blade
    // never compiles a directive glued to a word character, so the opening
    // tag would be left sitting in the email.
    $deliveredLine = 'Order **'.$order->order_number.'** was delivered'
        .($order->delivered_at ? ' on '.$order->delivered_at->format('D, d M Y') : '')
        .'. We hope you like it.';
@endphp
{!! $deliveredLine !!}
@break

@case ('cancelled')
Order **{{ $order->order_number }}** has been cancelled.
@break

@default
There is an update on your order **{{ $order->order_number }}**.
@endswitch

@if ($note)
@component('mail::panel')
{{ $note }}
@endcomponent
@endif

@if ($status === 'shipped' && $shipment)
@component('mail::table')
| | |
|:--- |:--- |
| Consignment | {{ $shipment->consignment_number }} |
@if ($shipment->courier)
| Courier | {{ $shipment->courier }} |
@endif
@if ($shipment->tracking_number)
| Tracking number | {{ $shipment->tracking_number }} |
@endif
@if ($shipment->expected_delivery_on)
| Expected | {{ $shipment->expected_delivery_on->format('D, d M Y') }} |
@endif
@endcomponent

@if ($shipment->tracking_url)
@component('mail::button', ['url' => $shipment->tracking_url])
Track with the courier
@endcomponent
@endif
@endif

@if ($status === 'cancelled' && $order->amount_paid > 0)
{{ money($order->amount_paid) }} will be refunded to the way you paid. Refunds usually reach the account within 5 to 7 working days.
@endif

@if ($status === 'delivered' && $order->isReturnable())
@php
    $returnBy = $order->returnWindowEndsOn();
@endphp
Something not right? You can raise a return until **{{ $returnBy->format('D, d M Y') }}**.
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
