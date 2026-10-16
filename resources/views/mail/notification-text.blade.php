{{ $subjectLine }}

{{ $bodyText }}
@foreach ($items as $item)

- {{ $item['title'] }}@if ($item['body']) — {{ $item['body'] }}@endif @if ($item['url']) ({{ $item['url'] }})@endif
@endforeach
@if ($buttons !== [])

@foreach ($buttons as $button)
{{ $button['label'] }}: {{ $button['url'] }}
@endforeach
@endif
@if ($url)

{{ __('Open in Mawred') }}: {{ $url }}
@endif
