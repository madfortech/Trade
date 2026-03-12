<x-app-layout>
    <div class="grid grid-flow-col grid-rows-3 gap-4 py-6 px-3">
        <div class="row-span-3 bg-gray-200 py-4 px-4">
            <flux:heading size="xl" level="1">
                {{ Auth::user()->name }}
            </flux:heading>

            <flux:text class="mt-2 mb-6 text-base">
                Created at: {{ Auth::user()->created_at->format('F j, Y') }}
            </flux:text>

            <flux:separator variant="subtle" />
        </div>

        <div class="col-span-2 bg-gray-200">
            <!-- <flux:navbar>
                <flux:navbar.item href="#">
                   
                </flux:navbar.item>
            </flux:navbar> -->
        </div>

        <!-- <div class="col-span-2 row-span-2 bg-gray-200"></div> -->
    </div>
</x-app-layout>