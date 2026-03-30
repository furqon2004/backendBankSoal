<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bank Soal REST API</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <!-- Tailwind CSS (via CDN for simplicity on the landing page) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a; /* Slate 900 */
            color: #f8fafc; /* Slate 50 */
        }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7); /* Slate 800 with opacity */
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .gradient-text {
            background: linear-gradient(135deg, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .pulse-blob {
            position: absolute;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.5;
            animation: pulse-slow 8s infinite alternate;
        }
        @keyframes pulse-slow {
            0% { transform: scale(1); opacity: 0.3; }
            100% { transform: scale(1.2); opacity: 0.6; }
        }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col justify-center items-center relative overflow-hidden">
    
    <!-- Decorative Background Elements -->
    <div class="pulse-blob bg-sky-600 rounded-full w-96 h-96 top-[-10%] left-[-10%]"></div>
    <div class="pulse-blob bg-indigo-600 rounded-full w-96 h-96 bottom-[-10%] right-[-10%]" style="animation-delay: 2s;"></div>

    <main class="w-full max-w-3xl px-6 relative z-10">
        <div class="glass-panel p-10 md:p-16 rounded-3xl shadow-2xl text-center">
            
            <div class="mb-6 flex justify-center">
                <div class="w-16 h-16 bg-gradient-to-br from-sky-400 to-indigo-500 rounded-2xl flex items-center justify-center shadow-lg transform rotate-3 hover:rotate-6 transition-transform duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                </div>
            </div>

            <h1 class="text-4xl md:text-5xl font-bold mb-4 tracking-tight">
                <span class="gradient-text">Bank Soal</span> REST API
            </h1>
            
            <p class="text-slate-300 text-lg md:text-xl mb-10 max-w-2xl mx-auto leading-relaxed">
                Core backend service powered by Laravel 12 & Google Gemini AI.
                Provides endpoints for user management, dynamic material creation, AI question generation, and quiz tracking.
            </p>

            <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                <!-- API Docs Button -->
                <a href="/docs/api" 
                   class="group relative inline-flex items-center justify-center px-8 py-4 font-semibold text-white transition-all duration-200 bg-indigo-600 border border-transparent rounded-full hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 shadow-[0_0_20px_rgba(79,70,229,0.4)] hover:shadow-[0_0_30px_rgba(79,70,229,0.6)] hover:-translate-y-1 w-full sm:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2 group-hover:animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Lihat Dokumentasi API
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-2 transition-transform duration-200 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            </div>

            <div class="mt-12 pt-8 border-t border-slate-700/50 flex justify-center space-x-6 text-sm text-slate-400">
                <div class="flex items-center">
                    <span class="w-2 h-2 rounded-full bg-green-400 mr-2 animate-pulse"></span>
                    System Online
                </div>
                <div>v1.0.0</div>
            </div>
        </div>
    </main>
</body>
</html>
