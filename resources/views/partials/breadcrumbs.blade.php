{{--
    Breadcrumb trail for pages two or more levels deep.
    Usage: @include('partials.breadcrumbs', ['trail' => ['Events' => route('manage.events.index'), $event->title => null]])
    The last entry (url null) is the current page.
--}}
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
        @foreach ($trail as $label => $url)
            @if ($url)
                <li><a href="{{ $url }}">{{ $label }}</a></li>
            @else
                <li><span aria-current="page">{{ \Illuminate\Support\Str::limit($label, 48) }}</span></li>
            @endif
        @endforeach
    </ol>
</nav>
