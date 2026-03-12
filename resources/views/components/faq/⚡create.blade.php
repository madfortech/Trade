<?php

use Livewire\Component;

use App\Models\Faq;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Locked;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

new class extends Component
{
    #[Rule('required')]
    public string $question = '';
    #[Rule('required')]
    public string $answer = '';
    #[Locked]
    public int $faqId;

    public ?Faq $faq;

    
    public function save(): void
    {
        $this->validate();

        if (empty($this->faq)) {
            Faq::create([
                'question' => $this->question,
                'answer' => $this->answer,
            ]);
        } else {
            $this->faq->update([
                'question' => $this->question,
                'answer' => $this->answer,
            ]);
        }
    }
    
};
?>

<div>
   <div class="grid grid-flow-col grid-rows-3 gap-4">
        <div class="row-span-3">
            <flux:heading size="lg">Create FAQ</flux:heading>

            <form wire:submit="save" class="mt-4 border-2 px-2 py-2">
                @csrf
                <flux:field>
                    <flux:label>Question</flux:label>

         
                    <flux:input  wire:model.defer="question" />
                    @error('question')
                        <flux:error name="question" />
                       
                    @enderror
               

             
                    <flux:label>Answer</flux:label>

         
                    <flux:textarea wire:model.defer="answer" />
                    @error('answer')
                        <flux:error name="answer" />
                       
                    @enderror
                </flux:field>
               
                <flux:button type="submit" class="mt-4">
                    {{ $faqId ? 'Save Changes' : 'Save' }}
                </flux:button>

            </form>
        </div>
        <div class="col-span-2">02</div>
        <div class="col-span-2 row-span-2">03</div>
   </div>
</div>