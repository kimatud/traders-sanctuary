// public/js/auth.js
// Handles user authentication (login, register, logout, password reset)
// Interacts with Firebase Auth client-side and updates UI.

document.addEventListener('DOMContentLoaded', () => {
    // Declare all DOM elements at the very beginning of the DCL event listener
    const authModal = document.getElementById('auth-modal');
    const authModalCloseBtn = document.getElementById('auth-modal-close');
    const authModalTitle = document.getElementById('auth-modal-title');
    const authModalMessage = document.getElementById('auth-modal-message');
    const authForm = document.getElementById('auth-form');
    const authEmailInput = document.getElementById('auth-email');
    const authPasswordInput = document.getElementById('auth-password');
    const authConfirmPasswordInput = document.getElementById('auth-confirm-password');
    const authPasswordField = document.getElementById('auth-password-field');
    const authConfirmPasswordField = document.getElementById('auth-confirm-password-field');
    const authSubmitButton = document.getElementById('auth-submit-button'); // Ensure this is declared

    const switchToRegisterBtn = document.getElementById('switch-to-register');
    const switchToLoginBtn = document.getElementById('switch-to-login');
    const switchToResetBtn = document.getElementById('switch-to-reset');
    const switchToLoginFromResetBtn = document.getElementById('switch-to-login-from-reset');

    const authSwitchLogin = document.getElementById('auth-switch-login');
    const authSwitchRegister = document.getElementById('auth-switch-register');
    const authSwitchReset = document.getElementById('auth-switch-reset');

    let currentAuthType = 'login'; // 'login', 'register', 'reset'

    // Function to show global message modal
    function showGlobalMessage(message, type) {
        const modal = document.getElementById('global-message-modal');
        const text = document.getElementById('global-message-text');
        const iconContainer = document.getElementById('global-message-icon');
        const closeBtn = document.getElementById('global-message-close');

        if (!modal || !text || !iconContainer || !closeBtn) {
            console.error("Global message modal elements not found.");
            return;
        }

        text.textContent = message;
        modal.classList.remove('hidden');

        // Clear previous classes and content
        modal.querySelector('.border').classList.remove('bg-green-100', 'border-green-400', 'text-green-700', 'bg-red-100', 'border-red-400', 'text-red-700');
        iconContainer.innerHTML = '';

        if (type === 'success') {
            modal.querySelector('.border').classList.add('bg-green-100', 'border-green-400', 'text-green-700');
            iconContainer.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check-circle h-6 w-6 text-green-500"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>';
        } else {
            modal.querySelector('.border').classList.add('bg-red-100', 'border-red-400', 'text-red-700');
            iconContainer.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-circle h-6 w-6 text-red-500"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>';
        }

        closeBtn.onclick = () => modal.classList.add('hidden');
    }
    window.showGlobalMessage = showGlobalMessage; // Expose globally

    function setAuthModalType(type) {
        currentAuthType = type;
        // Ensure these elements are defined before accessing their properties
        if (authModalTitle) authModalTitle.textContent = type === 'login' ? 'Login' : type === 'register' ? 'Register' : 'Reset Password';
        if (authSubmitButton) authSubmitButton.textContent = type === 'login' ? 'Login' : type === 'register' ? 'Register' : 'Send Reset Email';

        if (authPasswordField) authPasswordField.style.display = (type === 'reset') ? 'none' : 'block';
        if (authConfirmPasswordField) authConfirmPasswordField.style.display = (type === 'register') ? 'block' : 'none';
        if (authPasswordInput) authPasswordInput.required = (type !== 'reset');
        if (authConfirmPasswordInput) authConfirmPasswordInput.required = (type === 'register');

        if (authSwitchLogin) authSwitchLogin.style.display = (type === 'login') ? 'block' : 'none';
        if (authSwitchRegister) authSwitchRegister.style.display = (type === 'register') ? 'block' : 'none';
        if (authSwitchReset) authSwitchReset.style.display = (type === 'reset') ? 'block' : 'none';

        if (authModalMessage) {
            authModalMessage.textContent = '';
            authModalMessage.classList.add('hidden');
        }
    }

    function openAuthModal(type) {
        setAuthModalType(type);
        if (authModal) authModal.classList.remove('hidden');
        if (authEmailInput) authEmailInput.value = '';
        if (authPasswordInput) authPasswordInput.value = '';
        if (authConfirmPasswordInput) authConfirmPasswordInput.value = '';
    }
    window.openAuthModal = openAuthModal; // Expose globally

    function closeAuthModal() {
        if (authModal) authModal.classList.add('hidden');
    }

    if (authModalCloseBtn) authModalCloseBtn.addEventListener('click', closeAuthModal);
    // Add null checks for event listeners as well
    if (switchToRegisterBtn) switchToRegisterBtn.addEventListener('click', () => setAuthModalType('register'));
    if (switchToLoginBtn) switchToLoginBtn.addEventListener('click', () => setAuthModalType('login'));
    if (switchToResetBtn) switchToResetBtn.addEventListener('click', () => setAuthModalType('reset'));
    if (switchToLoginFromResetBtn) switchToLoginFromResetBtn.addEventListener('click', () => setAuthModalType('login'));

    if (authForm) {
        authForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (authModalMessage) {
                authModalMessage.textContent = '';
                authModalMessage.classList.add('hidden');
            }


            const email = authEmailInput ? authEmailInput.value : '';
            const password = authPasswordInput ? authPasswordInput.value : '';
            const confirmPassword = authConfirmPasswordInput ? authConfirmPasswordInput.value : '';

            try {
                if (currentAuthType === 'register') {
                    if (password !== confirmPassword) {
                        if (authModalMessage) {
                            authModalMessage.textContent = 'Passwords do not match.';
                            authModalMessage.classList.remove('hidden');
                            authModalMessage.classList.add('bg-red-100', 'text-red-700');
                        }
                        return;
                    }
                    const userCredential = await window.authFunctions.createUserWithEmailAndPassword(window.firebaseAuth, email, password);
                    // After successful Firebase registration, sync with PHP backend
                    await fetch(window.API_BASE_URL_JS + 'auth.php?action=register', { // Corrected path
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: email, password: password, firebase_uid: userCredential.user.uid })
                    });
                    showGlobalMessage('Registration successful! You can now log in.', 'success');
                    setAuthModalType('login');
                } else if (currentAuthType === 'login') {
                    const userCredential = await window.authFunctions.signInWithEmailAndPassword(window.firebaseAuth, email, password);
                    // After successful Firebase login, sync with PHP backend to get role
                    const response = await fetch(window.API_BASE_URL_JS + 'auth.php?action=login', { // Corrected path
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ firebase_uid: userCredential.user.uid })
                    });
                    if (response.ok) {
                        const data = await response.json();
                        window.currentUserRole = data.role; // Update global role
                    }
                    showGlobalMessage('Login successful!', 'success');
                    closeAuthModal();
                    // CORRECTED REDIRECTION PATH
                    window.location.href = window.BASE_URL_JS + 'dashboard.php';
                } else if (currentAuthType === 'reset') {
                    await window.authFunctions.sendPasswordResetEmail(window.firebaseAuth, email);
                    showGlobalMessage('Password reset email sent. Check your inbox!', 'success');
                    closeAuthModal();
                }
            } catch (error) {
                console.error("Auth error:", error);
                let errorMessage = error.message;
                if (error.code) {
                    switch (error.code) {
                        case 'auth/email-already-in-use': errorMessage = 'Email already in use.'; break;
                        case 'auth/invalid-email': errorMessage = 'Invalid email address.'; break;
                        case 'auth/weak-password': errorMessage = 'Password is too weak.'; break;
                        case 'auth/user-not-found': errorMessage = 'No user found with this email.'; break;
                        case 'auth/wrong-password': errorMessage = 'Incorrect password.'; break;
                        case 'auth/network-request-failed': errorMessage = 'Network error. Please check your internet connection.'; break;
                    }
                }
                if (authModalMessage) {
                    authModalMessage.textContent = errorMessage;
                    authModalMessage.classList.remove('hidden');
                    authModalMessage.classList.add('bg-red-100', 'text-red-700');
                }
            }
        });
    }


    // Handle Auth Buttons in Header
    const authButtonsDesktop = document.getElementById('auth-buttons-desktop');
    const authButtonsMobile = document.getElementById('auth-buttons-mobile');

    function updateAuthButtons() {
        const user = window.firebaseAuth.currentUser;
        const role = window.currentUserRole;

        const loginBtnHtml = `
            <button id="header-login-btn" class="flex items-center space-x-2 text-lg font-medium bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded-full transition duration-300 shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-in"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
                <span>Login</span>
            </button>
        `;
        const registerBtnHtml = `
            <button id="header-register-btn" class="flex items-center space-x-2 text-lg font-medium bg-green-500 hover:bg-green-600 px-4 py-2 rounded-full transition duration-300 shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                <span>Register</span>
            </button>
        `;
        const logoutBtnHtml = `
            <button id="header-logout-btn" class="flex items-center space-x-2 text-lg font-medium bg-red-500 hover:bg-red-600 px-4 py-2 rounded-full transition duration-300 shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                <span>Logout</span>
            </button>
        `;

        if (user) {
            if (authButtonsDesktop) { // Add null check here
                authButtonsDesktop.innerHTML = logoutBtnHtml;
                // Add event listener to the newly created button
                const headerLogoutBtn = document.getElementById('header-logout-btn');
                if (headerLogoutBtn) {
                    headerLogoutBtn.addEventListener('click', handleLogout);
                }
            }
            if (authButtonsMobile) { // Add null check here
                authButtonsMobile.innerHTML = `<li>${logoutBtnHtml}</li>`;
                // Add event listener to the newly created button
                const mobileHeaderLogoutBtn = authButtonsMobile.querySelector('#header-logout-btn'); // Select within mobile container
                if (mobileHeaderLogoutBtn) {
                    mobileHeaderLogoutBtn.addEventListener('click', handleLogout);
                }
            }


            // Show/hide admin link based on role
            const adminLinkDesktop = document.getElementById('nav-admin');
            const adminLinkMobile = document.getElementById('mobile-nav-admin');
            if (adminLinkDesktop) adminLinkDesktop.style.display = (role === 'admin') ? 'flex' : 'none';
            if (adminLinkMobile) adminLinkMobile.style.display = (role === 'admin') ? 'flex' : 'none';

            // Show/hide dashboard access denied message
            const dashboardDenied = document.getElementById('dashboard-access-denied');
            const dashboardContent = document.getElementById('dashboard-content');
            if (dashboardDenied && dashboardContent) {
                dashboardDenied.style.display = 'none';
                dashboardContent.style.display = 'block';
            }

            // Update dashboard user info
            const dashboardUserEmail = document.getElementById('dashboard-user-email');
            const dashboardUserId = document.getElementById('dashboard-user-id');
            if (dashboardUserEmail) dashboardUserEmail.textContent = user.email ? user.email.split('@')[0] : 'Trader';
            if (dashboardUserId) dashboardUserId.textContent = user.uid;

            // Handle premium content visibility on dashboard
            const premiumLink = document.getElementById('premium-content-link');
            const upgradeMessage = document.getElementById('upgrade-to-premium-message');
            const premiumUpgradeSection = document.getElementById('premium-upgrade-section');

            if (premiumLink) premiumLink.style.display = (role === 'premium' || role === 'admin') ? 'list-item' : 'none';
            if (upgradeMessage) upgradeMessage.style.display = (role === 'member' || role === 'guest') ? 'list-item' : 'none';
            if (premiumUpgradeSection) premiumUpgradeSection.style.display = (role === 'member' || role === 'guest') ? 'block' : 'none';


            // Show/hide admin page content
            const adminDenied = document.getElementById('admin-access-denied');
            const adminContent = document.getElementById('admin-content');
            if (adminDenied && adminContent) {
                adminDenied.style.display = (role === 'admin') ? 'none' : 'flex';
                adminContent.style.display = (role === 'admin') ? 'block' : 'none';
            }


        } else {
            if (authButtonsDesktop) { // Add null check here
                authButtonsDesktop.innerHTML = `${loginBtnHtml} ${registerBtnHtml}`;
                // Add event listeners to the newly created buttons
                const headerLoginBtn = document.getElementById('header-login-btn');
                const headerRegisterBtn = document.getElementById('header-register-btn');
                if (headerLoginBtn) {
                    headerLoginBtn.addEventListener('click', () => openAuthModal('login'));
                }
                if (headerRegisterBtn) {
                    headerRegisterBtn.addEventListener('click', () => openAuthModal('register'));
                }
            }
            if (authButtonsMobile) { // Add null check here
                authButtonsMobile.innerHTML = `<li>${loginBtnHtml}</li><li>${registerBtnHtml}</li>`;

                const mobileHeaderLoginBtn = authButtonsMobile.querySelector('#header-login-btn');
                const mobileHeaderRegisterBtn = authButtonsMobile.querySelector('#header-register-btn');
                if (mobileHeaderLoginBtn) {
                    mobileHeaderLoginBtn.addEventListener('click', () => openAuthModal('login'));
                }
                if (mobileHeaderRegisterBtn) {
                    mobileHeaderRegisterBtn.addEventListener('click', () => openAuthModal('register'));
                }
            }

            // Hide admin link
            const adminLinkDesktop = document.getElementById('nav-admin');
            const adminLinkMobile = document.getElementById('mobile-nav-admin');
            if (adminLinkDesktop) adminLinkDesktop.style.display = 'none';
            if (adminLinkMobile) adminLinkMobile.style.display = 'none';

            // Show dashboard access denied message
            const dashboardDenied = document.getElementById('dashboard-access-denied');
            const dashboardContent = document.getElementById('dashboard-content');
            if (dashboardDenied && dashboardContent) {
                dashboardDenied.style.display = 'flex';
                dashboardContent.style.display = 'none';
            }
            // Hide admin page content
            const adminDenied = document.getElementById('admin-access-denied');
            const adminContent = document.getElementById('admin-content');
            if (adminDenied && adminContent) {
                adminDenied.style.display = 'flex';
                adminContent.style.display = 'none';
            }
        }
    }

    async function handleLogout() {
        try {
            await window.authFunctions.signOut(window.firebaseAuth);
            showGlobalMessage('Logged out successfully.', 'success');
            // CORRECTED REDIRECTION PATH FOR LOGOUT
            window.location.href = window.BASE_URL_JS + 'index.php'; // Redirect to home after logout
        } catch (error) {
            console.error("Logout error:", error);
            showGlobalMessage('Failed to logout: ' + error.message, 'error');
        }
    }

    // Initial call and listen for auth state changes
    // Ensure window.firebaseAuth exists before trying to listen
    if (window.firebaseAuth) {
        document.addEventListener('firebaseAuthReady', updateAuthButtons);
        // Corrected fetch call for user role sync on auth state change
        window.firebaseAuth.onAuthStateChanged(async (user) => {
            if (user) {
                window.currentUserId = user.uid;
                try {
                    // Use window.API_BASE_URL_JS for all backend API calls
                    const response = await fetch(window.API_BASE_URL_JS + 'users_handler.php?firebase_uid=' + user.uid);
                    if (response.ok) {
                        const userData = await response.json();
                        if (userData && userData.length > 0) {
                            window.currentUserRole = userData[0].role;
                        } else {
                            // If user exists in Firebase but not in MySQL, create a basic entry
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
            document.dispatchEvent(new CustomEvent('firebaseAuthReady'));
            updateAuthButtons(); // Call updateAuthButtons after role is potentially updated
        });
    } else {
        console.warn("Firebase Auth not initialized when auth.js loaded. Check firebase_config.js.");
    }


    // Event listeners for hero section buttons (only on index.php)
    const heroRegisterBtn = document.getElementById('hero-register-btn');
    const heroLoginBtn = document.getElementById('hero-login-btn');

    if (heroRegisterBtn) {
        heroRegisterBtn.addEventListener('click', () => openAuthModal('register'));
    }
    if (heroLoginBtn) {
        heroLoginBtn.addEventListener('click', () => openAuthModal('login'));
    }

    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const menuIconOpen = document.getElementById('menu-icon-open');
    const menuIconClose = document.getElementById('menu-icon-close');

    if (mobileMenuButton && mobileMenu && menuIconOpen && menuIconClose) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            menuIconOpen.classList.toggle('hidden');
            menuIconClose.classList.toggle('hidden');
        });
    }
});
