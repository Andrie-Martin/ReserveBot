<x-mail::message>
# {{ $heading }}

Hi {{ $rep }},

{{ $intro }}

@foreach ($buttons as $button)
<x-mail::button :url="$button['url']" :color="$button['color']">
{{ $button['label'] }}
</x-mail::button>
@endforeach

{{ $outro }}

Thanks,<br>
ReserveBot
</x-mail::message>
