{{-- Suggestions while typing a room, course code or block (the forms' list="…"), and the course title
     filled in for a known course code. Needs $roomNames, $codes, $titles, $blockNames. --}}
<datalist id="tt-room-list">
  @foreach ($roomNames as $name)
    <option value="{{ $name }}"></option>
  @endforeach
</datalist>
<datalist id="tt-code-list">
  @foreach ($codes as $code)
    <option value="{{ $code }}">{{ $titles[mb_strtoupper($code)] ?? '' }}</option>
  @endforeach
</datalist>
<datalist id="tt-block-list">
  @foreach ($blockNames as $name)
    <option value="{{ $name }}"></option>
  @endforeach
</datalist>
<script>
  // A known course code fills in its title (when the title is still empty)
  (function () {
    var titles = @json($titles);
    document.querySelectorAll('.tt-form').forEach(function (form) {
      var code = form.querySelector('[name=code]');
      var title = form.querySelector('[name=title]');
      if (!code || !title) return;
      code.addEventListener('change', function () {
        var key = code.value.trim().toUpperCase().replace(/\s+/g, ' ').replace(/^([A-Z]+)(\d)/, '$1 $2');
        if (titles[key] && !title.value.trim()) title.value = titles[key];
      });
    });
  })();
</script>
