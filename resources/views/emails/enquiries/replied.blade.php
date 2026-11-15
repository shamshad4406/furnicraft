@component('mail::message')
Hello {{ Str::before($enquiry->name, ' ') }},

{{-- The staff member's own words, kept as they typed them. --}}
{!! nl2br(e($body)) !!}

---

@php
    $contextLine = 'This is about your enquiry '.$enquiry->reference
        .($enquiry->subject ? ' — '.$enquiry->subject : '')
        .'. Just reply to this email to carry on the conversation'
        .($supportPhone ? ', or call us on '.$supportPhone : '')
        .'.';
@endphp
<small>{{ $contextLine }}</small>

Thanks,<br>
{{ $company }}
@endcomponent
