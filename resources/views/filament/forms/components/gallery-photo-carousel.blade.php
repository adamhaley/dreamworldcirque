@php
    $photos = $getPhotos();
    $stagedUploads = $getStagedUploads();
    $isDisabled = $isDisabled();
@endphp

@once
    <style>
        .gpc-viewport { position: relative; }
        .gpc-track { display: flex; gap: 0.75rem; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; scrollbar-width: none; padding: 0.25rem 0.125rem; }
        .gpc-track::-webkit-scrollbar { display: none; }
        .gpc-slide { flex: 0 0 auto; width: 70%; scroll-snap-align: start; }
        @media (min-width: 640px) { .gpc-slide { width: calc((100% - 1.5rem) / 3); } }
        .gpc-photo { display: block; width: 100%; padding: 0; border: 0; background: none; cursor: pointer; text-align: left; border-radius: 0.5rem; }
        .gpc-photo:disabled { cursor: not-allowed; opacity: 0.6; }
        .gpc-frame { position: relative; aspect-ratio: 4 / 3; overflow: hidden; border-radius: 0.5rem; background: var(--gray-100); box-shadow: 0 0 0 1px color-mix(in oklab, var(--gray-950) 10%, transparent); transition: box-shadow 150ms; }
        .gpc-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .gpc-photo[aria-pressed="true"] .gpc-frame { box-shadow: 0 0 0 3px var(--primary-500); }
        .gpc-photo:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 2px; }
        .gpc-check { position: absolute; top: 0.375rem; right: 0.375rem; display: flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: var(--primary-600); color: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / 0.3); }
        .gpc-arrow { position: absolute; top: 40%; transform: translateY(-50%); z-index: 1; display: flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 9999px; border: 0; cursor: pointer; background: rgb(255 255 255 / 0.9); color: var(--gray-700); box-shadow: 0 1px 3px rgb(0 0 0 / 0.25), 0 0 0 1px color-mix(in oklab, var(--gray-950) 10%, transparent); }
        .gpc-arrow:hover { background: #fff; color: var(--gray-950); }
        .gpc-arrow:disabled { opacity: 0; pointer-events: none; }
        .gpc-arrow-prev { left: 0.25rem; }
        .gpc-arrow-next { right: 0.25rem; }
        .gpc-staged { margin-bottom: 1rem; padding: 0.75rem; border-radius: 0.5rem; background: var(--gray-50); box-shadow: 0 0 0 1px color-mix(in oklab, var(--gray-950) 5%, transparent); }
        .gpc-staged-heading { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--gray-950); }
        .gpc-staged-count { font-weight: 400; color: var(--gray-500); }
        .gpc-staged-list { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
        .gpc-thumb { position: relative; width: 4.5rem; }
        .gpc-thumb-frame { position: relative; width: 4.5rem; height: 4.5rem; overflow: hidden; border-radius: 0.375rem; background: var(--gray-200); display: flex; align-items: center; justify-content: center; font-size: 0.625rem; color: var(--gray-600); text-align: center; word-break: break-all; padding: 0; }
        .gpc-thumb-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .gpc-thumb-label { margin-top: 0.25rem; font-size: 0.625rem; line-height: 0.875rem; color: var(--gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .gpc-remove { position: absolute; top: -0.375rem; right: -0.375rem; display: flex; align-items: center; justify-content: center; width: 1.375rem; height: 1.375rem; border-radius: 9999px; border: 0; cursor: pointer; background: var(--gray-900); color: #fff; font-size: 0.875rem; line-height: 1; box-shadow: 0 1px 2px rgb(0 0 0 / 0.3); }
        .gpc-remove:hover { background: var(--danger-600); }
        .gpc-badge { position: absolute; bottom: 0.25rem; left: 0.25rem; padding: 0 0.25rem; border-radius: 0.25rem; font-size: 0.5625rem; line-height: 0.875rem; background: rgb(0 0 0 / 0.6); color: #fff; }
        .gpc-empty { font-size: 0.875rem; color: var(--gray-500); }

        .dark .gpc-frame { background: color-mix(in oklab, #fff 5%, transparent); box-shadow: 0 0 0 1px color-mix(in oklab, #fff 10%, transparent); }
        .dark .gpc-photo[aria-pressed="true"] .gpc-frame { box-shadow: 0 0 0 3px var(--primary-400); }
        .dark .gpc-check { background: var(--primary-500); }
        .dark .gpc-arrow { background: color-mix(in oklab, var(--gray-900) 90%, transparent); color: var(--gray-200); box-shadow: 0 1px 3px rgb(0 0 0 / 0.5), 0 0 0 1px color-mix(in oklab, #fff 10%, transparent); }
        .dark .gpc-arrow:hover { background: var(--gray-800); color: #fff; }
        .dark .gpc-staged { background: color-mix(in oklab, #fff 5%, transparent); box-shadow: 0 0 0 1px color-mix(in oklab, #fff 10%, transparent); }
        .dark .gpc-staged-heading { color: #fff; }
        .dark .gpc-staged-count, .dark .gpc-thumb-label, .dark .gpc-empty { color: var(--gray-400); }
        .dark .gpc-thumb-frame { background: color-mix(in oklab, #fff 10%, transparent); color: var(--gray-300); }
        .dark .gpc-remove { background: var(--gray-700); }
        .dark .gpc-remove:hover { background: var(--danger-500); }
    </style>
@endonce

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            photos: @js($photos),
            canScrollPrev: false,
            canScrollNext: false,
            selectedIds() {
                return Array.isArray(this.state) ? this.state.map(Number) : []
            },
            isSelected(id) {
                return this.selectedIds().includes(id)
            },
            toggle(id) {
                const ids = this.selectedIds()
                this.state = ids.includes(id) ? ids.filter((selectedId) => selectedId !== id) : [...ids, id]
            },
            remove(id) {
                this.state = this.selectedIds().filter((selectedId) => selectedId !== id)
            },
            get staged() {
                const ids = this.selectedIds()

                return this.photos.filter((photo) => ids.includes(photo.id))
            },
            updateArrows() {
                const track = this.$refs.track

                if (! track) {
                    return
                }

                this.canScrollPrev = track.scrollLeft > 4
                this.canScrollNext = track.scrollLeft + track.clientWidth < track.scrollWidth - 4
            },
            scrollByPage(direction) {
                const track = this.$refs.track
                const slide = track.querySelector('[data-slide]')
                const step = slide ? slide.getBoundingClientRect().width + 12 : track.clientWidth

                track.scrollBy({ left: direction * Math.max(step, Math.floor(track.clientWidth / step) * step), behavior: 'smooth' })
            },
        }"
        x-init="$nextTick(() => updateArrows())"
        x-on:resize.window.debounce.100ms="updateArrows()"
        {{ $getExtraAttributeBag()->class(['gpc']) }}
    >
        <div class="gpc-staged mb-4 rounded-lg bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <div class="gpc-staged-heading flex justify-between text-sm font-medium text-gray-950 dark:text-white">
                <span>Staged</span>
                <span class="gpc-staged-count text-gray-500 dark:text-gray-400" x-text="(staged.length + {{ count($stagedUploads) }}) + ' / {{ \App\Services\Prospects\ProspectReplyAttachmentService::MAX_ATTACHMENTS }} images'"></span>
            </div>

            <div class="gpc-staged-list mt-2 flex flex-wrap gap-2">
                <template x-for="photo in staged" x-bind:key="photo.id">
                    <div class="gpc-thumb" x-bind:title="photo.label">
                        <div class="gpc-thumb-frame">
                            <img x-bind:src="photo.url" x-bind:alt="photo.alt">
                        </div>
                        <div class="gpc-thumb-label" x-text="photo.label"></div>
                        @unless ($isDisabled)
                            <button
                                type="button"
                                class="gpc-remove"
                                x-bind:aria-label="'Remove ' + photo.label"
                                x-on:click="remove(photo.id)"
                            >&times;</button>
                        @endunless
                    </div>
                </template>

                @foreach ($stagedUploads as $upload)
                    <div class="gpc-thumb" title="{{ $upload['name'] }}" wire:key="{{ $getStatePath() }}-upload-{{ $upload['key'] }}" data-staged-upload>
                        <div class="gpc-thumb-frame">
                            @if ($upload['url'])
                                <img src="{{ $upload['url'] }}" alt="{{ $upload['name'] }}">
                            @else
                                <span>{{ $upload['name'] }}</span>
                            @endif
                            <span class="gpc-badge">Upload</span>
                        </div>
                        <div class="gpc-thumb-label">{{ $upload['name'] }}</div>
                    </div>
                @endforeach

                @if ($stagedUploads === [])
                    <p class="gpc-empty text-sm text-gray-500 dark:text-gray-400" x-show="staged.length === 0">
                        Nothing staged yet. Tap a photo above or upload images below.
                    </p>
                @endif
            </div>
        </div>

        @if ($photos === [])
            <p class="gpc-empty text-sm text-gray-500 dark:text-gray-400">No gallery photos yet.</p>
        @else
            <div class="gpc-viewport relative">
                <button
                    type="button"
                    class="gpc-arrow gpc-arrow-prev"
                    aria-label="Previous photos"
                    x-bind:disabled="! canScrollPrev"
                    x-on:click="scrollByPage(-1)"
                >
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::ChevronLeft" class="size-5" style="width: 1.25rem; height: 1.25rem;" />
                </button>

                <div
                    x-ref="track"
                    x-on:scroll.debounce.50ms="updateArrows()"
                    class="gpc-track flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth"
                    role="listbox"
                    aria-multiselectable="true"
                    aria-label="{{ $getLabel() }}"
                >
                    @foreach ($photos as $photo)
                        <div class="gpc-slide w-[70%] shrink-0 snap-start sm:w-1/3" data-slide wire:key="{{ $getStatePath() }}-photo-{{ $photo['id'] }}">
                            <button
                                type="button"
                                class="gpc-photo"
                                role="option"
                                title="{{ $photo['label'] }}"
                                data-photo-id="{{ $photo['id'] }}"
                                x-bind:aria-pressed="isSelected({{ $photo['id'] }}) ? 'true' : 'false'"
                                x-bind:aria-selected="isSelected({{ $photo['id'] }}) ? 'true' : 'false'"
                                x-on:click="toggle({{ $photo['id'] }})"
                                @disabled($isDisabled)
                            >
                                <span class="gpc-frame block aspect-[4/3] overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
                                    <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" loading="lazy" draggable="false" class="size-full object-cover">
                                    <span class="gpc-check" x-show="isSelected({{ $photo['id'] }})" x-cloak aria-hidden="true">
                                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::Check" style="width: 1rem; height: 1rem;" />
                                    </span>
                                </span>
                            </button>
                        </div>
                    @endforeach
                </div>

                <button
                    type="button"
                    class="gpc-arrow gpc-arrow-next"
                    aria-label="Next photos"
                    x-bind:disabled="! canScrollNext"
                    x-on:click="scrollByPage(1)"
                >
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::ChevronRight" class="size-5" style="width: 1.25rem; height: 1.25rem;" />
                </button>
            </div>
        @endif
    </div>
</x-dynamic-component>
