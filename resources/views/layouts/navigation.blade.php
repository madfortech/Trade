<nav class="flex items-center justify-between flex-wrap bg-purple-200 py-6 px-6">
    <div class="flex items-center text-gray-800 mr-6">
        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
    </div>
    

    <div class="w-full block flex-grow lg:flex lg:items-center lg:w-auto">

        <div class="text-sm lg:flex-grow lg:flex justify-center py-4 bg-purple-100 sm:rounded-full shadow-lg">
            <a href="{{('/') }}" class="block mt-4 lg:inline-block lg:mt-0 text-gray-800 hover:text-gray-300 mr-4">
                Home
            </a>
            <a href="{{ route('faq') }}" class="block mt-4 lg:inline-block lg:mt-0 text-gray-800 
                hover:text-gray-300 mr-4">
                Faq
            </a>
            <a href="{{ route('contact') }}" class="block mt-4 lg:inline-block lg:mt-0 text-gray-800 hover:text-gray-300">
                Contact
            </a>

            @auth
        
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <a href="{{ route('logout') }}" 
                    onclick="event.preventDefault();
                    this.closest('form').submit();"
                    class="block mt-4 lg:inline-block lg:mt-0 mt-4 ms-4 text-gray-800 hover:text-gray-300">
                    Logout
                </a>
            </form>
            @endauth
        </div>

        <div class="space-x-4 ml-4">
            @if (Route::has('login'))
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-block text-sm px-4 py-2 font-semibold leading-none mt-4 lg:mt-0">
                        Dashboard
                    </a>
                    @role('admin')
                        <a href="{{ route('admin') }}" class="inline-block text-sm px-4 py-2 font-semibold leading-none mt-4 lg:mt-0">
                            Admin
                        </a>
                    @endrole
                    @else
                    <a href="{{ route('login') }}" class="inline-block text-sm px-4 py-2 font-semibold leading-none border rounded text-gray-800  border-white      
                        hover:border-transparent hover:text-gray-300 hover:bg-purple-700 mt-4 lg:mt-0">
                        Login
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-block text-sm px-4 py-2 font-semibold leading-none border rounded text-gray-800  border-white      
                            hover:border-transparent hover:text-gray-300 hover:bg-purple-700 mt-4 lg:mt-0">
                            Register
                        </a>
                    @endif
                @endauth
            @endif


        </div>
    </div>
</nav>