<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valora mas | @yield('title')</title>
    <link rel="icon" href="{{ asset('img/valoramas.png') }}">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
    <style>
        /* Reset básico para evitar conflictos */
        body {
            box-sizing: border-box;
            overflow-x: hidden;
        }
        
        /* Degradado principal (morado-azul) */
        .sidebar-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .navbar-gradient {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-bottom: 1px solid rgba(102, 126, 234, 0.2);
        }
        
        /* Sidebar: Ancho fijo y visible por defecto */
        #sidebar-wrapper {
            width: 250px;
            min-width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: margin-left 0.3s ease;
        }
        
        /* Contenido principal: Empujado a la derecha y ancho dinámico, ahora flex para footer sticky */
        #page-content-wrapper {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease, width 0.3s ease;
            box-sizing: border-box;
        }
        
        /* Contenido principal crece para empujar footer abajo */
        #page-content-wrapper .container-fluid {
            flex: 1;
            padding: 1rem 2rem;
        }
        
        /* Footer siempre al fondo */
        .footer {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-top: 1px solid rgba(102, 126, 234, 0.1);
            margin-top: auto;
            padding: 1rem 0;
        }
        
        /* Sidebar items */
        .list-group-item {
            background: transparent !important;
            border: none !important;
            color: rgba(255, 255, 255, 0.9) !important;
            transition: all 0.3s ease;
            padding: 1rem 1.5rem;
        }
        
        .list-group-item:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            transform: translateX(5px);
        }
        
        .sidebar-heading {
            background: rgba(0, 0, 0, 0.2);
            color: white;
            padding: 1.5rem;
            font-weight: bold;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Toggle button */
        #menu-toggle {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.3s ease;
        }
        
        #menu-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* Navbar nav-links */
        .nav-link {
            color: #667eea !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover {
            color: #764ba2 !important;
        }
        
        /* Estado toggled: Ocultar sidebar y expandir contenido */
        #wrapper.toggled #sidebar-wrapper {
            margin-left: -250px;
        }
        
        #wrapper.toggled #page-content-wrapper {
            margin-left: 0;
            width: 100%;
        }
        
        /* Responsive: En móviles, sidebar oculto por defecto */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: -250px; /* Oculto por defecto en móvil */
            }
            #page-content-wrapper {
                margin-left: 0;
                width: 100%;
            }
            #wrapper.toggled #sidebar-wrapper {
                margin-left: 0; /* Mostrar al togglear en móvil */
            }
            #page-content-wrapper .container-fluid {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="border-right sidebar-gradient" id="sidebar-wrapper">
            <img src="{{ asset('valoramas.png') }}" alt="Logo Valora Más" style="width: 60%; margin-left: 20%;">
            <div class="sidebar-heading">Valora mas</div>
            <div class="list-group list-group-flush">
                <a href="{{ url('/home') }}" class="list-group-item list-group-item-action">
                    <i class="bi bi-house-fill me-2"></i>Inicio
                </a>
                <a href="{{ route('resumen-ejecutivo.index') }}" class="list-group-item list-group-item-action">
                    <i class="bi bi-bar-chart-line me-2"></i>Resumen Ejecutivo
                </a>
                <a class="list-group-item list-group-item-action" href="{{ route('logout') }}"
                   onclick="event.preventDefault();
                                 document.getElementById('logout-form').submit();">
                    <i class="bi bi-door-open-fill me-2" aria-hidden="true"></i>Salir
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light navbar-gradient border-bottom">
                <div class="container-fluid">
                    <!-- Botón toggle -->
                    <button class="btn btn-primary" id="menu-toggle"><i class="bi bi-list"></i></button>

                    <!-- Navbar collapse para notificaciones -->
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                            <li class="nav-item">
                                <a class="nav-link" href="#">{{Auth::user()->nombre}} {{Auth::user()->primer_apellido}} {{Auth::user()->segundo_apellido}}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                @yield('content')
            </div>

            <footer class="footer mt-auto py-3 bg-light">
                <div class="container text-center">
                    <span class="text-muted">Copyright&copy; {{ date('Y')}} Sistema Integral de Aplicaciones • Tecnologías de información</span>
                </div>
            </footer>
        </div>
        <!-- /#page-content-wrapper -->
    </div>
    <!-- /#wrapper -->

    @yield('scripts')
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById("menu-toggle").addEventListener("click", function(e) {
            e.preventDefault();
            document.getElementById("wrapper").classList.toggle("toggled");
        });
    </script>
</body>
</html>