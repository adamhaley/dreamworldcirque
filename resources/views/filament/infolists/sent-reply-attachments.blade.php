@once
    <style>
        .sra-heading { font-size: 0.875rem; font-weight: 500; color: var(--gray-950); }
        .sra-list { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 0.5rem 0 0; padding: 0; list-style: none; }
        .sra-thumb { width: 4.5rem; }
        .sra-thumb-frame { width: 4.5rem; height: 4.5rem; overflow: hidden; border-radius: 0.375rem; background: var(--gray-200); }
        .sra-thumb-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .sra-thumb-label { margin-top: 0.25rem; font-size: 0.625rem; line-height: 0.875rem; color: var(--gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .dark .sra-heading { color: #fff; }
        .dark .sra-thumb-label { color: var(--gray-400); }
        .dark .sra-thumb-frame { background: color-mix(in oklab, #fff 10%, transparent); }
    </style>
@endonce

<div {{ $attributes }} data-test="sent-reply-attachments">
    <p class="sra-heading text-sm font-medium text-gray-950 dark:text-white">Attachments</p>
    <ul class="sra-list mt-2 flex flex-wrap gap-2">
        @foreach ($attachments as $attachment)
            <li class="sra-thumb">
                <div class="sra-thumb-frame">
                    <img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] }}" loading="lazy">
                </div>
                <div class="sra-thumb-label" title="{{ $attachment['name'] }}">{{ $attachment['name'] }}</div>
            </li>
        @endforeach
    </ul>
</div>
