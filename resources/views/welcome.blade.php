<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDFina - Tu PDF, tu control</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @if (app()->environment('local'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        @php
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        @endphp

        @if (isset($manifest['resources/css/app.css']))
        <link rel="stylesheet" href="{{ asset('build/' . $manifest['resources/css/app.css']['file']) }}">
        @endif

        @if (isset($manifest['resources/js/app.js']))
        <script src="{{ asset('build/' . $manifest['resources/js/app.js']['file']) }}" defer></script>
        @endif
    @endif
</head>
<body class="bg-gradient-to-br from-black via-gray-950 to-black text-white flex flex-col font-sans">
    <!-- Header -->
    <header class="w-full max-w-6xl mx-auto px-6 pt-8 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <img src="/images/png/nina.png" alt="PDFina Logo" class="w-10 h-10 rounded-lg shadow-lg bg-gray-800 drop-shadow-[0_0_2px_rgba(255,255,255,0.7)]" />
            <span class="text-3xl font-extrabold tracking-tight text-red-500">PDFina</span>
        </div>
        @if (Route::has('login'))
            <nav class="flex items-center gap-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="px-4 py-2 rounded-lg bg-gray-800 hover:bg-red-500 hover:text-white transition-colors border border-gray-700 text-gray-200 font-medium shadow-lg">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-gray-800 hover:bg-red-500 hover:text-white transition-colors border border-gray-700 text-gray-200 font-medium shadow-lg">Iniciar sesión</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg bg-gray-800 hover:bg-red-500 hover:text-white transition-colors border border-gray-700 text-gray-200 font-medium shadow-lg">Registrarse</a>
                    @endif
                @endauth
            </nav>
        @endif
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col items-center w-full max-w-6xl mx-auto px-6 py-8">

        <!-- Hero Section -->
        <section class="w-full flex flex-col lg:flex-row items-center justify-between gap-12 mt-8 mb-16">
            <div class="flex-1 flex flex-col items-start justify-center max-w-2xl">
                <div class="mb-4">
                    <span class="inline-block px-4 py-2 bg-red-500/20 text-red-400 rounded-full text-sm font-semibold border border-red-500/30">
                        🚀 100% Gratuito
                    </span>
                </div>
                <h1 class="text-4xl lg:text-6xl font-extrabold mb-6 text-white leading-tight">
                    Domina tus PDF en
                    <span class="text-red-500 drop-shadow-lg">segundos</span>
                </h1>
                <h2 class="text-xl lg:text-2xl font-medium mb-8 text-gray-300 leading-relaxed">
                    PDFina es la herramienta definitiva para trabajar con archivos PDF. <span class="text-red-400 font-semibold">Sin internet, sin límites, sin esperas.</span>
                </h2>

                <!-- Benefit List -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8 w-full">
                    <div class="flex items-center gap-3 bg-gray-800/50 rounded-lg p-4 border border-gray-700">
                        <img src="/images/png/carpetina.png" class="w-10 h-10 drop-shadow-[0_0_2px_rgba(255,255,255,0.7)]" alt="Privacidad" />
                        <div>
                            <h3 class="font-bold text-white">100% Privado</h3>
                            <p class="text-gray-400 text-sm">Tus archivos nunca salen de tu PC</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-gray-800/50 rounded-lg p-4 border border-gray-700">
                        <img src="/images/png/boxina.png" class="w-10 h-10 drop-shadow-[0_0_2px_rgba(255,255,255,0.7)]" alt="Velocidad" />
                        <div>
                            <h3 class="font-bold text-white">Súper veloz</h3>
                            <p class="text-gray-400 text-sm">Procesamiento 5 veces más rápido</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-gray-800/50 rounded-lg p-4 border border-gray-700">
                        <img src="/images/png/nina2.png" class="w-10 h-10 drop-shadow-[0_0_2px_rgba(255,255,255,0.7)]" alt="Facilidad" />
                        <div>
                            <h3 class="font-bold text-white">Fácil de Usar</h3>
                            <p class="text-gray-400 text-sm">Elije un módulo, sube tu archivo y listo</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-gray-800/50 rounded-lg p-4 border border-gray-700">
                        <img src="/images/png/descargina.png" class="w-10 h-12 drop-shadow-[0_0_2px_rgba(255,255,255,0.7)]" alt="Gratis" />
                        <div>
                            <h3 class="font-bold text-white">Sin Límites</h3>
                            <p class="text-gray-400 text-sm">Sin marcas de agua, sin pagos</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hero Image -->
            <div class="flex-1 flex flex-col items-center justify-center max-w-lg">
                <div class="relative">
                    <div class="relative">
                        <img src="/images/png/pdfina.png" alt="Vista previa PDFina" class="w-full rounded-2xl shadow-2xl bg-gray-800 drop-shadow-[0_0_4px_rgba(255,255,255,0.6)]" />
                        <div class="absolute inset-0 bg-gray-900/30 rounded-2xl"></div>
                    </div>
                    <div class="absolute -top-4 -right-4 bg-red-500 text-white px-3 py-1 rounded-full text-sm font-bold animate-pulse">
                        Versión 0.7
                    </div>
                </div>
            </div>
        </section>

        <!-- Value Propositions -->
        <section class="w-full mb-20">
            <h2 class="text-3xl font-bold text-center mb-12 text-white">¿Por qué elegir PDFina?</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-8 text-center shadow-xl border border-gray-700 hover:border-red-500/50 transition-colors group">
                    <div class="mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-18 h-18 mx-auto mb-4 group-hover:scale-110 transition-transform text-gray-50">
                            <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                        </svg>
                        <h3 class="text-2xl font-bold mb-4 text-red-400">Seguridad Total</h3>
                    </div>
                    <p class="text-gray-300 leading-relaxed">Tus documentos nunca abandonan tu equipo. Procesa contratos, facturas y archivos sensibles con total tranquilidad.</p>
                </div>

                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-8 text-center shadow-xl border border-gray-700 hover:border-red-500/50 transition-colors group">
                    <div class="mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-18 h-18 mx-auto mb-4 group-hover:scale-110 transition-transform text-gray-50">
                            <path fill-rule="evenodd" d="M14.615 1.595a.75.75 0 0 1 .359.852L12.982 9.75h7.268a.75.75 0 0 1 .548 1.262l-10.5 11.25a.75.75 0 0 1-1.272-.71l1.992-7.302H3.75a.75.75 0 0 1-.548-1.262l10.5-11.25a.75.75 0 0 1 .913-.143Z" clip-rule="evenodd" />
                        </svg>
                        <h3 class="text-2xl font-bold mb-4 text-red-400">Ahorra Horas</h3>
                    </div>
                    <p class="text-gray-300 leading-relaxed">Olvídate de esperas interminables y subidas lentas. PDFina procesa tus archivos al instante, sin conexión a internet.</p>
                </div>

                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-8 text-center shadow-xl border border-gray-700 hover:border-red-500/50 transition-colors group">
                    <div class="mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-18 h-18 mx-auto mb-4 group-hover:scale-110 transition-transform text-gray-50">
                            <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />
                        </svg>
                        <h3 class="text-2xl font-bold mb-4 text-red-400">Súper Simple</h3>
                    </div>
                    <p class="text-gray-300 leading-relaxed">Interfaz tan intuitiva que cualquier persona puede usarla. Ya nos encargamos de las configuraciónes, vos disfrutalas.</p>
                </div>
            </div>
        </section>

        <!-- How it Works -->
        <section class="w-full mb-20">
            <h2 class="text-3xl font-bold text-center mb-4 text-white">Así de fácil funciona</h2>
            <p class="text-gray-400 text-center mb-12 text-lg">En solo 3 pasos tienes tu PDF listo</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="flex flex-col items-center text-center">
                    <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-full w-24 h-24 flex items-center justify-center mb-6 shadow-xl">
                        <span class="text-4xl font-bold text-white">1</span>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-white">Abre PDFina</h3>
                    <p class="text-gray-400 leading-relaxed">Elige la herramienta que necesitas: unir, dividir, comprimir, convertir...</p>
                </div>
                <div class="flex flex-col items-center text-center">
                    <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-full w-24 h-24 flex items-center justify-center mb-6 shadow-xl">
                        <span class="text-4xl font-bold text-white">2</span>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-white">Sube tu archivo</h3>
                    <p class="text-gray-400 leading-relaxed">Espera unos segundos a que este se cargue</p>
                </div>
                <div class="flex flex-col items-center text-center">
                    <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-full w-24 h-24 flex items-center justify-center mb-6 shadow-xl">
                        <span class="text-4xl font-bold text-white">3</span>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-white">¡Haz clic en enviar al escritorio!</h3>
                    <p class="text-gray-400 leading-relaxed">Antes de que parpadees lo tendrás listo</p>
                </div>
            </div>
        </section>

        <!-- Social Proof -->
        <section class="w-full mb-20">
            <h2 class="text-3xl font-bold text-center mb-12 text-white">Lo que dicen nuestros usuarios</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-gray-800/50 rounded-xl p-6 shadow-lg border border-gray-700">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center text-white font-bold text-lg">A</div>
                        <div class="ml-3">
                            <h4 class="font-bold text-white">Ana Rodríguez</h4>
                            <p class="text-gray-400 text-sm">Abogada</p>
                        </div>
                    </div>
                    <p class="text-gray-300 italic">"Por fin una herramienta que respeta mi privacidad. Manejo documentos legales confidenciales y PDFina me da la tranquilidad que necesito."</p>
                    <div class="flex text-yellow-400 mt-3">
                        ⭐⭐⭐⭐⭐
                    </div>
                </div>

                <div class="bg-gray-800/50 rounded-xl p-6 shadow-lg border border-gray-700">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold text-lg">L</div>
                        <div class="ml-3">
                            <h4 class="font-bold text-white">Lucas Martín</h4>
                            <p class="text-gray-400 text-sm">Estudiante universitario</p>
                        </div>
                    </div>
                    <p class="text-gray-300 italic">"PDFina me ahorra horas cada semana uniendo mis apuntes. La velocidad es increíble y nunca tengo que esperar."</p>
                    <div class="flex text-yellow-400 mt-3">
                        ⭐⭐⭐⭐⭐
                    </div>
                </div>

                <div class="bg-gray-800/50 rounded-xl p-6 shadow-lg border border-gray-700">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white font-bold text-lg">D</div>
                        <div class="ml-3">
                            <h4 class="font-bold text-white">Diego Fernández</h4>
                            <p class="text-gray-400 text-sm">Estudiante de medicina</p>
                        </div>
                    </div>
                    <p class="text-gray-300 italic">"Necesitaba convertir mis PDFs a Word para estudiar mejor. PDFina lo hizo al instante y sin esas marcas de agua molestas que tenían otros conversores."</p>
                    <div class="flex text-yellow-400 mt-3">
                        ⭐⭐⭐⭐⭐
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA -->
        <section id="descargar" class="w-full text-center mb-16">
            <div class="bg-gradient-to-r from-red-500/20 to-red-600/20 rounded-3xl p-12 border border-red-500/30">
                <h2 class="text-4xl font-extrabold mb-6 text-white">¡No pierdas más tiempo!</h2>
                <p class="text-xl text-gray-300 mb-8 max-w-2xl mx-auto">Únete a miles de usuarios que ya disfrutan de la seguridad de PDFina</p>
                <a href="#" class="inline-block px-12 py-6 rounded-2xl bg-red-500 text-white font-bold shadow-2xl hover:bg-red-600 transition-colors text-2xl hover:scale-105 transform duration-200">
                    Registrate
                </a>
                <div class="flex justify-center items-center gap-8 mt-8 text-gray-400">
                    <span class="flex items-center gap-2">
                        ✅ Sin pagos
                    </span>
                    <span class="flex items-center gap-2">
                        ✅ Sin límites
                    </span>
                    <span class="flex items-center gap-2">
                        ✅ Sin sorpresas
                    </span>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="w-full max-w-6xl mx-auto px-6 pb-8 flex flex-col md:flex-row justify-between items-center text-gray-400 text-sm border-t border-gray-700 pt-8">
        <span>PDFina &copy; {{ date('Y') }}. Hecho con <span class="text-red-500">❤️</span> en Argentina por Matias Lawwliet.</span>
        <span>Desarrollado con <a href="https://laravel.com/" target="_blank" class="underline underline-offset-4 text-red-400 hover:text-red-300">Laravel</a> + <a href="https://nativephp.com/" target="_blank" class="underline underline-offset-4 text-red-400 hover:text-red-300">NativePHP</a></span>
    </footer>
</body>
</html>
