<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@if (app()->environment('local'))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    @php
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    @endphp
    @if (isset($manifest['resources/css/app.css']))
    <link rel="stylesheet" href="{{ asset('build/' . $manifest['resources/css/app.css']['file']) }}">
    @endif
    @if (isset($manifest['resources/js/app.js']))
    <script src="{{ asset('build/' . $manifest['resources/js/app.js']['file']) }}" defer></script>
    @endif
@endif
@fluxAppearance
