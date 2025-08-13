<?php
$pageTitle = "Home";
include_once __DIR__ . '/../includes/header.php';
?>
    <main class="flex-grow bg-gray-50 text-gray-800">
        <!-- Hero Section -->
        <section class="relative bg-gradient-to-br from-blue-900 to-blue-600 text-white py-24 md:py-32 overflow-hidden">
            <div class="absolute inset-0 z-0 opacity-20">
                <!-- CORRECTED LINE HERE -->
                <div class="w-full h-full bg-cover bg-center" style="background-image: url('<?= BASE_URL ?>images/forex-background.jpg');"></div>
            </div>
            <div class="container mx-auto px-6 md:px-12 relative z-10 text-center">
                <h2 class="text-5xl md:text-7xl font-extrabold leading-tight mb-6 animate-fade-in-down">
                    Master Forex. <span class="text-blue-200">Together.</span>
                </h2>
                <p class="text-xl md:text-2xl mb-10 max-w-3xl mx-auto opacity-90 animate-fade-in-up">
                    Unlock your trading potential with expert analysis, real-time signals, and a vibrant community.
                </p>
                <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6 animate-fade-in-up delay-200">
                    <button id="hero-register-btn" class="px-8 py-4 bg-green-500 text-white text-xl font-bold rounded-full shadow-lg hover:bg-green-600 transform hover:scale-105 transition duration-300 focus:outline-none focus:ring-4 focus:ring-green-400">
                        Join the Community
                    </button>
                    <button id="hero-login-btn" class="px-8 py-4 bg-blue-500 text-white text-xl font-bold rounded-full shadow-lg hover:bg-blue-600 transform hover:scale-105 transition duration-300 focus:outline-none focus:ring-4 focus:ring-blue-400">
                        Create Account
                    </button>
                </div>
            </div>
        </section>

        <!-- Intro Section -->
        <section class="py-16 md:py-24 bg-white px-6 md:px-12">
            <div class="container mx-auto text-center">
                <h3 class="text-4xl font-bold text-blue-800 mb-8">What is Traders Sanctuary?</h3>
                <p class="text-lg md:text-xl max-w-4xl mx-auto text-gray-700 leading-relaxed">
                    Traders Sanctuary is your dedicated platform for navigating the dynamic world of Forex. We provide a holistic approach to trading success, combining sophisticated market analysis, actionable trading signals, and a supportive network of fellow traders. Whether you're a beginner or an experienced pro, our resources are designed to elevate your trading journey.
                </p>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-16 md:py-24 bg-blue-50 px-6 md:px-12">
            <div class="container mx-auto">
                <h3 class="text-4xl font-bold text-blue-800 text-center mb-12">Our Core Features</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-12">
                    <div class="bg-white rounded-xl shadow-lg p-8 text-center transform hover:scale-105 transition duration-300 border-t-4 border-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart text-blue-600 mx-auto mb-6"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>
                        <h4 class="text-2xl font-semibold text-gray-900 mb-4">Live Trades & Signals</h4>
                        <p class="text-gray-700">Get real-time trading signals and insights from expert analysts to make informed decisions.</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-lg p-8 text-center transform hover:scale-105 transition duration-300 border-t-4 border-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open text-blue-600 mx-auto mb-6"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        <h4 class="text-2xl font-semibold text-gray-900 mb-4">Market Education</h4>
                        <p class="text-gray-700">Access a comprehensive library of educational materials, webinars, and tutorials.</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-lg p-8 text-center transform hover:scale-105 transition duration-300 border-t-4 border-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users text-blue-600 mx-auto mb-6"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <h4 class="text-2xl font-semibold text-gray-900 mb-4">Community Support</h4>
                        <p class="text-gray-700">Connect with a thriving community of traders, share ideas, and grow together.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials Carousel -->
        <section class="py-16 md:py-24 bg-white px-6 md:px-12">
            <div class="container mx-auto text-center">
                <h3 class="text-4xl font-bold text-blue-800 mb-12">What Our Members Say</h3>
                <div class="relative overflow-hidden">
                    <div id="testimonials-carousel" class="flex transition-transform duration-500 ease-in-out">
                        <!-- Testimonials will be loaded here by main.js -->
                    </div>
                    <div id="testimonials-pagination" class="absolute bottom-4 left-0 right-0 flex justify-center space-x-2">
                        <!-- Pagination dots will be loaded here by main.js -->
                    </div>
                </div>
            </div>
        </section>

        <!-- Newsletter Signup -->
        <section class="py-16 md:py-24 bg-gradient-to-r from-blue-800 to-blue-600 text-white px-6 md:px-12">
            <div class="container mx-auto text-center">
                <h3 class="text-4xl font-bold mb-6">Stay Updated with Traders Sanctuary</h3>
                <p class="text-xl mb-8 opacity-90">
                    Subscribe to our newsletter for the latest market insights, trading tips, and platform updates.
                </p>
                <form id="newsletter-form" class="max-w-xl mx-auto flex flex-col sm:flex-row gap-4">
                    <input
                        type="email"
                        placeholder="Enter your email address"
                        class="flex-grow p-4 rounded-full border-2 border-white bg-white bg-opacity-20 text-white placeholder-white focus:outline-none focus:ring-2 focus:ring-blue-300 transition duration-300"
                        required
                        id="newsletter-email"
                    />
                    <button
                        type="submit"
                        class="px-8 py-4 bg-green-500 text-white font-bold rounded-full shadow-lg hover:bg-green-600 transform hover:scale-105 transition duration-300 focus:outline-none focus:ring-4 focus:ring-green-400"
                    >
                        Subscribe
                    </button>
                </form>
                <p class="text-sm mt-4 opacity-80">We respect your privacy and will not share your email.</p>
            </div>
        </section>
    </main>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>