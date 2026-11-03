@extends('layouts.app')
@section('title', 'Component examples — sample data')
@section('content')
<div class="rb-ui rb-stack" id="examples">
  <x-ui.page-header title="Component examples" description="Sample data only. This isolated fixture is not a production screen." />
  <x-ui.alert tone="info">Foundation examples use provisional documented values, not verified Figma measurements.</x-ui.alert>
  <x-ui.card>
    <x-slot:heading><h2 class="rb-heading rb-heading--h2">Buttons and native submission</h2></x-slot:heading>
    <div class="rb-actions">
      <x-ui.button id="default-button" data-example="forwarded">Default button</x-ui.button>
      <x-ui.button variant="secondary">Secondary</x-ui.button>
      <x-ui.button variant="quiet" size="compact">Quiet</x-ui.button>
      <x-ui.button variant="danger">Danger</x-ui.button>
      <x-ui.button id="disabled-button" disabled>Unavailable example</x-ui.button>
      <x-ui.link-button id="example-link" href="#examples" rel="help" variant="secondary">Back to examples</x-ui.link-button>
    </div>
  </x-ui.card>
  <x-ui.card>
    <x-slot:heading><h2 class="rb-heading rb-heading--h2">Fields and named validation</h2></x-slot:heading>
    <x-ui.error-summary id="summary" :bag="$errors->getBag('example')" />
    <x-ui.error-summary id="empty-summary" :bag="$errors->getBag('absent')" />
    <p id="external-help" class="rb-description">Example field descriptions also preserve caller-provided references.</p>
    <form id="sample-form" class="rb-stack" method="post" action="#sample-result" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <x-ui.field field-id="example-email" label="Email" hint="Use an example address." :error="$errors->getBag('example')->first('email')">
        <x-ui.input name="contact[email]" type="email" value="example@example.test" required aria-describedby="external-help" />
      </x-ui.field>
      <x-ui.field field-id="example-select" label="Option">
        <x-ui.select name="option"><option value="one">One</option><option value="two" selected>Two</option></x-ui.select>
      </x-ui.field>
      <x-ui.field field-id="example-notes" label="Notes" hint="Text remains escaped.">
        <x-ui.textarea name="notes" rows="3">{{ '<example & notes>' }}</x-ui.textarea>
      </x-ui.field>
      <x-ui.field field-id="example-password" label="Password"><x-ui.input type="password" name="password" autocomplete="new-password" /></x-ui.field>
      <x-ui.field field-id="example-file" label="File"><x-ui.input type="file" name="photo" accept="image/png" /></x-ui.field>
      <x-ui.field field-id="example-readonly" label="Read-only example"><x-ui.input name="readonly" readonly value="Retained value" /></x-ui.field>
      <x-ui.field field-id="example-disabled" label="Disabled example"><x-ui.input name="disabled" disabled value="Not submitted" /></x-ui.field>
      <label for="override-id" class="rb-field__label">Explicit standalone ID</label>
      <x-ui.input id="override-id" name="explicit" value="&quot;quoted&quot; &amp; escaped" />
    </form>
    <x-slot:actions><x-ui.button id="save-example" type="submit" form="sample-form" formaction="#sample-save" name="intent" value="save">Save example</x-ui.button></x-slot:actions>
  </x-ui.card>
  <div class="rb-grid">
    <x-ui.stat-card label="Example count — sample data" value="12" />
    <x-ui.card><x-slot:heading><h2 class="rb-heading rb-heading--h2">Existing avatar</h2></x-slot:heading><x-avatar :user="$user" size="md" /> <span>{{ $user->name }}</span></x-ui.card>
  </div>
  <div class="rb-actions">
    <x-ui.badge label="Waiting for approval" tone="warning" />
    <x-ui.badge label="Approved" tone="success" />
    <x-ui.badge label="Rejected" tone="danger" />
    <x-ui.badge :label="'<script>sample</script>'" />
  </div>
  <x-ui.alert tone="success" role="status">Example success message.</x-ui.alert>
  <x-ui.alert tone="warning">Example warning with a visible text explanation.</x-ui.alert>
  <x-ui.alert tone="danger">Example problem. Static examples do not all announce themselves as alerts.</x-ui.alert>
  <x-ui.empty-state title="No example items" description="This is an intentionally empty fixture."><x-slot:actions><x-ui.link-button href="#examples" variant="secondary">Review examples</x-ui.link-button></x-slot:actions></x-ui.empty-state>
  <x-ui.table-container id="example-table" label="Example reservations">
    <table><caption>Sample data — not live bookings</caption><thead><tr><th scope="col">Item</th><th scope="col">Description</th><th scope="col">State</th></tr></thead><tbody><tr><th scope="row">Example item</th><td>A long description that can wrap naturally at narrow widths.</td><td><x-ui.badge label="Example only" /></td></tr></tbody></table>
  </x-ui.table-container>
</div>
@endsection
