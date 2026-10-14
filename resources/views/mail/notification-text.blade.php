{{ $subjectLine }}

{{ $bodyText }}
@foreach ($items as $item)

- {{ $item['title'] }}@if ($item['body']) — {{ $item['body'] }}@endif @if ($item['url']) ({{ $item['url'] }})@endif
@endforeach
@if (isset($actions['approved']))

{{ __('Approve') }}: {{ $actions['approved'] }}
@endif
@if (isset($actions['rejected']))
{{ __('Reject') }}: {{ $actions['rejected'] }}
@endif
@if ($url)

{{ __('Open in Mawred') }}: {{ $url }}
@endif
