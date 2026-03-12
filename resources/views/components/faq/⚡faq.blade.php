<?php

use Livewire\Component;
use Illuminate\View\View;
use App\Models\Faq;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;

new class extends Component
{
    public bool $showModal = false;

   #[Rule('required')]
    public string $question = '';
    #[Rule('required')]
    public string $answer = '';
    
    #[Locked]
    public int $faqId;
    public ?Faq $faq;


    public function edit(int $faqId): void
    {
        $faq = Faq::findOrFail($faqId);
        $this->faqId    = $faqId;
        $this->question = $faq->question;
        $this->answer   = $faq->answer;
        $this->showModal = true;
    }

    public function update(): void
    {
        $this->validate();

        Faq::findOrFail($this->faqId)->update([
            'question' => $this->question,
            'answer'   => $this->answer,
        ]);

        $this->reset('faqId', 'question', 'answer', 'showModal');
    }
    
    
    // Render the component
    public function render()
    {
        return $this->view([
            'faqs' => Faq::all(),
        ]);
    }

    // Delete a FAQ entry
    public function delete(int $faqId): void
    {
        Faq::where('id', $faqId)->delete();
    }
 
};
?>

<div>
    <div class="flex flex-col px-6 py-6">
        <div class="py-12 max-w-4xl">
            <flux:heading class="font-bold" size="xl">
                Frequently Asked Questions
            </flux:heading>
            
            <div class="flex flex-col py-4 gap-4 px-4 mt-4">
                @forelse ($faqs as $faq)
                <div class="border-b-4 border-gray-200 py-6 mb-5">
                    <flux:heading size="xl" class="mb-2">{{ $faq->question }}</flux:heading>
                    <flux:text size="lg">{{ $faq->answer }}</flux:text>
                    @role('admin')
                        <flux:button size="xs"
                            wire:click="edit({{ $faq->id }})">
                            Edit
                        </flux:button>
                    
                        <flux:button size="xs"
                            wire:click="delete({{ $faq->id }})"
                            onclick="confirm('Are you sure?') || event.stopImmediatePropagation()">
                            Delete
                        </flux:button>
                    @endrole
                </div>
                @empty
                <div class="border-b-4 border-gray-200 py-6 mb-5">
                    <flux:heading size="xl" class="mb-2">No questions found</flux:heading>
                    <flux:text size="lg">No answers available at the moment.</flux:text>
                </div>
                @endforelse
            </div>
            
        </div>

        <!-- edit -->
        <div @class([
            'flex items-center justify-center fixed left-0 bottom-0 w-full h-full bg-gray-800 bg-opacity-90',
            'hidden' => ! $showModal,
            ])>
            <div class="bg-white rounded-lg w-1/2">
                <form wire:submit="update" class="w-full">
                    <div class="flex flex-col items-start p-4">
                        <div class="flex items-center w-full border-b pb-4">
                            <div class="text-gray-900 font-medium text-lg">{{ $faqId ? 'Edit FAQ' : 'Add New FAQ' }}</div>
                            
                          
                        </div>
                        <div class="w-full mt-4">
                            <label for="question" class="block font-medium text-sm text-gray-700">
                                Question
                            </label>
                            <input wire:model="question" id="question"
                                class="mt-2 text-sm sm:text-base pl-2 pr-4 rounded-lg border border-gray-400 w-full py-2 focus:outline-none focus:border-blue-400"/>
                            @error('question')
                                <p class="mt-2 text-sm text-red-600" id="question-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="py-4 border-b w-full mb-4">
                            <label for="answer" class="block font-medium text-sm text-gray-700">
                                Answer
                            </label>
                            <input wire:model="answer" id="answer"
                                class="mt-2 text-sm sm:text-base pl-2 pr-4 rounded-lg border border-gray-400 w-full py-2 focus:outline-none focus:border-blue-400"/>
                            @error('answer')
                                <p class="mt-2 text-sm text-red-600" id="answer-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="ml-auto">
                            <flux:button wire:click="$toggle('showModal')">
                                {{ $faqId ? 'Save Changes' : 'Save' }}
                            </flux:button>

                            
                           
                            <flux:button wire:click="$toggle('showModal')"  data-dismiss="modal">
                                Close
                            </flux:button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- edit -->
    </div>
    

</div>

         
    