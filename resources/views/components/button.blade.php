@props(['href', 'variant' => 'primary', 'arrow' => false])
<a {{ $attributes->class('button button-'.$variant) }} href="{{ $href }}">{{ $slot }}@if ($arrow)<x-icon.forward class="button-arrow" />@endif</a>
