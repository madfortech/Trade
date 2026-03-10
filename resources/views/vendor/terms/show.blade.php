<x-app-layout>
    <div class="lg:grid grid-flow-col grid-rows-3 gap-4 py-6 px-3">
        <div class="row-span-3 bg-gray-200 py-14 px-4">
            <flux:heading size="xl" level="1">
                Please read our Terms and Conditions
            </flux:heading>
 
           
            <flux:text class="mt-2">
                {!! $terms->terms !!}
            </flux:text>
            <!-- <form action="{{ route('terms.store') }}" method="post">
                @csrf

                <div class="form-check">
                    <input name="terms" type="checkbox" class="form-check-input" id="terms_and_conditions" required>
                    <label class="form-check-label" for="terms_and_conditions">{{ __('terms::terms.label') }}</label>
                    @error('terms')
                        <div class="invalid-feedback" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <input type="submit" class="btn btn-primary" value="Submit">
                </div>
            </form> -->
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


