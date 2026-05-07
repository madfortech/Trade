<?php

namespace App\Services\AIAnalysis;

use App\Services\AIAnalysis\Prompts\NiftyPrompt;
use App\Services\AIAnalysis\Prompts\SensexPrompt;
use App\Services\AIAnalysis\Prompts\OptionPrompt;

class PromptFactory
{
    public function nifty(): NiftyPrompt
    {
        return new NiftyPrompt();
    }

    public function sensex(): SensexPrompt
    {
        return new SensexPrompt();
    }

    public function option(): OptionPrompt
    {
        return new OptionPrompt();
    }
}