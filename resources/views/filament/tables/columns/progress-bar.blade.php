@php
    $percentage = (float) $getState();
    $boundedPercentage = min(max($percentage, 0), 100);
    $barColor = match (true) {
        $percentage < 50 => 'bg-red-500',
        $percentage < 100 => 'bg-yellow-500',
        default => 'bg-green-500',
    };
@endphp

<div class="flex min-w-36 items-center gap-3">
    <div
        role="progressbar"
        aria-label="Progres"
        aria-valuemin="0"
        aria-valuemax="100"
        aria-valuenow="{{ $boundedPercentage }}"
        class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
    >
        <div
            class="h-full rounded-full {{ $barColor }}"
            style="width: {{ $boundedPercentage }}%"
        ></div>
    </div>
    <span class="shrink-0 text-sm text-gray-700 dark:text-gray-300">
        {{ \Illuminate\Support\Number::format($percentage, maxPrecision: 1, locale: app()->getLocale()) }}%
    </span>
</div>
