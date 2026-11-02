{{-- Phase 9: a person's photo, or their initials in a circle. <x-avatar :user="$user" size="md" />
     Sizes: sm 28px (default), md 44px, lg 112px. Only show it where the viewer may see the photo
     (their own, or staff looking at their department's people): PhotoController checks it too. --}}
@props(['user', 'size' => 'sm'])
@if ($user->photo_path)
  <img {{ $attributes->class(['avatar', 'avatar-'.$size]) }} src="{{ $user->photoUrl() }}" alt="" loading="lazy">
@else
  <span {{ $attributes->class(['avatar', 'avatar-'.$size, 'avatar-none']) }} aria-hidden="true">{{ $user->initials() }}</span>
@endif
