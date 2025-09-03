<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Chipper Farms - Premium Pig Breeding Management</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        <style>
            :root {
                --chipper-green: #1C6C51;
                --chipper-light-green: #E6F4EF;
                --chipper-dark-green: #155A43;
                --chipper-accent: #2D8A65;
            }

            .chipper-green { color: var(--chipper-green); }
            .bg-chipper-green { background-color: var(--chipper-green); }
            .border-chipper-green { border-color: var(--chipper-green); }
            .hover\:bg-chipper-green:hover { background-color: var(--chipper-green); }
            .hover\:text-chipper-green:hover { color: var(--chipper-green); }

            /* Enhanced animations and effects */
            .fade-in { animation: fadeIn 0.8s ease-in-out; }
            .slide-up { animation: slideUp 0.6s ease-out; }
            .float { animation: float 3s ease-in-out infinite; }
            .gradient-text {
                background: linear-gradient(135deg, var(--chipper-green), var(--chipper-accent));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            @keyframes slideUp {
                from { opacity: 0; transform: translateY(40px); }
                to { opacity: 1; transform: translateY(0); }
            }

            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-10px); }
            }

            .card-hover {
                transition: all 0.3s ease;
                transform: translateY(0);
            }

            .card-hover:hover {
                transform: translateY(-8px);
                box-shadow: 0 20px 40px rgba(28, 108, 81, 0.15);
            }

            .hero-bg {
                background: linear-gradient(135deg, #f6fefb 0%, var(--chipper-light-green) 50%, #ffffff 100%);
                position: relative;
                overflow: hidden;
            }

            .hero-bg::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%231C6C51' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
                z-index: 0;
            }

            .section-divider {
                height: 1px;
                background: linear-gradient(90deg, transparent, var(--chipper-green), transparent);
                margin: 2rem 0;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--chipper-green), var(--chipper-accent));
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }

            .btn-primary::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                transition: left 0.5s;
            }

            .btn-primary:hover::before {
                left: 100%;
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(28, 108, 81, 0.3);
            }
        </style>
    </head>
<body class="font-sans antialiased bg-gradient-to-br from-green-50 to-white min-h-screen")
    <!-- Enhanced Navbar -->
    <nav class="bg-white/95 backdrop-blur-sm shadow-lg border-b border-green-100 sticky top-0 z-50 transition-all duration-300">
        <div class="max-w-6xl mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <a href="/" class="flex items-center gap-3 group">
                        <div class="relative">
                            <img src="/images/chipper-farms-logo.jpg" alt="Chipper Farms Logo" class="h-10 w-auto rounded-lg shadow-sm group-hover:shadow-md transition-shadow duration-300" />
                            <div class="absolute inset-0 bg-gradient-to-r from-green-400/20 to-green-600/20 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </div>
                        <!-- <div class="hidden md:block">
                            <h1 class="text-xl font-bold gradient-text">Chipper Farms</h1>
                            <p class="text-xs text-gray-600">Premium Pig Breeding</p>
                        </div> -->
                    </a>
                </div>

                <ul class="hidden md:flex gap-8 font-medium">
                    <li><a href="#home" class="relative chipper-green hover:text-green-700 transition-colors duration-300 py-2 group">
                        Home
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-green-500 to-green-600 group-hover:w-full transition-all duration-300"></span>
                    </a></li>
                    <li><a href="#about" class="relative chipper-green hover:text-green-700 transition-colors duration-300 py-2 group">
                        About
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-green-500 to-green-600 group-hover:w-full transition-all duration-300"></span>
                    </a></li>
                    <li><a href="#features" class="relative chipper-green hover:text-green-700 transition-colors duration-300 py-2 group">
                        Features
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-green-500 to-green-600 group-hover:w-full transition-all duration-300"></span>
                    </a></li>
                    <li><a href="#contact" class="relative chipper-green hover:text-green-700 transition-colors duration-300 py-2 group">
                        Contact
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-green-500 to-green-600 group-hover:w-full transition-all duration-300"></span>
                    </a></li>
                </ul>

                <div class="flex items-center gap-4">
                    <a href="{{ route('login') }}" class="btn-primary px-5 py-2 rounded-lg font-medium text-white shadow-lg hover:shadow-xl transition-all duration-300 relative overflow-hidden text-sm">
                        <span class="relative z-10">Sign In</span>
                    </a>

                    <!-- Mobile menu button -->
                    <button class="md:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors duration-300">
                        <svg class="w-6 h-6 chipper-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Enhanced Hero Section -->
    <section id="home" class="hero-bg py-20 flex items-center justify-center relative">
        <div class="max-w-6xl mx-auto px-6 py-12 text-center relative z-10">
            <div class="fade-in">
                <!-- Hero Badge -->
                <div class="inline-flex items-center gap-2 bg-white/80 backdrop-blur-sm px-4 py-2 rounded-full shadow-lg mb-6 border border-green-100">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-sm font-medium chipper-green">Professional Pig Breeding Management</span>
                </div>

                <!-- Main Heading -->
                <h1 class="text-4xl md:text-5xl font-extrabold mb-4 leading-tight">
                    <span class="gradient-text">Chipper Farms</span>
                    <br>
                    <span class="text-gray-700 text-2xl md:text-3xl font-semibold">Breeding Excellence</span>
                </h1>

                <!-- Subheading -->
                <p class="text-lg md:text-xl mb-8 max-w-3xl mx-auto text-gray-600 leading-relaxed">
                    Advanced pig breeding management system with genetic tracking,
                    <span class="chipper-green font-semibold">automated breeding cycles</span>,
                    and comprehensive health monitoring for optimal farm productivity.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 justify-center items-center mb-8">
                    <!-- <a href="{{ route('login') }}" class="btn-primary px-6 py-3 rounded-lg font-semibold text-white shadow-lg text-base group relative overflow-hidden">
                        <span class="relative z-10 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            Access Dashboard
                        </span>
                    </a> -->
                    <a href="#features" class="px-6 py-3 rounded-lg font-semibold text-green-700 bg-white/80 backdrop-blur-sm border-2 border-green-200 hover:border-green-300 shadow-lg hover:shadow-xl transition-all duration-300 text-base group">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Learn More
                        </span>
                    </a>
                </div>

                <!-- Feature Highlights -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 max-w-2xl mx-auto">
                    <div class="bg-white/60 backdrop-blur-sm rounded-lg p-3 shadow-lg border border-green-100 card-hover">
                        <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-green-600 rounded-lg flex items-center justify-center mb-2 mx-auto">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-800 mb-1 text-xs">Breeding Analytics</h3>
                        <p class="text-gray-600 text-xs leading-tight">Advanced genetic tracking and performance metrics</p>
                    </div>

                    <div class="bg-white/60 backdrop-blur-sm rounded-lg p-3 shadow-lg border border-green-100 card-hover">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center mb-2 mx-auto">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-800 mb-1 text-xs">Automated Cycles</h3>
                        <p class="text-gray-600 text-xs leading-tight">Smart breeding cycle management with reminders</p>
                    </div>

                    <div class="bg-white/60 backdrop-blur-sm rounded-lg p-3 shadow-lg border border-green-100 card-hover">
                        <div class="w-8 h-8 bg-gradient-to-br from-purple-400 to-purple-600 rounded-lg flex items-center justify-center mb-2 mx-auto">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-800 mb-1 text-xs">Health Monitoring</h3>
                        <p class="text-gray-600 text-xs leading-tight">Comprehensive health tracking and pregnancy management</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating Elements -->
        <div class="absolute top-20 left-10 w-20 h-20 bg-green-200/30 rounded-full blur-xl float"></div>
        <div class="absolute bottom-20 right-10 w-32 h-32 bg-blue-200/20 rounded-full blur-2xl float" style="animation-delay: -1s;"></div>
        <div class="absolute top-1/2 right-20 w-16 h-16 bg-purple-200/25 rounded-full blur-lg float" style="animation-delay: -2s;"></div>
    </section>

    <!-- Enhanced About Section -->
    <section id="about" class="py-16 px-6 bg-gradient-to-br from-white to-green-50 relative overflow-hidden">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-16 slide-up">
                <div class="inline-flex items-center gap-2 bg-green-100 px-4 py-2 rounded-full mb-6">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium text-green-700">About Our System</span>
                </div>
                <h2 class="text-3xl md:text-4xl font-bold mb-6 gradient-text">Professional Breeding Management</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto leading-relaxed">
                    Chipper Farms represents the next generation of pig breeding management,
                    combining traditional farming wisdom with cutting-edge technology for optimal results.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Content -->
                <div class="space-y-8 slide-up">
                    <div class="space-y-6">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 mb-2">Genetic Excellence</h3>
                                <p class="text-gray-600 text-sm">Advanced genetic tracking prevents inbreeding and optimizes breeding pairs for superior offspring quality and health.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 mb-2">Automated Scheduling</h3>
                                <p class="text-gray-600 text-sm">Smart breeding cycle management with automated reminders for mating, pregnancy checks, and farrowing dates.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-purple-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 mb-2">Performance Analytics</h3>
                                <p class="text-gray-600 text-sm">Comprehensive reporting and analytics to track breeding success rates, health metrics, and farm productivity.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-2xl p-6 border border-green-100">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <h4 class="font-bold text-gray-800">Proven Results</h4>
                        </div>
                        <p class="text-gray-600 text-sm">
                            Our breeding management system has helped farms increase productivity by up to 35%
                            while maintaining the highest standards of animal welfare and genetic diversity.
                        </p>
                    </div>
                </div>

                <!-- Visual Element -->
                <div class="relative slide-up">
                    <div class="bg-gradient-to-br from-green-100 to-blue-100 rounded-3xl p-8 shadow-2xl">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="bg-white rounded-2xl p-6 text-center shadow-lg card-hover">
                                <div class="text-3xl font-bold text-green-600 mb-2">98%</div>
                                <div class="text-sm text-gray-600">Breeding Success Rate</div>
                            </div>
                            <div class="bg-white rounded-2xl p-6 text-center shadow-lg card-hover">
                                <div class="text-3xl font-bold text-blue-600 mb-2">24/7</div>
                                <div class="text-sm text-gray-600">Health Monitoring</div>
                            </div>
                            <div class="bg-white rounded-2xl p-6 text-center shadow-lg card-hover">
                                <div class="text-3xl font-bold text-purple-600 mb-2">100%</div>
                                <div class="text-sm text-gray-600">Genetic Tracking</div>
                            </div>
                            <div class="bg-white rounded-2xl p-6 text-center shadow-lg card-hover">
                                <div class="text-3xl font-bold text-orange-600 mb-2">35%</div>
                                <div class="text-sm text-gray-600">Productivity Increase</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Background decoration -->
        <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-green-200/20 to-blue-200/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-gradient-to-tr from-purple-200/20 to-pink-200/20 rounded-full blur-2xl"></div>
    </section>

    <!-- Our Pigs Section -->
    <section id="features" class="py-16 px-6 bg-white relative overflow-hidden">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-16 slide-up">
                <div class="inline-flex items-center gap-2 bg-blue-100 px-4 py-2 rounded-full mb-6">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <span class="text-sm font-medium text-blue-700">System Features</span>
                </div>
                <h2 class="text-3xl md:text-4xl font-bold mb-6 gradient-text">Comprehensive Breeding Management</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto leading-relaxed">
                    Everything you need to manage a successful pig breeding operation,
                    from genetic tracking to automated scheduling and performance analytics.
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white rounded-lg shadow p-6 flex flex-col items-center">
                    <img src="https://cdn.pixabay.com/photo/2016/11/29/09/32/animal-1867127_1280.jpg" alt="Pig 1" class="w-28 h-28 rounded-full mb-4 object-cover" />
                    <h3 class="text-xl font-semibold mb-2" style="color: #1C6C51;">Rosie</h3>
                    <p style="color: #1C6C51;">Rosie loves mud baths and fresh apples. She’s the friendliest pig on the farm!</p>
                                        </div>
                <div class="bg-white rounded-lg shadow p-6 flex flex-col items-center">
                    <img src="https://cdn.pixabay.com/photo/2017/01/31/13/14/animal-2028258_1280.png" alt="Pig 2" class="w-28 h-28 rounded-full mb-4 object-cover" />
                    <h3 class="text-xl font-semibold mb-2" style="color: #1C6C51;">Benny</h3>
                    <p style="color: #1C6C51;">Benny is curious and playful, always exploring and making new friends.</p>
                                    </div>
                <div class="bg-white rounded-lg shadow p-6 flex flex-col items-center">
                    <img src="https://cdn.pixabay.com/photo/2017/06/20/19/22/piglet-2425206_1280.jpg" alt="Pig 3" class="w-28 h-28 rounded-full mb-4 object-cover" />
                    <h3 class="text-xl font-semibold mb-2" style="color: #1C6C51;">Lulu</h3>
                    <p style="color: #1C6C51;">Lulu is the youngest, always running around and making everyone smile.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Contact Section -->
    <section id="contact" class="py-16 px-6 bg-gradient-to-br from-green-50 to-blue-50 relative overflow-hidden">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-12 slide-up">
                <div class="inline-flex items-center gap-2 bg-green-100 px-4 py-2 rounded-full mb-6">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <span class="text-sm font-medium text-green-700">Get In Touch</span>
                </div>
                <h2 class="text-3xl md:text-4xl font-bold mb-6 gradient-text">Ready to Get Started?</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto leading-relaxed">
                    Transform your pig breeding operation with our comprehensive management system.
                    Contact us today to learn more or schedule a demo.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <!-- Contact Info -->
                <div class="space-y-6 slide-up">
                    <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-green-100">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-green-600 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">Email Support</h3>
                                <p class="text-gray-600">support@chipperfarms.com</p>
                            </div>
                        </div>
                        <!-- <a href="mailto:support@chipperfarms.com" class="text-green-600 hover:text-green-700 font-medium">
                            support@chipperfarms.com
                        </a> -->
                    </div>

                    <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-blue-100">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">Phone Support</h3>
                                <p class="text-gray-600">(555) 123-4567</p>
                            </div>
                        </div>
                        <!-- <a href="tel:+15551234567" class="text-blue-600 hover:text-blue-700 font-medium">
                            (555) 123-4567
                        </a> -->
                    </div>

                    <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-purple-100">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">Visit Us</h3>
                                <p class="text-gray-600">Farm location</p>
                            </div>
                        </div>
                        <!-- <p class="text-purple-600 font-medium">
                            123 Farm Lane<br>
                            Countryside, USA 12345
                        </p> -->
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="bg-white/60 backdrop-blur-sm rounded-3xl p-8 shadow-2xl border border-green-100 slide-up">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-gradient-to-br from-green-400 to-blue-500 rounded-2xl flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Start Your Journey</h3>
                        <p class="text-gray-600 mb-6 text-sm">
                            Join hundreds of successful pig farmers who trust Chipper Farms
                            for their breeding management needs.
                        </p>
                        <div class="space-y-3">
                            <!-- <a href="{{ route('login') }}" class="btn-primary w-full px-5 py-2.5 rounded-lg font-semibold text-white shadow-lg text-base inline-block">
                                Access Dashboard
                            </a> -->
                            <a href="mailto:demo@chipperfarms.com" class="w-full px-5 py-2.5 rounded-lg font-semibold text-green-700 bg-green-50 border-2 border-green-200 hover:border-green-300 shadow-lg hover:shadow-xl transition-all duration-300 text-base inline-block">
                                Request Demo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Background decoration -->
        <div class="absolute top-10 right-10 w-32 h-32 bg-gradient-to-br from-green-200/20 to-blue-200/20 rounded-full blur-2xl"></div>
        <div class="absolute bottom-10 left-10 w-24 h-24 bg-gradient-to-br from-purple-200/20 to-pink-200/20 rounded-full blur-xl"></div>
    </section>

    <!-- Enhanced Footer -->
    <footer class="bg-gradient-to-r from-green-800 to-green-900 text-white relative overflow-hidden">
        <div class="max-w-6xl mx-auto px-6 py-10">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div class="md:col-span-2">
                    <div class="flex items-center gap-3 mb-4">
                        <img src="/images/chipper-farms-logo.jpg" alt="Chipper Farms Logo" class="h-10 w-auto rounded-lg" />
                        <!-- <div>
                            <h3 class="text-xl font-bold">Chipper Farms</h3>
                            <p class="text-green-200 text-sm">Premium Pig Breeding Management</p>
                        </div> -->
                    </div>
                    <p class="text-green-100 mb-4 max-w-md">
                        Leading the future of pig breeding with advanced genetic tracking,
                        automated breeding cycles, and comprehensive farm management solutions.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 bg-green-700 hover:bg-green-600 rounded-lg flex items-center justify-center transition-colors duration-300">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-green-700 hover:bg-green-600 rounded-lg flex items-center justify-center transition-colors duration-300">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 0 1-1.93.07 4.28 4.28 0 0 0 4 2.98 8.521 8.521 0 0 1-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-green-700 hover:bg-green-600 rounded-lg flex items-center justify-center transition-colors duration-300">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.357-.629-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24.009c6.624 0 11.99-5.367 11.99-11.988C24.007 5.367 18.641.001.012.001z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="#home" class="text-green-200 hover:text-white transition-colors duration-300">Home</a></li>
                        <li><a href="#about" class="text-green-200 hover:text-white transition-colors duration-300">About</a></li>
                        <li><a href="#features" class="text-green-200 hover:text-white transition-colors duration-300">Features</a></li>
                        <li><a href="#contact" class="text-green-200 hover:text-white transition-colors duration-300">Contact</a></li>
                        <li><a href="{{ route('login') }}" class="text-green-200 hover:text-white transition-colors duration-300">Login</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Contact Info</h4>
                    <ul class="space-y-2 text-green-200">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span class="text-sm">123 Farm Lane, Countryside, USA</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <span class="text-sm">(555) 123-4567</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span class="text-sm">support@chipperfarms.com</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="section-divider"></div>

            <!-- Bottom Footer -->
            <div class="flex flex-col md:flex-row justify-between items-center pt-8">
                <div class="text-green-200 text-sm mb-4 md:mb-0">
                    &copy; {{ date('Y') }} Chipper Farms. All rights reserved.
                </div>
                <div class="flex space-x-6 text-sm text-green-200">
                    <a href="#" class="hover:text-white transition-colors duration-300">Privacy Policy</a>
                    <a href="#" class="hover:text-white transition-colors duration-300">Terms of Service</a>
                    <a href="#" class="hover:text-white transition-colors duration-300">Support</a>
                </div>
            </div>
        </div>

        <!-- Background decoration -->
        <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-green-600/10 to-green-400/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-gradient-to-tr from-green-700/10 to-green-500/10 rounded-full blur-2xl"></div>
    </footer>

    <!-- Smooth Scrolling Script -->
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all slide-up elements
        document.querySelectorAll('.slide-up').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Observe all fade-in elements
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            observer.observe(el);
        });
    </script>
    </body>
</html>