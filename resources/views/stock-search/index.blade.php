<x-app-layout>
 

  <div class="py-12 text-gray-900">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="py-12 text-gray-900">
        

          <div class="flex border-t-2 border-gray-500 border-b-2 border-gray-500 space-x-4 capitalize">
            <!-- Nifty -->
            <div class="py-2">

             
              
               
            </div>
            <!-- End Nifty -->

         
          </div>

          <div class="lg:grid grid-cols-3 gap-4 my-4">

            <!-- Wide Left Section -->
            <div class="col-span-2 row-span-3 border p-4 mb-2">
                <div
                    id="tv-chart"
                    class="h-[700px] border rounded-lg"
                >
                </div>
            </div>

            <!-- Right Top -->
            <div class="border p-4 mb-2">
              @include('stock-search.partials.form')
            </div>

            <!-- Right Bottom -->
            <div class="border p-4 mb-2">
              <ul class="space-y-2">
                <flux:heading size="xl" class="capitalize">Results</flux:heading>

                @include('stock-search.partials.results')
              </ul>

              <div id="aiPanel" class="hidden border rounded-lg bg-white overflow-hidden">

                <div class="px-4 py-3 border-b bg-slate-50">
                  <flux:heading size="xl">
                    AI Prediction
                  </flux:heading>
                </div>

                @include('stock-search.partials.ai-results')

              </div>
            </div>
            

          </div>

        </div>
      </div>
    </div>
  </div>
  
</x-app-layout>

