@component('mail::message')
# Thank you for getting in touch

Hello {{ Str::before($enquiry->name, ' ') }},

We have your enquiry and someone from our team will come back to you shortly.

@component('mail::table')
| | |
|:--- |:--- |
| Reference | **{{ $enquiry->reference }}** |
| About | {{ $enquiry->type_label }} |
@if ($enquiry->product)
| Product | {{ $enquiry->product->name }} |
@endif
@if ($enquiry->quantity)
| Quantity | {{ $enquiry->quantity }} |
@endif
@if ($enquiry->required_by)
| Needed by | {{ $enquiry->required_by->format('D, d M Y') }} |
@endif
@endcomponent

**What you told us**

> {{ $enquiry->message }}

@php
    // Built in PHP because the phone number runs straight on from a word:
    // Blade never compiles a directive glued to a word character.
    $contactLine = 'Quote '.$enquiry->reference.' if you call or write back'
        .($supportPhone ? ' — our number is '.$supportPhone : '')
        .'.';
@endphp
{{ $contactLine }}

Thanks,<br>
{{ $company }}
@endcomponent
