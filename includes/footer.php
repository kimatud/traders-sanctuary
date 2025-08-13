<?php
// includes/footer.php
?>
    <footer class="bg-gray-900 text-gray-300 py-10 px-6 md:px-12 mt-auto">
        <div class="container mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- About Section -->
            <div>
                <h3 class="text-xl font-bold text-white mb-4">Traders Sanctuary</h3>
                <p class="text-sm">
                    Your ultimate hub for Forex trading. Empowering traders with cutting-edge analysis, education, and a supportive community.
                </p>
            </div>

            <!-- Quick Links -->
            <div>
                <h3 class="text-xl font-bold text-white mb-4">Quick Links</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="#" class="hover:text-blue-400 transition duration-300">About Us</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition duration-300">Services</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition duration-300">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition duration-300">Terms of Service</a></li>
                </ul>
            </div>

            <!-- Social Media -->
            <div>
                <h3 class="text-xl font-bold text-white mb-4">Connect With Us</h3>
                <div class="flex space-x-4">
                    <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="text-gray-300 hover:text-pink-500 transition duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-instagram"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>
                    </a>
                    <a href="https://discord.com/invite/your-invite-code" target="_blank" rel="noopener noreferrer" class="text-gray-300 hover:text-indigo-500 transition duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </a>
                    <a href="https://t.me/yourtelegramchannel" target="_blank" rel="noopener noreferrer" class="text-gray-300 hover:text-blue-400 transition duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-send"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M15 15 22 2"/></svg>
                    </a>
                </div>
                <p class="text-sm mt-4">&copy; <?php echo date("Y"); ?> Traders Sanctuary. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Auth Modal HTML (hidden by default, controlled by JS) -->
    <div id="auth-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4 hidden">
        <div class="bg-white rounded-xl shadow-2xl p-8 w-full max-w-md relative animate-fade-in-up">
            <button id="auth-modal-close" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 transition duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
            <h2 id="auth-modal-title" class="text-3xl font-bold text-center text-blue-800 mb-6">Login</h2>

            <div id="auth-modal-message" class="p-3 mb-4 rounded-lg text-sm hidden"></div>

            <form id="auth-form" class="space-y-4">
                <div>
                    <label for="auth-email" class="block text-gray-700 text-sm font-medium mb-1">Email</label>
                    <input
                        type="email"
                        id="auth-email"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        required
                    />
                </div>
                <div id="auth-password-field">
                    <label for="auth-password" class="block text-gray-700 text-sm font-medium mb-1">Password</label>
                    <input
                        type="password"
                        id="auth-password"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        required
                    />
                </div>
                <div id="auth-confirm-password-field" style="display:none;">
                    <label for="auth-confirm-password" class="block text-gray-700 text-sm font-medium mb-1">Confirm Password</label>
                    <input
                        type="password"
                        id="auth-confirm-password"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        required
                    />
                </div>

                <button
                    type="submit"
                    id="auth-submit-button"
                    class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
                >
                    Login
                </button>
            </form>

            <div class="mt-6 text-center text-gray-600">
                <div id="auth-switch-login">
                    Don't have an account?
                    <button id="switch-to-register" class="text-blue-600 hover:underline font-medium">Register</button>
                    <br />
                    <button id="switch-to-reset" class="text-blue-600 hover:underline font-medium mt-2">Forgot Password?</button>
                </div>
                <div id="auth-switch-register" style="display:none;">
                    Already have an account?
                    <button id="switch-to-login" class="text-blue-600 hover:underline font-medium">Login</button>
                </div>
                <div id="auth-switch-reset" style="display:none;">
                    Remembered your password?
                    <button id="switch-to-login-from-reset" class="text-blue-600 hover:underline font-medium">Login</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Message Modal HTML (hidden by default, controlled by JS) -->
    <div id="global-message-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm w-full border flex flex-col items-center text-center">
            <div id="global-message-icon" class="mb-4"></div>
            <p id="global-message-text" class="text-lg font-semibold mb-4"></p>
            <button
                id="global-message-close"
                class="px-6 py-2 bg-blue-600 text-white rounded-full hover:bg-blue-700 transition duration-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
            >
                Close
            </button>
        </div>
    </div>

    <!-- Corrected paths for JS files -->
    <script src="<?= BASE_URL ?>js/auth.js"></script>
    <script src="<?= BASE_URL ?>js/main.js"></script>
</body>
</html>
