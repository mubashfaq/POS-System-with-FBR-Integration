@props([
    'id', // required — modal DOM id
    'title' => '', // header title text
    'icon' => 'fas fa-info-circle', // header icon class
    'size' => 'lg', // sm | md | lg | xl
    'scrollable' => true, // adds modal-dialog-scrollable
    'closeLabel' => 'Close',
])

@php
    $sizeClass = 'modal-' . $size;
    $scrollClass = $scrollable ? 'modal-dialog-scrollable' : '';
@endphp

<div class="modal fade app-modal" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label"
    aria-hidden="true">
    <div class="modal-dialog {{ $sizeClass }} {{ $scrollClass }}">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Label">
                    @if ($icon)
                        <i class="{{ $icon }}"></i>
                    @endif
                    {{ $title }}
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="modal-footer">
                    {{ $footer }}
                </div>
            @else
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> {{ $closeLabel }}
                    </button>
                </div>
            @endisset

        </div>
    </div>
</div>
