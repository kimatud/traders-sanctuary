<?php
$pageTitle = "Admin Panel";
include_once __DIR__ . '/../includes/header.php';
// This middleware is for server-side redirection if needed.
// For now, client-side JS handles access control.
// include_once __DIR__ . '/../includes/auth_middleware.php';
?>
    <main class="flex-grow bg-gray-100 py-10 px-6 md:px-12">
        <div class="container mx-auto">
            <h2 class="text-4xl font-bold text-blue-800 mb-8">Admin Panel</h2>
            <p class="text-gray-600 text-lg mb-8">
                Manage platform content, users, and testimonials.
            </p>

            <div id="admin-access-denied" class="hidden flex flex-col justify-center items-center bg-gray-100 p-4 text-center">
                <h3 class="text-3xl font-bold text-red-600 mb-4">Admin Access Denied</h3>
                <p class="text-lg text-gray-700">You must be an administrator to view this page.</p>
            </div>

            <div id="admin-content" class="hidden bg-white rounded-xl shadow-lg p-6">
                <div id="admin-message" class="p-3 mb-4 rounded-lg text-sm hidden"></div>

                <div class="flex border-b border-gray-200 mb-6">
                    <button id="tab-announcements" class="px-6 py-3 text-lg font-semibold text-blue-700 border-b-2 border-blue-700">
                        Announcements
                    </button>
                    <button id="tab-testimonials" class="px-6 py-3 text-lg font-semibold text-gray-600 hover:text-blue-700">
                        Testimonials
                    </button>
                    <button id="tab-users" class="px-6 py-3 text-lg font-semibold text-gray-600 hover:text-blue-700">
                        Users
                    </button>
                </div>

                <!-- Announcements Tab -->
                <div id="admin-tab-announcements">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Manage Announcements</h3>
                    <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <h4 id="announcement-form-title" class="font-semibold mb-2">Add New Announcement</h4>
                        <input
                            type="text"
                            placeholder="Title"
                            class="w-full p-2 border border-gray-300 rounded-md mb-2"
                            id="announcement-input-title"
                        />
                        <textarea
                            placeholder="Content"
                            rows="3"
                            class="w-full p-2 border border-gray-300 rounded-md mb-2"
                            id="announcement-input-content"
                        ></textarea>
                        <button
                            id="announcement-submit-btn"
                            class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition duration-300"
                        >
                            Add Announcement
                        </button>
                        <button
                            id="announcement-cancel-edit-btn"
                            class="w-full mt-2 bg-gray-400 text-white py-2 rounded-md hover:bg-gray-500 transition duration-300 hidden"
                        >
                            Cancel Edit
                        </button>
                    </div>

                    <div id="announcements-admin-list" class="space-y-4">
                        <!-- Announcements will be loaded here by main.js -->
                    </div>
                </div>

                <!-- Testimonials Tab -->
                <div id="admin-tab-testimonials" class="hidden">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Manage Testimonials</h3>
                    <div id="testimonials-admin-list" class="space-y-4">
                        <!-- Testimonials will be loaded here by main.js -->
                    </div>
                </div>

                <!-- Users Tab -->
                <div id="admin-tab-users" class="hidden">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Manage Users</h3>
                    <div id="users-admin-list" class="space-y-4">
                        <!-- Users will be loaded here by main.js -->
                    </div>
                </div>
            </div>
        </div>
    </main>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>