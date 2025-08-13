<?php
$pageTitle = "Register";
include_once __DIR__ . '/../includes/header.php';
// No server-side auth_middleware here as registration is public
?>
    <main class="flex-grow bg-gray-100 py-10 px-6 md:px-12 flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
            <h2 class="text-3xl font-bold text-blue-800 mb-6 text-center">Create Your Account</h2>
            <p class="text-gray-600 mb-6 text-center">Join Traders Sanctuary to access exclusive content and tools.</p>

            <!-- Global Message Display (for Firebase errors/success) -->
            <div id="auth-message" class="hidden p-3 mb-4 rounded-lg text-center font-medium" role="alert"></div>

            <form id="register-form" class="space-y-6">
                <div>
                    <label for="register-email" class="block text-gray-700 text-sm font-medium mb-2">Email Address</label>
                    <input
                        type="email"
                        id="register-email"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        placeholder="your.email@example.com"
                        required
                    />
                </div>
                <div>
                    <label for="register-password" class="block text-gray-700 text-sm font-medium mb-2">Password</label>
                    <input
                        type="password"
                        id="register-password"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        placeholder="Minimum 6 characters"
                        required
                    />
                </div>
                <div>
                    <label for="register-confirm-password" class="block text-gray-700 text-sm font-medium mb-2">Confirm Password</label>
                    <input
                        type="password"
                        id="register-confirm-password"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        placeholder="Re-enter your password"
                        required
                    />
                </div>
                <button
                    type="submit"
                    id="register-button"
                    class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
                >
                    Register
                </button>
            </form>
            <p class="text-center text-gray-600 mt-6">
                Already have an account? <a href="<?= BASE_URL ?>login.php" class="text-blue-600 hover:underline font-medium">Login here</a>
            </p>
        </div>
    </main>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
