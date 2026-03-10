@extends('layouts.admin')

@section('content')

 
 
    <flux:heading size="xl" level="1">
        {{ Auth::user()->name }}
    </flux:heading>

    <flux:text class="mt-2 mb-6 text-base">
        Created at: {{ Auth::user()->created_at->format('F j, Y') }}
    </flux:text>

    <flux:separator variant="subtle" />

 
@endsection