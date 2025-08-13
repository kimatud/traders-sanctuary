<?php
// includes/header.php
require_once __DIR__ . '/../api/config.php';
// This file will be included in all public/*.php files
// It sets up the session (if not already started) and Firebase config for JS.
// Define the base URL of your application
define('BASE_URL', '/traders-sanctuary/public/'); // This is correct, assuming your web root is htdocs and project is in traders-sanctuary
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traders Sanctuary - <?php echo $pageTitle ?? 'Master Forex. Together.'; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <!-- CRITICAL INLINE STYLES FOR BODY LAYOUT - FORCING VERTICAL STACKING -->
    <style>
        html, body {
            height: 100%; /* Ensure html and body take full viewport height */
            margin: 0 !important; /* Force margin to 0, overriding browser defaults/preflight */
            padding: 0 !important; /* Also force padding to 0 */
            font-family: 'Inter', sans-serif !important; /* Force Inter font */
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            display: flex !important; /* FORCE flex display */
            flex-direction: column !important; /* FORCE column direction */
            min-height: 100vh !important; /* FORCE body to take at least full viewport height */
            background-color: #f3f4f6; /* Tailwind gray-100 */
            color: #374151; /* Tailwind gray-700 */
        }

        /* Animations (keep these here or in style.css, they are less critical for layout) */
        .animate-fade-in-down { animation: fadeInDown 1s ease-out forwards; }
        .animate-fade-in-up { animation: fadeInUp 1s ease-out forwards; }
        .animate-fade-in-up.delay-200 { animation-delay: 0.2s; }
        .animate-slide-down { animation: slideDown 0.3s ease-out forwards; }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <!-- Your custom style.css -->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">

    <!-- Firebase JS SDK -->
    <script type="module">
        // Expose BASE_URL to JavaScript
        window.BASE_URL_JS = '<?= BASE_URL ?>';
        // Derive API_BASE_URL for fetch calls that go to the /api directory
        window.API_BASE_URL_JS = window.BASE_URL_JS.replace('/public/', '/') + 'api/';


        import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
        import { getAuth, onAuthStateChanged, signInWithCustomToken, signInAnonymously, createUserWithEmailAndPassword, signInWithEmailAndPassword, signOut, sendPasswordResetEmail } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';
        import { getFirestore, doc, getDoc, setDoc, updateDoc, collection, query, onSnapshot, deleteDoc } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js';

        // Firebase Configuration from PHP
        const firebaseConfig = {
            apiKey: "<?php echo FIREBASE_API_KEY; ?>",
            authDomain: "<?php echo FIREBASE_AUTH_DOMAIN; ?>",
            projectId: "<?php echo FIREBASE_PROJECT_ID; ?>",
            storageBucket: "<?php echo FIREBASE_STORAGE_BUCKET; ?>",
            messagingSenderId: "<?php echo FIREBASE_MESSAGING_SENDER_ID; ?>"
        };

        // Initialize Firebase
        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);
        const db = getFirestore(app);

        // Expose Firebase objects globally for main.js and auth.js
        window.firebaseApp = app;
        window.firebaseAuth = auth;
        window.firebaseDb = db;
        window.currentUserId = null; // Will be updated by auth.js
        window.currentUserRole = 'guest'; // Will be updated by auth.js

        // Initial auth state check and local DB sync
        onAuthStateChanged(auth, async (user) => {
            if (user) {
                window.currentUserId = user.uid;
                // Fetch user role from your PHP backend (which queries your MySQL DB)
                try {
                    // Corrected fetch path using window.API_BASE_URL_JS
                    const response = await fetch(window.API_BASE_URL_JS + 'users_handler.php?firebase_uid=' + user.uid);
                    if (response.ok) {
                        const userData = await response.json();
                        if (userData && userData.length > 0) {
                            window.currentUserRole = userData[0].role;
                        } else {
                            // If user exists in Firebase but not in MySQL, create a basic entry
                            // Corrected fetch path using window.API_BASE_URL_JS
                            await fetch(window.API_BASE_URL_JS + 'auth.php?action=register', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ email: user.email, password: 'firebase_managed_password', firebase_uid: user.uid })
                            });
                            window.currentUserRole = 'member'; // Default role
                        }
                    }
                } catch (error) {
                    console.error("Error syncing user role from backend:", error);
                    window.currentUserRole = 'member'; // Default to member on error
                }
            } else {
                window.currentUserId = null;
                window.currentUserRole = 'guest';
            }
            // Dispatch a custom event to notify other scripts that auth is ready
            document.dispatchEvent(new CustomEvent('firebaseAuthReady'));
        });

        // Expose auth functions for client-side use
        window.authFunctions = {
            createUserWithEmailAndPassword,
            signInWithEmailAndPassword,
            signOut,
            sendPasswordResetEmail,
            signInAnonymously,
            signInWithCustomToken
        };
    </script>
</head>
<body class="min-h-screen flex flex-col">
    <header class="header-blue-effect text-white shadow-lg py-6 md:py-8 sticky top-0 z-40">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-start flex-wrap">
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight header-title-shadow mr-auto">
                <a href="<?= BASE_URL ?>" class="hover:opacity-90 transition duration-300">Traders Sanctuary</a>
            </h1>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex space-x-8 items-center">
                <a href="<?= BASE_URL ?>" class="flex items-center space-x-2 text-lg font-medium hover:text-blue-200 transition duration-300 nav-link-hover-effect <?php echo (strpos($_SERVER['REQUEST_URI'], '/index.php') !== false || $_SERVER['REQUEST_URI'] === BASE_URL) ? 'text-blue-200 underline' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-home"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <span>Home</span>
                </a>
                <a href="<?= BASE_URL ?>dashboard.php" class="flex items-center space-x-2 text-lg font-medium hover:text-blue-200 transition duration-300 nav-link-hover-effect <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard.php') !== false) ? 'text-blue-200 underline' : ''; ?>" id="nav-dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="<?= BASE_URL ?>contact.php" class="flex items-center space-x-2 text-lg font-medium hover:text-blue-200 transition duration-300 nav-link-hover-effect <?php echo (strpos($_SERVER['REQUEST_URI'], '/contact.php') !== false) ? 'text-blue-200 underline' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <span>Contact</span>
                </a>
                <a href="<?= BASE_URL ?>admin.php" class="flex items-center space-x-2 text-lg font-medium hover:text-blue-200 transition duration-300 nav-link-hover-effect <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin.php') !== false) ? 'text-blue-200 underline' : ''; ?>" id="nav-admin" style="display:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.09.15a2 2 0 0 1 0 2l-.08.15a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.09-.15a2 2 0 0 1 0-2l.08-.15a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span>Admin</span>
                </a>
                <div id="auth-buttons-desktop">
                    <!-- Auth buttons will be rendered here by auth.js -->
                </div>
            </nav>

            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <button id="mobile-menu-button" class="text-white focus:outline-none">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path id="menu-icon-open" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        <path id="menu-icon-close" class="hidden" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <nav id="mobile-menu" class="md:hidden bg-blue-700 py-4 mt-4 rounded-lg shadow-xl hidden">
            <ul class="flex flex-col items-center space-y-4">
                <li><a href="<?= BASE_URL ?>" class="flex items-center space-x-3 text-xl font-medium hover:text-blue-200 transition duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-home"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <span>Home</span>
                </a></li>
                <li><a href="<?= BASE_URL ?>dashboard.php" class="flex items-center space-x-3 text-xl font-medium hover:text-blue-200 transition duration-300" id="mobile-nav-dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>
                    <span>Dashboard</span>
                </a></li>
                <li><a href="<?= BASE_URL ?>contact.php" class="flex items-center space-x-3 text-xl font-medium hover:text-blue-200 transition duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <span>Contact</span>
                </a></li>
                <li><a href="<?= BASE_URL ?>admin.php" class="flex items-center space-x-3 text-xl font-medium hover:text-blue-200 transition duration-300" id="mobile-nav-admin" style="display:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.09.15a2 2 0 0 1 0 2l-.08.15a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.09-.15a2 2 0 0 1 0-2l.08-.15a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span>Admin</span>
                </a></li>
                <li id="auth-buttons-mobile">
                    <!-- Auth buttons will be rendered here by auth.js -->
                </li>
            </ul>
        </nav>
    </header>
