@extends('layouts.app')
@section('title', 'Conditional form examples — sample data')
@section('content')
<h1>Existing conditional forms — sample data</h1>
<p>Actual existing partials are rendered below. No production actions should be submitted.</p>
<form class="mform acct-form" id="account-fixture" method="post" action="#example">
  @include('accounts._fields', ['prefix' => 'fixture', 'account' => null, 'blocks' => collect(), 'sections' => collect()])
</form>
<form class="mform tt-form" id="timetable-fixture" method="post" action="#example">
  @include('manage._timetable_row_form', ['prefix' => 'fixture-tt', 'row' => null, 'defaultDay' => 'Mon', 'bag' => $errors->getBag('row')])
</form>
@include('manage._timetable_datalists', ['roomNames' => ['Example room'], 'codes' => ['EX 101'], 'titles' => ['EX 101' => 'Example course'], 'blockNames' => []])
@endsection
