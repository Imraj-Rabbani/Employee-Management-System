<x-layouts.app :title="__('Dashboard')">
    {{-- Placeholder boxes (striped background) until the real dashboard widgets are built. --}}
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="relative aspect-video overflow-hidden glass rounded-2xl">
                <svg class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" fill="none">
                    <defs>
                        <pattern id="pattern-1" x="0" y="0" width="8" height="8" patternUnits="userSpaceOnUse">
                            <path d="M-1 5L5 -1M3 9L8.5 3.5" stroke-width="0.5"></path>
                        </pattern>
                    </defs>
                    <rect stroke="none" fill="url(#pattern-1)" width="100%" height="100%"></rect>
                </svg>
            </div>
            <div class="relative aspect-video overflow-hidden glass rounded-2xl">
                <svg class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" fill="none">
                    <defs>
                        <pattern id="pattern-2" x="0" y="0" width="8" height="8" patternUnits="userSpaceOnUse">
                            <path d="M-1 5L5 -1M3 9L8.5 3.5" stroke-width="0.5"></path>
                        </pattern>
                    </defs>
                    <rect stroke="none" fill="url(#pattern-2)" width="100%" height="100%"></rect>
                </svg>
            </div>
            <div class="relative aspect-video overflow-hidden glass rounded-2xl">
                <svg class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" fill="none">
                    <defs>
                        <pattern id="pattern-3" x="0" y="0" width="8" height="8" patternUnits="userSpaceOnUse">
                            <path d="M-1 5L5 -1M3 9L8.5 3.5" stroke-width="0.5"></path>
                        </pattern>
                    </defs>
                    <rect stroke="none" fill="url(#pattern-3)" width="100%" height="100%"></rect>
                </svg>
            </div>
        </div>
        <div class="relative h-full flex-1 glass rounded-2xl">
            <svg class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" fill="none">
                <defs>
                    <pattern id="pattern-4" x="0" y="0" width="8" height="8" patternUnits="userSpaceOnUse">
                        <path d="M-1 5L5 -1M3 9L8.5 3.5" stroke-width="0.5"></path>
                    </pattern>
                </defs>
                <rect stroke="none" fill="url(#pattern-4)" width="100%" height="100%"></rect>
            </svg>
        </div>
    </div>
</x-layouts.app>
