<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4 text-center text-sm text-gray-300" :status="session('status')" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-blue-900 to-black p-4">
        <div class="w-full max-w-md bg-gray-800/50 backdrop-blur-md p-8 rounded-2xl shadow-2xl shadow-blue-500/50 border border-blue-500/30">
            <h2 class="text-3xl font-bold text-center text-white mb-6">Iniciar Sesión</h2>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Campo nick_name -->
                <div class="mb-6">
                    <label for="nick_name" class="block text-sm font-medium text-gray-300 mb-2">Usuario</label>
                    <input 
                        id="nick_name" 
                        type="text" 
                        name="nick_name" 
                        value="{{ old('nick_name') }}" 
                        required 
                        autofocus 
                        class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300"
                        placeholder="Ingresa tu usuario"
                    >
                    @error('nick_name')
                        <span class="text-red-500 text-sm mt-2 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Campo contraseña -->
                <div class="mb-6 relative">
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Contraseña</label>
                    <input 
                        id="password" 
                        type="password" 
                        name="password" 
                        required 
                        class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300"
                        placeholder="Ingresa tu contraseña"
                    >
                    <button 
                        type="button" 
                        onclick="togglePassword()" 
                        class="absolute right-3 top-10 text-gray-400 hover:text-gray-200"
                    >
                        <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                    @error('password')
                        <span class="text-red-500 text-sm mt-2 block">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Recordarme -->
                <div class="flex items-center mb-6">
                    <input 
                        id="remember_me" 
                        type="checkbox" 
                        name="remember" 
                        class="h-4 w-4 text-blue-500 focus:ring-blue-500 border-gray-700 rounded bg-gray-900"
                    >
                    <label for="remember_me" class="ml-2 text-sm text-gray-300">{{ __('Remember me') }}</label>
                </div>

                <!-- Botones -->
                <div class="flex items-center justify-between">
                    @if (Route::has('password.request'))
                        <a 
                            href="{{ route('password.request') }}" 
                            class="text-sm text-blue-400 hover:text-blue-300 transition-colors duration-300"
                        >
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif
                    <button 
                        type="submit" 
                        class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg shadow-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300"
                    >
                        {{ __('Log in') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script para mostrar/ocultar contraseña -->
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                `;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                `;
            }
        }
    </script>
</x-guest-layout>
