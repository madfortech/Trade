<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Trade With AI') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

        @fluxAppearance
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">

        <flux:header container class="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:brand href="{{('/') }}"  name="AdminPanel" class="max-lg:hidden dark:hidden" />
        

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="home" href="{{ route('dashboard') }}" current>Home</flux:navbar.item>
            

                <flux:separator vertical variant="subtle" class="my-2"/>

                <flux:dropdown class="max-lg:hidden">
                    <flux:navbar.item icon:trailing="chevron-down">Faq</flux:navbar.item>

                    <flux:navmenu>
                        <flux:navmenu.item href="{{ route('faq.create') }}">Add new</flux:navmenu.item>
                    </flux:navmenu>
                </flux:dropdown>
            </flux:navbar>

            <flux:spacer />

    
            <flux:dropdown position="top" align="start">
                <flux:profile name="Admin" />

                <flux:menu>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <a href="{{ route('logout') }}" 
                            onclick="event.preventDefault();
                            this.closest('form').submit();"
                            class="block mt-4 lg:inline-block lg:mt-0 mt-4 ms-4 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                            Logout
                        </a>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

 

        <flux:main container>
            @yield('content')
        
        </flux:main>
        

        @livewireScripts
        @fluxScripts
    </body>
</html>
