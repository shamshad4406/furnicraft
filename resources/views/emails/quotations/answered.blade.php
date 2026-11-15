@component('mail::message')
# Quotation {{ $answer }}

**{{ $quotation->customer_name }}** has {{ $answer }} quotation **{{ $quotation->number }}**
for {{ money($quotation->grand_total) }}.

@component('mail::table')
| | |
|:--- |:--- |
| Customer | {{ $quotation->customer_name }} |
| Email | {{ $quotation->customer_email }} |
| Phone | {{ $quotation->customer_phone }} |
| Total | {{ money($quotation->grand_total) }} |
@endcomponent

@if ($answer === 'declined' && $quotation->decline_reason)
**Reason given:** {{ $quotation->decline_reason }}
@endif

@if ($answer === 'accepted' && $quotation->customer_note)
**They added:** {{ $quotation->customer_note }}
@endif

@if ($answer === 'accepted')
Turn it into an order when you are ready.
@endif

@component('mail::button', ['url' => $adminUrl])
Open the quotation
@endcomponent
@endcomponent
