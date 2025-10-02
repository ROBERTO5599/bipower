@extends('layouts.guest')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <!-- Contenedor del logo (fuera de la card, fondo blanco) -->
        <div class="col-12 text-center mb-4 logo-container">
            <img src="{{ asset('valoramas.png') }}" alt="Valora Más Logo" class="logo-img" style="max-width: 250px; max-height: 150px; object-fit: contain; transition: opacity 0.3s ease;">
        </div>
        
        <div class="col-xl-10 col-lg-12 col-md-9">
            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <!-- Header degradado SIN logo -->
                <div class="card-header bg-gradient text-white text-center py-4 position-relative">
                    <h3 class="mb-1 fw-bold">Iniciar Sesión</h3>
                    <p class="mb-0 opacity-75">Ingresa tus credenciales para continuar</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <!-- Mensaje de status -->
                    @if (session('status'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('status') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <!-- Campo Usuario -->
                        <div class="mb-4">
                            <label for="nick_name" class="form-label fw-semibold text-muted mb-2">Usuario</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-person-fill text-primary"></i>
                                </span>
                                <input 
                                    id="nick_name" 
                                    type="text" 
                                    name="nick_name" 
                                    value="{{ old('nick_name') }}" 
                                    required 
                                    autofocus 
                                    class="form-control border-start-0 @error('nick_name') is-invalid @enderror shadow-none"
                                    placeholder="Ingresa tu usuario"
                                >
                            </div>
                            @error('nick_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Campo Contraseña -->
                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold text-muted mb-2">Contraseña</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-lock-fill text-primary"></i>
                                </span>
                                <input 
                                    id="password" 
                                    type="password" 
                                    name="password" 
                                    required 
                                    class="form-control border-start-0 @error('password') is-invalid @enderror shadow-none"
                                    placeholder="Ingresa tu contraseña"
                                >
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePassword()">
                                    <i id="eye-icon" class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Recordarme y Olvidé -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                <label class="form-check-label text-muted" for="remember_me">
                                    Recordarme
                                </label>
                            </div>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-primary text-decoration-none fw-semibold">
                                    ¿Olvidaste tu contraseña?
                                </a>
                            @endif
                        </div>

                        <!-- Botón Submit -->
                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold py-3 rounded-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    }
    
    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
    }
    
    .input-group:focus-within .form-control,
    .input-group:focus-within .input-group-text {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }
    
    /* Logo: Fuera de la card, centrado y estable */
    .logo-container {
       /* background: white;  Fondo blanco explícito */
        padding: 1rem 0;
    }
    
    .logo-img {
        opacity: 1;
    }
    
    .logo-img:hover {
        opacity: 0.9;
    }
</style>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.remove('bi-eye');
            eyeIcon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.remove('bi-eye-slash');
            eyeIcon.classList.add('bi-eye');
        }
    }
</script>
@endsection