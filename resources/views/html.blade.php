<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sitemap</title>
</head>
<body>
    <h1>Sitemap</h1>

    @forelse ($groups as $group => $urls)
        <section>
            <h2>{{ ucfirst($group) }}</h2>
            <ul>
                @foreach ($urls as $url)
                    <li><a href="{{ $url->url }}">{{ $url->url }}</a></li>
                @endforeach
            </ul>
        </section>
    @empty
        <p>No pages to list.</p>
    @endforelse
</body>
</html>
