<?php
$pageTitle = "Dashboard";
include_once __DIR__ . '/../includes/header.php';
// This middleware is for server-side redirection if needed.
// For now, client-side JS handles access control.
// include_once __DIR__ . '/../includes/auth_middleware.php';
?>
    <main class="flex-grow bg-gray-100 py-10 px-6 md:px-12">
        <div class="container mx-auto">
            <h2 class="text-4xl font-bold text-blue-800 mb-8">
                Welcome, <span id="dashboard-user-email">Trader</span>!
            </h2>
            <p class="text-gray-600 text-lg mb-8">
                Your User ID: <span class="font-mono bg-gray-200 p-1 rounded text-sm" id="dashboard-user-id">Loading...</span>
            </p>

            <div id="dashboard-access-denied" class="hidden flex flex-col justify-center items-center bg-gray-100 p-4 text-center">
                <h3 class="text-3xl font-bold text-red-600 mb-4">Access Denied</h3>
                <p class="text-lg text-gray-700">Please log in to view the dashboard.</p>
            </div>

            <div id="dashboard-content" class="hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Live Charts & Rates -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-2xl font-semibold text-gray-900 mb-6 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart mr-3 text-blue-600"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>
                            Live Market Data
                        </h3>
                        <!-- New divs for Forex data display -->
                        <div id="forex-rates-display" class="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <p class="text-gray-500">Loading live Forex rates...</p>
                            <!-- Forex rates will be loaded here by main.js -->
                        </div>
                        <div id="forex-news-display" class="mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <p class="text-gray-500">Loading Forex news...</p>
                            <!-- Forex news will be loaded here by main.js -->
                        </div>
                        <!-- End of new divs -->

                        <div class="aspect-video w-full rounded-lg overflow-hidden border border-gray-200">
                            <!-- TradingView Widget - Example for EURUSD -->
                            <iframe
                                src="https://www.tradingview.com/widget/advanced-chart/?symbol=FX_IDC%3AEURUSD&interval=D&timezone=Etc%2FUTC&theme=light&style=1&locale=en&enable_publishing=false&allow_symbol_change=true&save_image=false&container_id=tradingview_chart"
                                width="100%"
                                height="100%"
                                frameborder="0"
                                allowfullscreen
                                title="TradingView EURUSD Chart"
                                class="rounded-lg"
                            ></iframe>
                        </div>
                        <p class="text-sm text-gray-500 mt-4">
                            Data provided by TradingView. For more comprehensive live data and news feeds, a dedicated backend integration with Forex APIs (e.g., OANDA, Alpha Vantage) would be required.
                        </p>
                    </div>

                    <!-- Announcements / Telegram Feed -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-2xl font-semibold text-gray-900 mb-6 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell mr-3 text-blue-600"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                            Announcements
                        </h3>
                        <div id="announcements-list" class="h-64 overflow-y-auto space-y-4 pr-2">
                            <!-- Announcements will be loaded here by main.js -->
                        </div>
                    </div>
                </div>

                <!-- Trading Strategies & Education -->
                <div class="mt-12 bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-2xl font-semibold text-gray-900 mb-6 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open mr-3 text-blue-600"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Trading Resources
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h4 class="text-xl font-semibold text-gray-800 mb-4">Exclusive Strategies</h4>
                            <ul class="list-disc list-inside space-y-2 text-gray-700">
                                <li>The Golden Cross Strategy (PDF) <a href="https://www.africau.edu/images/default/sample.pdf" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">(Download)</a></li>
                                <li>Scalping Techniques for Volatile Markets (PDF) <a href="https://www.africau.edu/images/default/sample.pdf" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">(Download)</a></li>
                                <li>Risk Management Essentials (PDF) <a href="https://www.africau.edu/images/default/sample.pdf" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">(Download)</a></li>
                                <li id="premium-content-link" style="display:none;">
                                    <span class="font-bold text-purple-600">Premium:</span> Advanced Algorithmic Trading (PDF) <a href="https://www.africau.edu/images/default/sample.pdf" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">(Download)</a>
                                </li>
                                <li id="upgrade-to-premium-message" style="display:none;">
                                    <span class="font-bold text-gray-500">Upgrade for Premium Content!</span>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold text-gray-800 mb-4">Market Education Videos</h4>
                            <div class="aspect-video w-full rounded-lg overflow-hidden border border-gray-200">
                                <!-- Placeholder YouTube Embed -->
                                <iframe
                                    width="100%"
                                    height="100%"
                                    src="https://www.youtube.com/embed/dQw4w9WgXcQ"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                    title="Forex Education Video"
                                    class="rounded-lg"
                                ></iframe>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">Learn the fundamentals and advanced concepts of Forex trading.</p>
                        </div>
                    </div>
                </div>

                <!-- Submit Testimonial -->
                <div class="mt-12 bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-2xl font-semibold text-gray-900 mb-6 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user mr-3 text-blue-600"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Share Your Experience
                    </h3>
                    <p class="text-gray-700 mb-4">
                        We'd love to hear how Traders Sanctuary has helped you. Submit your testimonial below!
                    </p>
                    <form id="testimonial-form" class="space-y-4">
                        <div>
                            <label for="testimonial-author" class="block text-gray-700 text-sm font-medium mb-1">Your Name/Alias</label>
                            <input
                                type="text"
                                id="testimonial-author"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                                required
                            />
                        </div>
                        <div>
                            <label for="testimonial-content" class="block text-gray-700 text-sm font-medium mb-1">Your Testimonial</label>
                            <textarea
                                id="testimonial-content"
                                rows="4"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                                required
                            ></textarea>
                        </div>
                        <button
                            type="submit"
                            class="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition duration-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50"
                        >
                            Submit Testimonial
                        </button>
                    </form>
                </div>

                <!-- Discord Community Widget -->
                <div class="mt-12 bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-2xl font-semibold text-gray-900 mb-6 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square mr-3 text-blue-600"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Join Our Discord Community
                    </h3>
                    <p class="text-gray-700 mb-4">
                        Connect with fellow traders, discuss strategies, and get real-time support in our active Discord server.
                    </p>
                    <iframe
                        src="https://discord.com/widget?id=YOUR_DISCORD_SERVER_ID&theme=dark"
                        width="100%"
                        height="300"
                        allowtransparency="true"
                        frameborder="0"
                        sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"
                        class="rounded-lg"
                        title="Discord Widget"
                    ></iframe>
                    <p class="text-sm text-gray-500 mt-4">
                        Make sure to replace `YOUR_DISCORD_SERVER_ID` with your actual Discord server ID for the widget to work.
                    </p>
                </div>

                <!-- Payment Gateway Integration Placeholder -->
                <div id="premium-upgrade-section" class="mt-12 bg-gradient-to-r from-purple-800 to-purple-600 text-white rounded-xl shadow-lg p-6 text-center" style="display:none;">
                    <h3 class="text-3xl font-bold mb-4">Unlock Premium Features!</h3>
                    <p class="text-xl mb-6">Access exclusive signals, advanced strategies, and personalized analytics.</p>
                    <button class="px-8 py-4 bg-yellow-400 text-purple-900 font-bold rounded-full shadow-lg hover:bg-yellow-500 transform hover:scale-105 transition duration-300 focus:outline-none focus:ring-4 focus:ring-yellow-300">
                        Upgrade to Premium
                    </button>
                    <p class="text-sm mt-4 opacity-80">
                        (This button would integrate with a payment gateway like Stripe or PayPal in a live environment.)
                    </p>
                </div>
            </div>
        </div>
    </main>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>