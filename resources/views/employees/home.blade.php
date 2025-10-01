@extends('employees.layouts.main')
@section('styles')
	<style type="text/css">
		#tabs{
			background: transparent;
		    
		}
		#tabs h6.section-title{
		    color: #eee;
		}

		#tabs .nav-tabs .nav-item.show .nav-link, .nav-tabs .nav-link.active {
		    color: white !important;
		    background-color: #492b79;
		    border-color: transparent transparent #492b79;
		    border-bottom: 4px solid !important;
		    font-size: 15px;
		    font-weight: bold;
		}
		#tabs .nav-tabs .nav-link {
		    border: 1px solid #492b79;
		    border-top-left-radius: .25rem;
		    border-top-right-radius: .25rem;
		    /*background-color: #af1922;*/
		    font-size: 15px;
		    color: #492b79;

		}
		#nav-tab > a {
			color: white;
		}
		.tab-content {
			background-color: white;
		}
		.title {
			color: #af1922;
		}
		.form-control {
			border-radius: 5px;
		}
		input[type=number]::-webkit-inner-spin-button, 
		input[type=number]::-webkit-outer-spin-button { 
		  -webkit-appearance: none; 
		  margin: 0; 
		}

		.black {
			font-weight: bold;
		}

		.w100 {
			width: 100% !important;
		}
		.red {
			background-color: #af1922;
		}
		#living_place_type_chosen {
			width: 100% !important;
		}
		.capitalize {
			text-transform: capitalize;
		}
		.btn-green {
			color: white;
			background-color: #198754;
		}
		.img-thumbnail {
			width: 70% !important;
		}
		.addDocument {
			background-color: transparent;
		}
		.show {
			display: block;
		}
		.hide {
			display: none;
		}
		.border-none {
			border: none !important;
		}
		.text-green {
			color: #198754 !important;
		}
        .tarjeta {
            border: 1px solid rgba(0, 0, 0, 0.125);
            border-radius: 10px;
        }

        .border-curved {
            border-radius: 5px;
        }

        .hide {
            /*display: none;*/
            visibility: hidden;
        }

        .show {
            /*display: inline-block;¨*/
            visibility: show;
        }

        .col {
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          align-items: center;
          border: 1px solid #eceeef;
        }
        .img-thumbnail{
            border: none !important;
        }
		.btn div {
			display: block;
			margin-top: 10px;  /* Espacio entre la imagen y el texto */
			font-weight: bold;
			font-size: 16px;
			text-overflow: ellipsis;
			white-space: nowrap;
			overflow: hidden;
			max-width: 100px;  /* Limita el ancho del texto */
		}

		#documentosContainer .btn {
			display: block;
			margin: 10px;
			text-align: center;
			font-size: 16px;
			overflow: hidden;
			max-width: 120px;
		}

		#documentosContainer img {
			width: 100px;
			height: 100px;
			border-radius: 10px;
			margin-bottom: 5px;
		}

		#documentosContainer .btn-success {
			margin-top: 5px;
		}
		
		/* Estilo para las imágenes del carousel */
		.carousel-inner img {
			height: 300px;
			/* object-fit: cover; */
		}

		/* Titulo*/
		.carousel-caption {
		position: absolute;
		bottom: 20px;
		left: 20px;
		right: 20px;
		z-index: 2;
		color: white;
		text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
		}

		.carousel-caption h5 {
		font-size: 24px;
		font-weight: bold;
		}

		.vertical-slider {
			position: relative;
			border: 1px solid #ddd;
			border-radius: 12px;
			background: #f9f9f9;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
			overflow: hidden;
		}

		.swiper-slide {
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 10px;
			height: 140px;
		}

		.card {
			width: 90%;
			border: none;
			border-radius: 10px;
			overflow: hidden;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
			transition: transform 0.2s ease-in-out;
		}

		.card:hover {
			transform: scale(1.05);
			box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
		}

		.card-img-top {
			width: 100%;
			height: 80px;
			object-fit: cover;
			border-bottom: 1px solid #ddd;
		}

		.card-body {
			padding: 10px;
		}

		.card-title {
			font-size: 14px;
			font-weight: bold;
			color: #333;
			margin-bottom: 5px;
		}

		.btn-primary {
			font-size: 12px;
			padding: 5px 10px;
			background-color: #007bff;
			border-color: #007bff;
			border-radius: 20px;
			transition: background-color 0.3s ease-in-out;
		}

		.btn-primary:hover {
			background-color: #0056b3;
		}

		.video-container {
			margin: auto;
			text-align: center;
			border-radius: 100px 10px / 120px;
			overflow: hidden;
			width: fit-content;
		}

		#video {
			display: block;
			margin: 0 auto;
			border-radius: 10px;
			box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.3);
		}

		/* Boton Slider Vertical */
		.toggle-slider {
			padding: 5px 10px;
			font-size: 14px;
			display: flex;
			align-items: center;
			gap: 5px;
			border-radius: 5px;
		}

		.toggle-slider i {
			font-size: 16px;
		}

		/* Ajustes del Slider */
		#sliderContainer {
			transition: all 0.3s ease;
			border-radius: 5px;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
		}

		@media (max-width: 768px) {
			#sliderContainer {
				width: 100%;
				height: auto;
			}

			.swiper-container {
				max-height: 400px;
			}
			img[src="/img/vid.png"]{
				height: 400px;
				width: 300px;
			}

			.carousel-inner img {
			height: 300px;
			object-fit: cover;
			}
		}

		/* Controles del carrusel */
		.carousel-control-prev-icon,
		.carousel-control-next-icon {
			background-color: rgba(0, 0, 0, 0.5);
			width: 50px;
			height: 50px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 24px;
			color: white;
			transition: transform 0.3s ease, background-color 0.3s ease;
		}

		.carousel-control-prev-icon:hover,
		.carousel-control-next-icon:hover {
			background-color: #492b79;
			transform: scale(1.2);
		}

		/* Íconos personalizados para controles */
		.carousel-control-prev-icon::before,
		.carousel-control-next-icon::before {
			content: '';
		}

		.carousel-control-prev-icon {
			content: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns="http://www.w3.org/2000/svg" fill="%23FFFFFF" viewBox="0 0 16 16"%3E%3Cpath fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/%3E%3C/svg%3E');
			background-size: 30px 30px;
		}

		.carousel-control-next-icon {
			content: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns="http://www.w3.org/2000/svg" fill="%23FFFFFF" viewBox="0 0 16 16"%3E%3Cpath fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/%3E%3C/svg%3E');
			background-size: 30px 30px;
		}


  </style>
@endsection
@section('content')

	{{-- Slider --}}
	<div id="carousel" class="carousel slide" data-ride="carousel">
		<!-- Indicadores -->
		<ol class="carousel-indicators" id="carousel-indicators"></ol>

		<!-- Elementos del carrusel -->
		<div class="carousel-inner" id="carousel-inner"></div>

		<!-- Controles -->
		<a class="carousel-control-prev" href="#carousel" role="button" data-slide="prev">
			<span class="carousel-control-prev-icon" aria-hidden="true"></span>
			<span class="sr-only">Previous</span>
		</a>
		<a class="carousel-control-next" href="#carousel" role="button" data-slide="next">
			<span class="carousel-control-next-icon" aria-hidden="true"></span>
			<span class="sr-only">Next</span>
		</a>
	</div>
	<div class="row justify-content-center my-5">
		<!-- Boton para mostrar/ocultar slider -->
		<button class="btn btn-outline-primary btn-sm d-md-none mb-2 toggle-slider" style="margin-top: 20px" data-toggle="collapse" data-target="#sliderContainer" aria-expanded="false" aria-controls="sliderContainer">
			<i class="fas fa-bars"></i> Archivos
		</button>
		<div class="col-12 col-sm-12 col-md-12 col-lg-11 col-xl-12 d-flex">
			<div class="video-container" style="border-radius: 100px 10px / 120px;">
				<!-- Imagen de vista previa -->
				<img  src="/img/vid.png" alt="Vista previa del video" id="video-thumbnail" width="900px" height="500px">
			
				<!-- Video -->
				<video id="video" class="plyr" preload="none" style="display: none;" controls>
					<source src="/vid/videomiguel.webm" type="video/webm">
					Tu navegador no soporta el formato de video.
				</video>
			</div>			
			{{-- Slider Vertical --}}
			<div class="row my-4">
				<div class="col-12">
					<section id="tabs" class="d-flex justify-content-end align-items-start">
			
						<!-- Contenedor del slider vertical -->
						<div id="sliderContainer" class="swiper-container vertical-slider collapse show" style="width: 350px; height: 500px;">
							<div class="swiper-wrapper">
								@foreach ($cuestionarios as $cuestionario)
								<div class="swiper-slide">
									<div class="card">
										<img src="{{ asset('/img/adobe.png') }}" class="card-img-top" alt="{{ $cuestionario->nombre }}" />
										<div class="card-body text-center">
											<h6 class="card-title mb-2">{{ Str::limit($cuestionario->nombre, 15) }}</h6>
											<button class="btn btn-primary btn-sm openModalButton" data-id="{{ $cuestionario->id }}">
												Ver más
											</button>
										</div>
									</div>
								</div>
								@endforeach
							</div>
							<!-- Paginacion -->
							<div class="swiper-pagination"></div>
						</div>
					</section>
				</div>
			</div>
			
			
			
		</div>
	</div>
	<div class="row my-4 text-center">
		<div class="col-12">
			<h4 class="mb-3">Documentos</h4>
			<div class="d-flex flex-wrap justify-content-center" id="documentosContainer">
				<!-- Aquí se cargarán dinámicamente los documentos -->
			</div>
		</div>
	</div>
	<div class="modal fade" id="pdfModal" tabindex="-1" role="dialog" aria-labelledby="pdfModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="pdfModalLabel">Vista previa del documento</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<!-- El iframe mostrará el contenido del documento -->
					<iframe id="pdfViewer" src="" width="100%" height="500px" frameborder="0"></iframe>
				</div>
			</div>
		</div>
	</div>
	{{-- <div class="modal fade" id="videoModal" tabindex="-1" role="dialog" aria-labelledby="videoModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="pdfModalLabel">Video</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<video class="video_modal" id="video" src="/vid/videomiguel.webm" controls> video not disponible</video>
				</div>
			</div>
		</div>
	</div> --}}
@endsection
@section('scripts')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3/dist/plyr.css" />
<script src="https://cdn.jsdelivr.net/npm/plyr@3/dist/plyr.polyfilled.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>

	<script type="text/javascript">
		document.addEventListener("DOMContentLoaded", function() {
			let notificationCount = document.getElementById("notificationCount").innerText;
			let bellIcon = document.querySelector(".bell-icon");

			// Si el número de notificaciones es mayor a 1, aplica la animación
			if (parseInt(notificationCount) > 1) {
				bellIcon.classList.add("shake");
			}

			// Forzar la apertura del dropdown si no funciona automáticamente
			$('#dropdownMenu2').dropdown();
		});


		$(document).ready(function(){
				$('#carousel').carousel({
			interval: 2500, // Tiempo de carousel
			pause: 'hover'
		});
			$('.select_2').select2();
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

			
			
			$.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
			var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
			$('.openModalButton').on('click', function() {
				// Obtén el id del cuestionario desde el atributo data-id del botón
				var cuestionarioId = $(this).data('id');
				
				// Crea la ruta dinámica del PDF basándote en el ID del cuestionario
				var pdfUrl = '/cuestionarios/cuestionario_' + cuestionarioId + '.pdf';
				
				// Actualiza el iframe en el modal con la URL del PDF
				$('#pdfViewer').attr('src', pdfUrl);
				
				// Muestra el modal
				$('#pdfModal').modal('show');
			});

			// $('.openModal2Button').on('click', function() {
			// 	// Muestra el modal
			// 	$('#videoModal').modal('show');
			// });

			// // Detecta cuando el modal se oculta y pausa el video
			// $('#videoModal').on('hidden.bs.modal', function () {
			// 	var video = document.getElementById('video');
			// 	video.pause();
			// });

			

			function changeImg( img ){
				var docImg = document.getElementById(img + 'Img');
				$(docImg).attr('src', '{{ asset('img/fileAdded.webp') }}');
			}

			function changeImgNotAdded( img ){
				var docImg = document.getElementById(img + 'Img');
				console.log(docImg);
				$(docImg).attr('src', '{{ asset('img/addDocument.webp') }}');
			}

			function hideDeleteButton( file ){
				var deleteDoc = document.getElementById( 'destroy' + file );
				$(deleteDoc).removeClass('show');
				$(deleteDoc).addClass('hide');
			}

			function hideShowButton( file ){
				var showDoc = document.getElementById( 'show' + file );
				$(showDoc).removeClass('show');
				$(showDoc).addClass('hide');
			}

			function showDeleteButton( file ){
				var deleteDoc = document.getElementById( 'destroy' + file );
				$(deleteDoc).removeClass('hide');
				$(deleteDoc).addClass('show');
			}

			function showShowButton( file ){
				var showDoc = document.getElementById( 'show' + file );
				$(showDoc).removeClass('hide');
				$(showDoc).addClass('show');
			}

			$('.destroy').click( function() {
				var doc = this.getAttribute('data-document-type');
				$.ajax({
					url: "/documentacion/"+ doc +"/eliminar",
					method: 'DELETE',
					dataType: 'json',
					success: function(response){
						changeImgNotAdded(response);
						hideDeleteButton(response);
						hideShowButton(response);
						toastr.success('Se actualizó correctamente la información.','¡Hecho!');
					},
					error: function(data){
						if( data.status === 422 ) {
				            var errors = $.parseJSON(data.responseText);
				            $.each(errors, function (key, value) {
				                if($.isPlainObject(value)) {
				                    $.each(value, function (key, value) {     
										toastr.error(value,'¡Cuidado!');
				                    });
				                }
			            	});
			            }
					}
				});

			});

		});

		$(document).ready(function () {
			function cargarDocumentos() {
				$.ajax({
					url: "{{ route('documentos.lista') }}", 
					method: "GET",
					success: function (documentos) {
						let documentosHTML = "";

						documentos.forEach((documento) => {
							const extension = documento.ruta.split('.').pop().toLowerCase();
							let imagen = '';
							let viewerUrl = '';

							// Seleccionar la imagen y la URL del visor según la extensión del archivo
							if (extension === 'doc' || extension === 'docx') {
								imagen = "{{ asset('word.png') }}";
								viewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src={{ asset('storage/') }}/${documento.ruta}`;
							} else if (extension === 'pdf') {
								imagen = "{{ asset('/img/adobe.png') }}";
								viewerUrl = `{{ asset('storage/') }}/${documento.ruta}`;
							} else {
								imagen = "{{ asset('default.png') }}";
								viewerUrl = `{{ asset('storage/') }}/${documento.ruta}`;
							}

							documentosHTML += `
								<div class="text-center mx-2 my-2">
									<button type="button" class="btn openModalDocumento" data-url="${viewerUrl}" style="border: none; background: none;" data-toggle="tooltip">
										<img src="${imagen}" alt="${documento.nombre_archivo}" style="width:100px; height:100px;" />
										<div class="tooltip-container" title="${documento.nombre_archivo}">
											${documento.nombre_archivo}
										</div>
									</button>
									<a href="{{ asset('storage/') }}/${documento.ruta}" download="${documento.nombre_archivo}" class="btn btn-success btn-sm mt-2">
										Descargar
									</a>
								</div>
							`;
						});

						$("#documentosContainer").html(documentosHTML);
					},
					error: function () {
						toastr.error("Error al cargar los documentos.");
					},
				});
			}

			cargarDocumentos();

			// Manejar el evento de ver documento
			$(document).on("click", ".openModalDocumento", function () {
				const url = $(this).data("url");

				$("#pdfViewer").attr("src", url);
				$("#pdfModal").modal("show");
			});
		});

		document.addEventListener('DOMContentLoaded', function () {
			fetch('/comunicados/lista')
				.then(response => response.json())
				.then(data => {
					const indicators = document.getElementById('carousel-indicators');
					const inner = document.getElementById('carousel-inner');

					let indicatorsHTML = '';
					let itemsHTML = '';

					data.forEach((comunicado, index) => {
						indicatorsHTML += `
							<li data-target="#carousel" data-slide-to="${index}" ${index === 0 ? 'class="active"' : ''}></li>
						`;

						itemsHTML += `
							<div class="carousel-item ${index === 0 ? 'active' : ''}">
								<div id="svg-container-${index}" class="d-block w-100">
									<!-- SVG dinámico se insertará aquí -->
								</div>
								<a href="/storage/${comunicado.ruta}" download="${comunicado.nombre_archivo}" 
									style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
										background: transparent; z-index: 10;">
								</a>
							</div>
						`;
					});

					indicators.innerHTML = indicatorsHTML;
					inner.innerHTML = itemsHTML;

					// Procesar imagen segun el tipo
					data.forEach((comunicado, index) => {
						const svgPath = comunicado.tipo === "comunicado" ? '/img/Comunicados2.svg' : '/img/Campaña.svg';

						fetch(svgPath)
							.then(response => response.text())
							.then(svgContent => {
								const container = document.getElementById(`svg-container-${index}`);
								container.innerHTML = svgContent;

								// Inserta el texto
								const dynamicTextElement = container.querySelector('#dynamic-text');
								if (dynamicTextElement) {
									dynamicTextElement.textContent = comunicado.titulo;

									// Ajustar el tamaño de fuente dinámicamente
									const baseFontSize = 60; // Tamaño fijo para titulos cortos
									const minFontSize = 20; // Tamaño minimo
									const maxLength = 60; // Tamaño máxima

									let adjustedFontSize;
									if (comunicado.titulo.length <= 34) {
										// Mantener tamaño fijo si tiene 34 caracteres
										adjustedFontSize = baseFontSize;
									} else {
										// Reducir tamaño proporcionalmente
										adjustedFontSize = baseFontSize * (34 / comunicado.titulo.length);
										// Asegurar que el tamaño no sea menor que el minimo
										adjustedFontSize = Math.max(adjustedFontSize, minFontSize);
									}

									// Aplicar el tamaño
									dynamicTextElement.setAttribute('font-size', adjustedFontSize);
								}
							})
							.catch(error => console.error('Error al cargar el SVG:', error));
					});
				})
				.catch(error => console.error('Error al obtener los comunicados:', error));
		});



	const swiper = new Swiper('.vertical-slider', {
			direction: 'vertical', 
			slidesPerView: 3,
			spaceBetween: 10,
			pagination: {
				el: '.swiper-pagination',
				clickable: true,
			},
			mousewheel: true,
		});

	document.addEventListener('DOMContentLoaded', () => {
		const player = new Plyr('#video', {
			controls: [
				'play-large',
				'play',
				'progress',
				'current-time',
				'mute',
				'volume',
				'settings',
				'fullscreen',
			],
			settings: ['captions', 'quality', 'speed'],
			quality: { default: 1080, options: [720, 1080] },
		});
	});

	const thumbnail = document.getElementById('video-thumbnail');
    const video = document.getElementById('video');

    thumbnail.addEventListener('click', function() {
        // Oculta la imagen de vista previa
        thumbnail.style.display = 'none';

        // Muestra el video
        video.style.display = 'block';

        // Reproduce el video automaticamente
        video.play();
    });
	
	</script>

@endsection