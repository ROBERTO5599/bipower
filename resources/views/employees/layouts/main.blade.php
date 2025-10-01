

    
        
        

    <title>Valora mas | {{ $title }}</title>
    <link rel="icon" href="{{ asset('img/valoramas.png') }}">
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/plugins/iCheck/custom.css') }}" rel="stylesheet">
    <link href="{{ asset('css/animate.css') }}" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet">

    <!--                iconos                     -->
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
   

    @yield('styles')
    <style>
       /* Estilos generales */
        body {
            font-family: 'Roboto Regular', sans-serif;
            background-color: #f3f3f4;
            color: #321064;
            margin: 0;
            padding: 0;
            overflow-x: hidden; /* Evita el desplazamiento horizontal */
        }

        /* Menú lateral */
        .navbar-static-side {
            background-color: #321064;
            color: white;
            width: 220px; 
            top: 0;
            bottom: 0;
            left: 0;
        }

        .navbar-static-side ul li a {
            color: #fceea6;
            transition: all 0.3s ease;
        }

        .nav-header img {
            border: 3px solid #fceea6;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin: 0 auto;
        }


        .navbar-static-side ul li a:hover {
            background-color: #fceea6;
            color: #321064;
        }

        /* Contenedor principal */
        #page-wrapper { 
            background: #fff;
            min-height: 100vh;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Footer */
        .footer {
            background-color: #d5d5d5;
            color: #321064; 
            text-align: center;
            align-items: center;
            padding: 10px;
            font-size: 14px;
            margin-left: 50px;
            position: fixed;
            bottom: 0;
            z-index: 10;
        }

        /* Botones */
        .btn-green {
            background-color: #198754;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 10px 15px;
            transition: background-color 0.3s ease;
        }

        
        .btn-green:hover {
            background-color: #146c43;
        }

        /* Tabs */
        .nav-tabs .nav-link {
            background: #321064;
            color: white;
            margin: 0 5px;
            border-radius: 5px 5px 0 0;
        }

        .nav-tabs .nav-link.active {
            background: #f3ca3c;
            color: #321064;
        }

        /* Modal */
        .modal-content {
            border-radius: 10px;
        }

        .bell-icon {
            position: relative;
            font-size: 24px;
        }

        .notification-count {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            display: none; /* Initially hidden, shown via JS */
        }

        /* Ensure notification count is visible in mini-navbar */
        .mini-navbar .notification-count {
            display: inline-block; /* Show in collapsed state */
            right: 5px; /* Adjust position for collapsed menu */
            top: -5px;
            font-size: 10px; /* Slightly smaller for compact view */
        }

        /* Ensure notification count is visible in expanded state */
        .navbar-static-side .notification-count {
            display: inline-block; /* Ensure visibility in expanded state */
        }

        @keyframes shake {
            0% { transform: rotate(0deg); }
            25% { transform: rotate(-15deg); }
            50% { transform: rotate(15deg); }
            75% { transform: rotate(-10deg); }
            100% { transform: rotate(0deg); }
        }

        .bell-icon.shake {
            animation: shake 0.5s ease-in-out infinite;
        }

        /* Header general */
        .navbar-header {
            background-color: #321064;
            color: #fceea6;
            padding: 10px 20px;
            border-bottom: 2px solid #fceea6;
            width: 100%;
        }

        /* Icono de notificaciones */
        .bell-icon {
            font-size: 20px;
            color: #fae372;
            position: relative;
            cursor: pointer;
            transition: transform 0.3s;
        }

        a {
            pointer-events: auto;
            touch-action: manipulation;
            text-decoration: none;
            cursor: pointer;
            user-select: none; /* Evitar selección de texto */
        }

        @media screen and (max-width: 768px) {
            #page-wrapper {
                margin-left: 0;
            }

            .navbar-static-side {
                position: relative;
                width: 100%;
            }

            .footer {
                left: 0;
                width: 100%;
            }

            .notification-count {
                display: inline-block; /* Ensure visibility on mobile */
            }
        }

    </style>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Scripts -->
    <script>
        window.Laravel = <?php echo json_encode([
            'csrfToken' => csrf_token(),
        ]); ?>
    </script>
    <link href="{{ asset('css/plugins/chosen/bootstrap-chosen.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Toastr style -->
    <link href="{{ asset('/css/plugins/toastr/toastr.min.css') }}" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="{{ asset('css/plugins/dataTables/datatables.min.css') }}" rel="stylesheet">
    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.bootstrap4.min.css">

</head>
<body class="mini-navbar">
    <div id="wrapper">
        <nav class="navbar-default navbar-static-side" role="navigation">
            <div class="sidebar-collapse">
                <ul class="nav metismenu" id="side-menu">
                    <li class="nav-header text-center" style="background-color: #321064;">
                        <div class="dropdown profile-element">
                            <img id="empleadoFoto" alt="Foto del empleado" class="rounded-circle" src="{{ $empleadoImageUrl }}" style="width: 70px"/>
                            <a data-toggle="dropdown" class="dropdown-toggle" href="#">
                                <span class="block m-t-xs font-bold">
                                {{ $empleado->nombre }} {{ $empleado->primer_apellido }} {{ $empleado->segundo_apellido }}
                                </span>
                            </a>
                        </div>
                        <div class="logo-element" style="background-color: #321064;">
                            <a href="{{ url('/') }}">
                                <img src="{{ asset('iso.jpeg') }}" style="width: 50%;">
                            </a>
                        </div>
                    </li>
                     
    
                    <li {{ Request::path() == 'home' ? ' class=active' : '' }}>
                        <a href="{{ url('/home') }}"><i class="bi bi-house-fill"></i> <span class="nav-label">Inicio</span></a>
                    </li>
                    
                    

                    <li>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                                   onclick="event.preventDefault();
                                                 document.getElementById('logout-form').submit();">
                            <i class="bi bi-door-open-fill" aria-hidden="true"></i><span class="nav-label">Salir</span>
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </li>

                </ul>
            </div>
        </nav>
        <div id="page-wrapper" class="gray-bg">
            <div class="row border-bottom">
                <nav class="navbar navbar-static-top" role="navigation">
                    <div class="navbar-header">
                        <div class="row align-items-center justify-content-between">
                            <!-- Información del empleado -->
                            <div class="">
                                <h3 class="">
                                    {{ $empleado->nombre }} {{ $empleado->primer_apellido }} {{ $empleado->segundo_apellido }}
                                    <sup>
                                        <small style="color: red;">{{ Auth::user()->id }}</small>
                                        <input type="hidden" value="{{ Auth::user()->id }}" id="emp_codigo">
                                    </sup>
                                </h3>
                                <h4 class="mb-1"><b>{{ $empleado->sucursal->nombre }}</b></h4>
                                <h5 class="mb-0">{{ $empleado->puesto->nombre }}</h5>
                            </div>
                            <!-- Fecha de ingreso -->
                            <div class="text-end">
                                <div>
                                    <b>FECHA DE INGRESO</b>
                                </div>
                                <h5 class="mb-0 camelcase">
                                    {{ \Carbon\Carbon::parse($empleado->fecha_ingreso)->translatedFormat('d \de F \de Y') }}
                                </h5>
                            </div>
                            <!-- Notificaciones -->
                            <div class=" text-end" style="margin-right: 30px">
                                <div class="dropdown">
                                    <button class="btn dropdown-toggle" style="color: #492b79; 
        background-color: #321064;" type="button" id="dropdownMenu2" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fa-solid fa-bell bell-icon"></i>
                                        <span class="notification-count" id="notificationCount">{{ $cantidadBajoPromedio }}</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenu2">
                                        <h6 class="dropdown-header">CUESTIONARIOS PENDIENTES</h6>
                                        @foreach ($cuestionariosBajoPromedio as $cuestionarioBajoPromedio)
                                            <a class="dropdown-item" href="{{ url('cuestionarios/aplicar-cuestionarios/') }}">
                                                {{ $cuestionarioBajoPromedio->nombre }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </nav>
            </div>
            @yield('content')
            <div class="footer">
                <div>
                    Copyright&copy; {{ date('Y')}} Sistema Integral de Aplicaciones • Tecnologías de información
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="infoCovidModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog info-covid" role="document" style="margin-left: 15px !important;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
               
            </div>
        </div>
    </div>
    <!-- Mainly scripts -->
    <script src="{{ asset('js/jquery-3.1.1.min.js') }}"></script>
    <script src="{{ asset('js/popper.min.js') }}"></script>
    <script src="{{ asset('js/bootstrap.js') }}"></script>
    <script src="{{ asset('js/plugins/metisMenu/jquery.metisMenu.js') }}"></script>
    <!--<script src="{{ asset('js/jquery.min.js') }}"></script>-->
    <script src="{{ asset('js/plugins/slimscroll/jquery.slimscroll.min.js') }}"></script>

    <!-- Custom and plugin javascript -->
    <script src="{{ asset('js/plugins/chosen/chosen.jquery.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Toastr script -->
    <script src="{{ asset('js/plugins/toastr/toastr.min.js') }}"></script>

    <!-- DataTables JS -->
    <script src="{{ asset('js/plugins/dataTables/datatables.min.js') }}"></script>
    <!-- DataTables Buttons extension -->
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.colVis.min.js"></script>

    


    <script src="{{ asset('js/inspinia.js') }}"></script>
    @yield('scripts')
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            let notificationCount = document.getElementById("notificationCount").innerText;
            let bellIcon = document.querySelector(".bell-icon");

            // Si el número de notificaciones es mayor a 0, aplica la animación
            if (parseInt(notificationCount) > 0) {
                bellIcon.classList.add("shake");
            }

            // Forzar la apertura del dropdown si no funciona automáticamente
            $('#dropdownMenu2').dropdown();
        });
        $(document).ready(function () {
            $('#side-menu').metisMenu();
        });

        $(document).ready(function(){
            toastr.options = {
                "closeButton": true,
                "debug": false,
                "progressBar": true,
                "preventDuplicates": false,
                "positionClass": "toast-top-right",
                "onclick": null,
                "showDuration": "15000",
                "hideDuration": "3000",
                "timeOut": "7000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            };

            @if (Session::has('error'))
                toastr.error('{{ Session::get('error') }}', '¡Cuidado!');
            @elseif(Session::has('success'))
                toastr.success('{{ Session::get('success') }}', '¡Hecho!');
            @endif

            // Verificar si el dispositivo es móvil
            if (window.innerWidth > 768) { // Solo ejecutar si no es móvil
                const $body = $('body');
                const $menu = $('.navbar-static-side');

                // Expandir menú al pasar el mouse
                $menu.on('mouseenter', function () {
                    $body.removeClass('mini-navbar');
                    // Ensure notification counts are visible
                    $('.notification-count').css('display', 'inline-block');
                });

                // Minimizar menú al quitar el mouse
                $menu.on('mouseleave', function () {
                    $body.addClass('mini-navbar');
                    // Ensure notification counts are visible in collapsed state
                    $('.notification-count').css('display', 'inline-block');
                });
            } else {
                // Ensure notification counts are visible on mobile
                $('.notification-count').css('display', 'inline-block');
            }
        });

        $(document).ready(function () {
            function actualizarMensajesSinLeer() {
                $.ajax({
                    url: '{{ route("mensajes.sinLeer") }}',
                    method: 'GET',
                    success: function (data) {
                        const totalSinLeer = data.reduce((sum, incidencia) => sum + incidencia.sin_leer, 0);
                        const $contador = $('#mensajesSinLeer');

                        if (totalSinLeer > 0) {
                            $contador.text(totalSinLeer).css('display', 'inline-block');
                        } else {
                            $contador.css('display', 'none');
                        }
                    },
                    error: function (xhr) {
                        console.error('Error al obtener mensajes sin leer:', xhr.responseText);
                    }
                });
            }

            // Llamar la función al cargar la página y cada 30 segundos
            actualizarMensajesSinLeer();
            setInterval(actualizarMensajesSinLeer, 1000);
        });

        $('.incidencia-item').on('click', function () {
            const incidenciaId = $(this).data('id');

            $.ajax({
                url: `/mensajes/${incidenciaId}/marcar-leidos`,
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function () {
                    console.log('Mensajes marcados como leídos.');
                },
                error: function (xhr) {
                    console.error('Error al marcar mensajes como leídos:', xhr.responseText);
                }
            });
        });
    </script>
</body>

