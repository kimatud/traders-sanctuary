<?php
$pageTitle = "Contact Us";
include_once __DIR__ . '/../includes/header.php';
?>
    <main class="flex-grow bg-gray-100 py-10 px-6 md:px-12">
        <div class="container mx-auto max-w-2xl bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-4xl font-bold text-blue-800 text-center mb-8">Contact Us</h2>
            <p class="text-lg text-gray-700 text-center mb-8">
                Have a question or need support? Fill out the form below and we'll get back to you.
            </p>

            <div id="contact-form-message" class="p-3 mb-4 rounded-lg text-sm hidden"></div>

            <form id="contact-form" class="space-y-6">
                <div>
                    <label for="contact-name" class="block text-gray-700 text-sm font-medium mb-2">Your Name</label>
                    <input
                        type="text"
                        id="contact-name"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        required
                    />
                </div>
                <div>
                    <label for="contact-email" class="block text-gray-700 text-sm font-medium mb-2">Your Email</label>
                    <input
                        type="email"
                        id="contact-email"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        required
                    />
                </div>
                <div>
                    <label for="contact-subject" class="block text-gray-700 text-sm font-medium mb-2">Subject</label>
                    <input
                        type="text"
                        id="contact-subject"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        required
                    />
                </div>
                <div>
                    <label for="contact-message" class="block text-gray-700 text-sm font-medium mb-2">Message</label>
                    <textarea
                        id="contact-message"
                        rows="6"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        required
                    ></textarea>
                </div>
                <button
                    type="submit"
                    class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 flex items-center justify-center space-x-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-send"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M15 15 22 2"/></svg>
                    <span>Send Message</span>
                </button>
            </form>
        </div>
    </main>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>