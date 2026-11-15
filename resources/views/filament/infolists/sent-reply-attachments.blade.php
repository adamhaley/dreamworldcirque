<div {{ $attributes }} data-test="sent-reply-attachments">
    <p class="text-sm font-medium text-gray-950 dark:text-white">Attachments</p>
    <ul class="mt-2 flex flex-wrap gap-4">
        @foreach ($attachments as $attachment)
            <li class="flex items-center gap-2">
                <img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] }}" class="h-16 w-16 rounded object-cover" loading="lazy">
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $attachment['name'] }}</span>
            </li>
        @endforeach
    </ul>
</div>
