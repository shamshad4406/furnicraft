@component('mail::message')
# One click and you are on the list

Someone — we hope you — asked for {{ $company }} updates at this address.

@component('mail::button', ['url' => $confirmUrl])
Yes, sign me up
@endcomponent

We will not send anything until you click it. If this was not you, ignore this
email and nothing happens — your address stays off the list.

Thanks,<br>
{{ $company }}
@endcomponent
