{{ $company }}

{{ $subjectLine }}

{{ $bodyText }}

@foreach ($buttons as $button)
{{ $button['label'] }}: {{ $button['url'] }}
@endforeach

{{ __('You applied for a job with :company.', ['company' => $company]) }} {{ __('Delete my details') }}: {{ $privacyUrl }}
