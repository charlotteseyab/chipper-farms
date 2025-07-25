<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chipper Farms - Welcome</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
            <style>
        .chipper-green { color: #1C6C51; }
        .bg-chipper-green { background-color: #1C6C51; }
        .border-chipper-green { border-color: #1C6C51; }
        .hover\:bg-chipper-green:hover { background-color: #1C6C51; }
        .hover\:text-chipper-green:hover { color: #1C6C51; }
            </style>
    </head>
<body class="font-sans antialiased" style="background-color: #f6fefb; min-height: 100vh; display: flex; flex-direction: column;">
    <!-- Navbar -->
    <nav class="bg-white shadow-sm py-4 px-6 flex justify-between items-center">
        <div class="flex items-center gap-2">
            <!-- Use provided logo image -->
            <a href="/">
                <img src="/images/chipper-farms-logo.jpg" alt="Chipper Farms Logo" class="h-12 w-auto" />
            </a>
                        </div>
        <ul class="flex gap-6 font-medium">
            <li><a href="#home" class="transition chipper-green hover:text-black">Dashboard</a></li>
            <li><a href="#about" class="transition chipper-green hover:text-black">About</a></li>
            <li><a href="#our-pigs" class="transition chipper-green hover:text-black">Our Pigs</a></li>
            <li><a href="#contact" class="transition chipper-green hover:text-black">Contact</a></li>
        </ul>
        <a href="{{ route('login') }}" class="ml-6 px-5 py-2 rounded-lg font-semibold text-white" style="background: #1C6C51;">Sign In</a>
                            </nav>

    <!-- Hero Section -->
    <section id="home" class="flex-1 flex flex-col items-center justify-center text-center py-16 px-4" style="background: linear-gradient(to bottom, #E6F4EF 0%, #f6fefb 100%);">
        <!-- <img src="https://cdn.pixabay.com/photo/2017/01/31/13/14/animal-2028258_1280.png" alt="Happy Pig" class="w-40 h-40 mx-auto mb-6 rounded-full shadow-lg border-4" style="border-color: #1C6C51; background: #fff;" /> -->
        <h1 class="text-4xl md:text-5xl font-extrabold mb-4" style="color: #1C6C51;">Welcome to Chipper Farms!</h1>
        <p class="text-lg md:text-xl mb-8 max-w-2xl mx-auto" style="color: #1C6C51;">Where happy pigs roam and every day is a sunny day on the farm. We care for our animals with love and dedication, ensuring the best for our pigs and our community.</p>
        <a href="#our-pigs" class="inline-block px-6 py-3 rounded-lg shadow font-semibold text-white transition" style="background: #1C6C51;">Meet Our Pigs</a>
    </section>

    <!-- About Section -->
    <section id="about" class="py-16 px-4 bg-white">
        <div class="max-w-3xl mx-auto text-center">
            <h2 class="text-3xl font-bold mb-4" style="color: #1C6C51;">About Chipper Farms</h2>
            <p class="text-lg" style="color: #1C6C51;">Chipper Farms is a family-run pig farm dedicated to ethical animal care and sustainable farming. Our pigs enjoy open pastures, healthy diets, and lots of love. We believe in transparency, community, and the joy of farm life.</p>
                                        </div>
    </section>

    <!-- Our Pigs Section -->
    <section id="our-pigs" class="py-16 px-4" style="background: #E6F4EF;">
        <div class="max-w-5xl mx-auto text-center">
            <h2 class="text-3xl font-bold mb-8" style="color: #1C6C51;">Our Pigs</h2>
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

    <!-- Contact Section -->
    <section id="contact" class="py-16 px-4 bg-white">
        <div class="max-w-2xl mx-auto text-center">
            <h2 class="text-3xl font-bold mb-4" style="color: #1C6C51;">Contact Us</h2>
            <p class="mb-6" style="color: #1C6C51;">Have questions or want to visit? Reach out to us!</p>
            <a href="mailto:info@chipperfarms.com" class="inline-block px-6 py-3 rounded-lg shadow font-semibold text-white transition" style="background: #1C6C51;">Email Us</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-white text-center py-6 mt-auto" style="background: #1C6C51;">
        <div class="mb-2 font-semibold">Chipper Farms &copy; {{ date('Y') }}. All rights reserved.</div>
        <div class="text-green-100 text-sm">123 Farm Lane, Countryside, USA | (555) 123-4567</div>
        <div class="mt-2">
            <a href="#" class="text-green-100 hover:text-white mx-2">Facebook</a>|
            <a href="#" class="text-green-100 hover:text-white mx-2">Instagram</a>
        </div>
    </footer>
    </body>
</html>