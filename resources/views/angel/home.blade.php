<x-app-layout>
 

  <div class="py-12 text-gray-900">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="py-12 text-gray-900">
        

          <div class="flex border-t-2 border-gray-500 border-b-2 border-gray-500 space-x-4 capitalize">
            <!-- Nifty -->
            <div class="py-2">

              @php 
                $nLtp = number_format($nifty['ltp'] ?? 0, 2);
                $nChange = number_format($nifty['netChange'] ?? 0, 2);
                $nPercent = number_format($nifty['percentChange'] ?? 0, 2);
                $nSign = ($nifty['netChange'] ?? 0) > 0 ? '+' : '';
                $nColor = (($nifty['netChange'] ?? 0) >= 0) ? 'text-green-600' : 'text-red-600';
                $nHigh = ($nifty['high'] > 0) ? number_format($nifty['high'], 2) : '---';
                $nLow = ($nifty['low'] > 0) ? number_format($nifty['low'], 2) : '---';
              @endphp
              
              <flux:link href="{{ route('angel.nifty.chart') }}" class="font-bold text-lg">
                NIFTY: <span id="nifty-ltp">{{ $nLtp }}</span>
                  <div class="text-[10px] text-gray-400">
                      Low: <span id="nifty-low">{{ $nLow }}</span> 
                      High: <span id="nifty-high">{{ $nHigh }}</span>
                  </div>
                  
                  {{-- Flux Text Component with ID for Color and Container for Change --}}
                  <flux:text id="nifty-status" class="mt-2 {{ $nColor }} text-sm font-medium">
                      <span id="nifty-change">{{ $nSign }}{{ $nChange }}</span> 
                      (<span id="nifty-percent">{{ $nPercent }}</span>%)
                  </flux:text>
              </flux:link>

            </div>
            <!-- End Nifty -->

            <!-- Sensex -->
             <div class="py-2">
              <flux:link href="{{ route('angel.sensex.option-chain') }}" class="font-bold text-lg">
                  SENSEX: <span id="sensex-ltp">0.00</span>
                  <div class="text-[10px] text-gray-400">
                      Low: <span id="sensex-low">---</span> 
                      High: <span id="sensex-high">---</span>
                  </div>
                    
                  {{-- Is ID "sensex-status" ko dhyan se check karein --}}
                  <flux:text id="sensex-status" class="mt-2 text-sm font-medium">
                      <span id="sensex-change">0.00</span> 
                      (<span id="sensex-percent">0.00</span>%)
                  </flux:text>
                </flux:link>
              </div>

            <!-- End Sensex -->

            <!-- Crude Oil -->
            <div class="py-2">
              @php 
                $coLtp = number_format($crudeOil['ltp'] ?? 0, 2);
                $coChange = number_format($crudeOil['netChange'] ?? 0, 2);
                $coPercent = number_format($crudeOil['percentChange'] ?? 0, 2);
                $coSign = ($crudeOil['netChange'] ?? 0) > 0 ? '+' : '';
                $coColor = (($crudeOil['netChange'] ?? 0) >= 0) ? 'text-green-600' : 'text-red-600';
                $coHigh = ($crudeOil['high'] > 0) ? number_format($crudeOil['high'], 2) : '---';
                $coLow = ($crudeOil['low'] > 0) ? number_format($crudeOil['low'], 2) : '---';
              @endphp
              
              <flux:link href="{{ route('angel.crude-option') }}" class="font-bold text-lg">
                Crude Oil: <span id="crude-oil-ltp">{{ $coLtp }}</span>
                  <div class="text-[10px] text-gray-400">
                      Low: <span id="crude-oil-low">{{ $coLow }}</span> 
                      High: <span id="crude-oil-high">{{ $coHigh }}</span>
                  </div>
                  
                  <flux:text id="crude-oil-status" class="mt-2 {{ $coColor }} text-sm font-medium">
                      <span id="crude-oil-change">{{ $coSign }}{{ $coChange }}</span> 
                      (<span id="crude-oil-percent">{{ $coPercent }}</span>%)
                  </flux:text>
              </flux:link>
            </div>
            <!-- End Crude Oil -->
          </div>

          <div class="lg:grid grid-cols-3 gap-4 my-4">

            <!-- Wide Left Section -->
            <div class="col-span-2 row-span-3 border p-4 mb-2">
                <div class=""></div>
            </div>

            <!-- Right Top -->
            <div class="border p-4 mb-2">
                
            </div>

            <!-- Right Bottom -->
            <div class="border p-4 mb-2">
              <ul class="space-y-2">
                <flux:heading size="xl" class="capitalize">User profile</flux:heading>

                <li>
                  <strong>Name:</strong> {{ $profile['name'] ?? 'N/A' }}
                </li>

                <li>
                  <strong>Client Code:</strong> {{ $profile['clientcode'] ?? 'N/A' }}
                </li>

                <li>
                  <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded">
                        Logout
                    </button>
                  </form>
                </li>
              </ul>
            </div>

          </div>

        </div>
      </div>
    </div>
  </div>
  
</x-app-layout>

 
<script>
    function updateMarket() {
        fetch("{{ route('angel.market.json') }}")
            .then(res => {
                if(res.status === 401) {
                    window.location.reload(); 
                    return;
                }
                return res.json();
            })
            .then(data => {
                // --- NIFTY UPDATE ---
                 if (data.nifty) {

                  document.getElementById('nifty-ltp').innerText = data.nifty.ltp ?? '0.00';
                  document.getElementById('nifty-change').innerText = data.nifty.change ?? '0.00';
                  document.getElementById('nifty-percent').innerText = data.nifty.percent ?? '0.00';

                  // High / Low safe update
                  document.getElementById('nifty-high').innerText =
                      (data.nifty.high && data.nifty.high !== '0.00')
                          ? data.nifty.high
                          : '---';

                  document.getElementById('nifty-low').innerText =
                      (data.nifty.low && data.nifty.low !== '0.00')
                          ? data.nifty.low
                          : '---';

                  const nStatus = document.getElementById('nifty-status');
                  if (nStatus) {
                      nStatus.className = `mt-2 ${data.nifty.color} text-sm font-medium`;
                  }
                }


                // --- SENSEX UPDATE ---
                if(data.sensex) {
                    document.getElementById('sensex-ltp').innerText = data.sensex.ltp;
                    document.getElementById('sensex-low').innerText = data.sensex.low;
                    document.getElementById('sensex-high').innerText = data.sensex.high;
                    document.getElementById('sensex-change').innerText = data.sensex.change;
                    document.getElementById('sensex-percent').innerText = data.sensex.percent;
                    
                    const sStatus = document.getElementById('sensex-status');
                    if(sStatus) sStatus.className = `mt-2 ${data.sensex.color} text-sm font-medium`;
                }

                // --- CRUDE OIL UPDATE ---
                if(data.crudeOil) {
                    document.getElementById('crude-oil-ltp').innerText = data.crudeOil.ltp;
                    document.getElementById('crude-oil-low').innerText = data.crudeOil.low;
                    document.getElementById('crude-oil-high').innerText = data.crudeOil.high;
                    document.getElementById('crude-oil-change').innerText = data.crudeOil.change;
                    document.getElementById('crude-oil-percent').innerText = data.crudeOil.percent;
                    
                    const coStatus = document.getElementById('crude-oil-status');
                    if(coStatus) coStatus.className = `mt-2 ${data.crudeOil.color} text-sm font-medium`;
                }
            })
            .catch(error => console.error('Market Update Error:', error));
    }

    // Har 5 second mein auto-update
    setInterval(updateMarket, 3000); 
    
    // Initial load par turant call karein
    document.addEventListener('DOMContentLoaded', updateMarket);
</script>
