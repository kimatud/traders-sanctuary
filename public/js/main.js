// public/js/main.js
// Handles general client-side interactions, data fetching, and form submissions.

document.addEventListener('DOMContentLoaded', () => {
    // --- Testimonials Carousel (Landing Page) ---
    const testimonialsCarousel = document.getElementById('testimonials-carousel');
    const testimonialsPagination = document.getElementById('testimonials-pagination');
    let currentTestimonialIndex = 0;
    let testimonialInterval;
    let allTestimonials = []; // Store all fetched testimonials

    async function fetchTestimonials() {
        try {
            // Corrected path for testimonials_handler.php
            const response = await fetch(window.BASE_URL_JS.replace('/public/', '') + '/api/testimonials_handler.php?approved_only=true');
            if (!response.ok) {
                // Corrected typo: new new Error to new Error
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            allTestimonials = data;
            renderTestimonials();
            if (allTestimonials.length > 1) {
                startTestimonialCarousel();
            }
        } catch (error) {
            console.error("Error fetching testimonials:", error);
            if (testimonialsCarousel) {
                testimonialsCarousel.innerHTML = '<p class="text-gray-500 italic text-center">Failed to load testimonials.</p>';
            }
        }
    }

    function renderTestimonials() {
        if (!testimonialsCarousel) return; // Only run on landing page

        testimonialsCarousel.innerHTML = '';
        if (testimonialsPagination) { // Add null check for pagination
            testimonialsPagination.innerHTML = '';
        }


        if (allTestimonials.length === 0) {
            testimonialsCarousel.innerHTML = '<p class="text-gray-500 italic text-center">No testimonials yet.</p>';
            return;
        }

        allTestimonials.forEach((testimonial, index) => {
            const testimonialDiv = document.createElement('div');
            testimonialDiv.className = 'w-full flex-shrink-0 p-4'; // Tailwind for flex item
            testimonialDiv.innerHTML = `
                <div class="bg-blue-50 rounded-xl shadow-md p-8 max-w-2xl mx-auto">
                    <p class="text-lg italic text-gray-700 mb-6">
                        "${testimonial.content}"
                    </p>
                    <p class="font-semibold text-blue-800">- ${testimonial.author}</p>
                </div>
            `;
            testimonialsCarousel.appendChild(testimonialDiv);

            if (testimonialsPagination) { // Only add dots if pagination element exists
                const dotButton = document.createElement('button');
                dotButton.className = `w-3 h-3 rounded-full ${index === currentTestimonialIndex ? 'bg-blue-600' : 'bg-gray-300'} transition-colors duration-300`;
                dotButton.addEventListener('click', () => {
                    clearInterval(testimonialInterval);
                    currentTestimonialIndex = index;
                    updateTestimonialCarousel();
                    startTestimonialCarousel();
                });
                testimonialsPagination.appendChild(dotButton);
            }
        });
        updateTestimonialCarousel();
    }

    function updateTestimonialCarousel() {
        if (testimonialsCarousel) {
            testimonialsCarousel.style.transform = `translateX(-${currentTestimonialIndex * 100}%)`;
            // Update pagination dots
            if (testimonialsPagination) { // Add null check for pagination
                const dots = testimonialsPagination.querySelectorAll('button');
                dots.forEach((dot, index) => {
                    if (index === currentTestimonialIndex) {
                        dot.classList.add('bg-blue-600');
                        dot.classList.remove('bg-gray-300');
                    } else {
                        dot.classList.remove('bg-blue-600');
                        dot.classList.add('bg-gray-300');
                    }
                });
            }
        }
    }

    function startTestimonialCarousel() {
        if (allTestimonials.length > 1) {
            clearInterval(testimonialInterval); // Clear any existing interval
            testimonialInterval = setInterval(() => {
                currentTestimonialIndex = (currentTestimonialIndex + 1) % allTestimonials.length;
                updateTestimonialCarousel();
            }, 5000); // Change testimonial every 5 seconds
        }
    }

    // Call fetchTestimonials on page load if elements exist
    if (testimonialsCarousel && testimonialsPagination) {
        fetchTestimonials();
    }

    // --- Contact Form Submission ---
    const contactForm = document.getElementById('contact-form');
    const contactFormMessage = document.getElementById('contact-form-message');

    if (contactForm) {
        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (contactFormMessage) {
                contactFormMessage.textContent = '';
                contactFormMessage.classList.add('hidden');
                contactFormMessage.classList.remove('bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700');
            }

            const name = document.getElementById('contact-name').value;
            const email = document.getElementById('contact-email').value;
            const subject = document.getElementById('contact-subject').value;
            const message = document.getElementById('contact-message').value;

            // Basic client-side validation
            if (!name || !email || !subject || !message) {
                if (contactFormMessage) {
                    contactFormMessage.textContent = 'All fields are required.';
                    contactFormMessage.classList.remove('hidden');
                    contactFormMessage.classList.add('bg-red-100', 'text-red-700');
                }
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                if (contactFormMessage) {
                    contactFormMessage.textContent = 'Please enter a valid email address.';
                    contactFormMessage.classList.remove('hidden');
                    contactFormMessage.classList.add('bg-red-100', 'text-red-700');
                }
                return;
            }

            try {
                // Corrected path for contact_handler.php
                const response = await fetch(window.BASE_URL_JS.replace('/public/', '') + '/api/contact_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ name, email, subject, message }),
                });

                const data = await response.json();

                if (data.success) {
                    if (contactFormMessage) {
                        contactFormMessage.textContent = data.message;
                        contactFormMessage.classList.remove('hidden');
                        contactFormMessage.classList.add('bg-green-100', 'text-green-700');
                    }
                    contactForm.reset(); // Clear the form
                } else {
                    if (contactFormMessage) {
                        contactFormMessage.textContent = data.message;
                        contactFormMessage.classList.remove('hidden');
                        contactFormMessage.classList.add('bg-red-100', 'text-red-700');
                    }
                }
            } catch (error) {
                console.error("Contact form submission error:", error);
                if (contactFormMessage) {
                    contactFormMessage.textContent = 'An unexpected error occurred. Please try again later.';
                    contactFormMessage.classList.remove('hidden');
                    contactFormMessage.classList.add('bg-red-100', 'text-red-700');
                }
            }
        });
    }

    // --- Newsletter Subscription ---
    const newsletterForm = document.getElementById('newsletter-form');
    const newsletterEmailInput = document.getElementById('newsletter-email');

    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = newsletterEmailInput.value;

            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showGlobalMessage('Please enter a valid email address for the newsletter.', 'error');
                return;
            }

            try {
                // Corrected path for newsletter_handler.php (assuming it exists, or contact_handler is used)
                // For now, this is a placeholder. If you have a dedicated newsletter_handler.php, use that.
                // Otherwise, you might integrate newsletter signup into contact_handler.php with an option.
                // For this example, we'll simulate success.
                showGlobalMessage('Thank you for subscribing to our newsletter! (Simulated)', 'success');
                newsletterForm.reset();
            } catch (error) {
                console.error("Newsletter subscription error:", error);
                showGlobalMessage('Failed to subscribe to newsletter. Please try again later.', 'error');
            }
        });
    }


    // --- Dashboard Specific Logic ---
    const dashboardContent = document.getElementById('dashboard-content');
    if (dashboardContent) {
        // Function to show admin/dashboard messages
        function showDashboardMessage(message, type) {
            const msgDiv = document.getElementById('dashboard-message'); // Assuming a message div exists on dashboard
            if (msgDiv) {
                msgDiv.textContent = message;
                msgDiv.className = `p-3 mb-4 rounded-lg text-sm ${type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`;
                msgDiv.classList.remove('hidden');
            } else {
                showGlobalMessage(message, type); // Fallback to global modal if no specific div
            }
        }

        // Fetch Finnhub Forex Data
        async function fetchFinnhubForexData() {
            try {
                // Corrected path for forex_proxy.php
                const response = await fetch(window.BASE_URL_JS.replace('/public/', '') + '/api/forex_proxy.php?provider=FINNHUB&endpoint=quote&symbol=EURUSD');
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(`Failed to fetch EURUSD quote: ${errorData.message || response.statusText}`);
                }
                const data = await response.json();
                const quote = data.c; // Current price
                const change = data.d; // Change
                const percentChange = data.dp; // Percent change

                const quoteElement = document.getElementById('eurusd-quote');
                const changeElement = document.getElementById('eurusd-change');

                if (quoteElement) quoteElement.textContent = quote ? quote.toFixed(5) : 'N/A';
                if (changeElement) {
                    if (change !== undefined) {
                        changeElement.textContent = `${change.toFixed(4)} (${percentChange.toFixed(2)}%)`;
                        changeElement.className = `font-semibold ${change >= 0 ? 'text-green-600' : 'text-red-600'}`;
                    } else {
                        changeElement.textContent = 'N/A';
                        changeElement.className = 'font-semibold text-gray-600';
                    }
                }
            } catch (error) {
                console.error("Error fetching Finnhub Forex data:", error);
                showDashboardMessage('Failed to load EURUSD quote: ' + error.message, 'error');
            }
        }

        // Fetch Finnhub News
        async function fetchFinnhubNews() {
            try {
                // Corrected path for forex_proxy.php
                const response = await fetch(window.BASE_URL_JS.replace('/public/', '') + '/api/forex_proxy.php?provider=FINNHUB&endpoint=news&category=forex');
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(`Failed to fetch Forex news: ${errorData.message || response.statusText}`);
                }
                const newsData = await response.json();
                const newsList = document.getElementById('forex-news-list');
                if (newsList) {
                    newsList.innerHTML = ''; // Clear previous news
                    if (newsData.length > 0) {
                        newsData.slice(0, 5).forEach(news => { // Display top 5 news
                            const newsItem = document.createElement('div');
                            newsItem.className = 'bg-gray-50 p-3 rounded-lg border border-gray-200';
                            newsItem.innerHTML = `
                                <a href="${news.url}" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700 hover:underline">${news.headline}</a>
                                <p class="text-sm text-gray-600">${news.summary}</p>
                                <p class="text-xs text-gray-500 mt-1">${new Date(news.datetime * 1000).toLocaleString()}</p>
                            `;
                            newsList.appendChild(newsItem);
                        });
                    } else {
                        newsList.innerHTML = '<p class="text-gray-500 italic">No recent Forex news available.</p>';
                    }
                }
            } catch (error) {
                console.error("Error fetching Finnhub news:", error);
                showDashboardMessage('Failed to load Forex news.', 'error');
            }
        }

        // Initial calls for dashboard data
        document.addEventListener('firebaseAuthReady', () => {
            // Only fetch if user is logged in and on dashboard
            if (window.firebaseAuth.currentUser) {
                fetchFinnhubForexData();
                fetchFinnhubNews();
                // Refresh data every 60 seconds
                setInterval(fetchFinnhubForexData, 60000);
                setInterval(fetchFinnhubNews, 300000); // News less frequent
            }
        });
    }


    // --- Admin Panel Specific Logic ---
    const adminContent = document.getElementById('admin-content');
    if (adminContent) {
        let currentAdminTab = 'announcements'; // Default tab for admin panel
        let adminTabListenersAttached = false; // Flag to ensure listeners are attached only once

        // Function to show admin messages
        function showAdminMessage(message, type) {
            const msgDiv = document.getElementById('admin-message');
            if (msgDiv) {
                msgDiv.textContent = message;
                msgDiv.className = `p-3 mb-4 rounded-lg text-sm ${type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`;
                msgDiv.classList.remove('hidden');
            } else {
                showGlobalMessage(message, type); // Fallback to global modal
            }
        }
        window.showAdminMessage = showAdminMessage; // Expose globally for admin functions

        // Tab switching logic
        function switchAdminTab(tab) {
            currentAdminTab = tab;
            // Get tab content elements inside the function to ensure they are current
            const adminTabAnnouncements = document.getElementById('admin-tab-announcements');
            const adminTabTestimonials = document.getElementById('admin-tab-testimonials');
            const adminTabUsers = document.getElementById('admin-tab-users');
            const adminTabButtons = document.querySelectorAll('.admin-tab-button'); // Re-query buttons to ensure they are found

            // Hide all tab contents with null checks
            if (adminTabAnnouncements) adminTabAnnouncements.classList.add('hidden');
            if (adminTabTestimonials) adminTabTestimonials.classList.add('hidden');
            if (adminTabUsers) adminTabUsers.classList.add('hidden');

            // Deactivate all tab buttons
            adminTabButtons.forEach(btn => {
                if (btn) { // Ensure button exists
                    btn.classList.remove('text-blue-700', 'border-b-2', 'border-blue-700');
                    btn.classList.add('text-gray-600', 'hover:text-blue-700');
                }
            });

            // Show active tab content and activate button with null checks
            const activeTabContent = document.getElementById(`admin-tab-${tab}`);
            if (activeTabContent) {
                activeTabContent.classList.remove('hidden');
            }
            const activeTabButton = document.querySelector(`.admin-tab-button[data-tab="${tab}"]`);
            if (activeTabButton) {
                activeTabButton.classList.add('text-blue-700', 'border-b-2', 'border-blue-700');
                activeTabButton.classList.remove('text-gray-600', 'hover:text-blue-700');
            }
            // Fetch data for the active tab
            if (tab === 'announcements') {
                fetchAdminAnnouncements();
            } else if (tab === 'testimonials') {
                fetchAdminTestimonials();
            } else if (tab === 'users') {
                fetchAdminUsers();
            }
        }

        // --- Admin Announcements Management ---
        const announcementForm = document.getElementById('announcement-form');
        const announcementTitleInput = document.getElementById('announcement-title');
        const announcementContentInput = document.getElementById('announcement-content');
        const announcementSubmitBtn = document.getElementById('announcement-submit-btn');
        const announcementCancelEditBtn = document.getElementById('announcement-cancel-edit-btn');
        let editingAnnouncementId = null;

        async function fetchAdminAnnouncements() {
            try {
                // Corrected path for announcements_handler.php
                const response = await fetch(window.BASE_URL_JS.replace('/public/', '') + '/api/announcements_handler.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                const announcementsList = document.getElementById('announcements-admin-list');
                if (announcementsList) {
                    announcementsList.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(announcement => {
                            const announcementDiv = document.createElement('div');
                            announcementDiv.className = 'bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex justify-between items-center';
                            announcementDiv.innerHTML = `
                                <div>
                                    <p class="font-semibold text-gray-900">${announcement.title}</p>
                                    <p class="text-sm text-gray-700">${announcement.content}</p>
                                    <p class="text-xs text-gray-500 mt-1">Posted: ${new Date(announcement.created_at).toLocaleString()}</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button class="text-blue-600 hover:text-blue-800 edit-announcement-btn" data-id="${announcement.id}" data-title="${announcement.title}" data-content="${announcement.content}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-edit"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-announcement-btn" data-id="${announcement.id}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                    </button>
                                </div>
                            `;
                            announcementsList.appendChild(announcementDiv);
                        });
                        addAnnouncementEventListeners();
                    } else {
                        announcementsList.innerHTML = '<p class="text-gray-500 italic">No announcements to manage.</p>';
                    }
                }
            } catch (error) {
                console.error("Error fetching announcements:", error);
                showAdminMessage('Failed to load announcements: ' + error.message, 'error');
            }
        }

        function addAnnouncementEventListeners() {
            document.querySelectorAll('.edit-announcement-btn').forEach(button => {
                button.addEventListener('click', (e) => {
                    editingAnnouncementId = e.currentTarget.dataset.id;
                    announcementTitleInput.value = e.currentTarget.dataset.title;
                    announcementContentInput.value = e.currentTarget.dataset.content;
                    announcementSubmitBtn.textContent = 'Update Announcement';
                    announcementCancelEditBtn.classList.remove('hidden');
                });
            });

            document.querySelectorAll('.delete-announcement-btn').forEach(button => {
                button.addEventListener('click', async (e) => {
                    // Replaced confirm() with showGlobalMessage for consistency and better UX
                    showGlobalMessage('Are you sure you want to delete this announcement? This action is irreversible.', 'confirm', async () => {
                        const id = e.currentTarget.dataset.id;
                        try {
                            // Corrected path for announcements_handler.php
                            const response = await fetch(`${window.BASE_URL_JS.replace('/public/', '')}/api/announcements_handler.php?id=${id}`, { method: 'DELETE' });
                            const data = await response.json();
                            if (data.success) {
                                showAdminMessage('Announcement deleted.', 'success');
                                fetchAdminAnnouncements();
                            } else {
                                showAdminMessage('Failed to delete announcement: ' + data.message, 'error');
                            }
                        } catch (error) {
                            console.error("Error deleting announcement:", error);
                            showAdminMessage('An error occurred while deleting the announcement.', 'error');
                        }
                    });
                });
            });
        }

        if (announcementForm) {
            announcementForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const title = announcementTitleInput.value;
                const content = announcementContentInput.value;
                const author_id = window.currentUserId; // Use Firebase UID as author_id

                if (!title || !content) {
                    showAdminMessage('Title and content are required.', 'error');
                    return;
                }

                try {
                    let response;
                    if (editingAnnouncementId) {
                        // Corrected path for announcements_handler.php
                        response = await fetch(`${window.BASE_URL_JS.replace('/public/', '')}/api/announcements_handler.php?id=${editingAnnouncementId}`, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ title, content })
                        });
                    } else {
                        // Corrected path for announcements_handler.php
                        response = await fetch(window.BASE_URL_JS.replace('/public/', '') + '/api/announcements_handler.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ title, content, author_id })
                        });
                    }
                    const data = await response.json();
                    if (data.success) {
                        showAdminMessage(data.message, 'success');
                        announcementForm.reset();
                        editingAnnouncementId = null;
                        announcementSubmitBtn.textContent = 'Add Announcement';
                        announcementCancelEditBtn.classList.add('hidden');
                        fetchAdminAnnouncements();
                    } else {
                        showAdminMessage('Operation failed: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error("Announcement form submission error:", error);
                    showAdminMessage('An error occurred during announcement operation.', 'error');
                }
            });
        }

        if (announcementCancelEditBtn) {
            announcementCancelEditBtn.addEventListener('click', () => {
                editingAnnouncementId = null;
                announcementForm.reset();
                announcementSubmitBtn.textContent = 'Add Announcement';
                announcementCancelEditBtn.classList.add('hidden');
            });
        }


        // --- Admin Testimonials Management ---
        async function fetchAdminTestimonials() {
            try {
                // Corrected path for testimonials_handler.php
                const response = await fetch(window.BASE_URL_JS.replace('/public/', '') + '/api/testimonials_handler.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                const testimonialsList = document.getElementById('testimonials-admin-list');
                if (testimonialsList) {
                    testimonialsList.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(testimonial => {
                            const testimonialDiv = document.createElement('div');
                            testimonialDiv.className = `bg-white p-4 rounded-lg shadow-sm border ${testimonial.approved ? 'border-green-200' : 'border-yellow-200'} flex justify-between items-center`;
                            testimonialDiv.innerHTML = `
                                <div>
                                    <p class="font-semibold text-gray-900">${testimonial.author}</p>
                                    <p class="text-sm text-gray-700 italic">"${testimonial.content}"</p>
                                    <p class="text-xs text-gray-500 mt-1">Submitted: ${new Date(testimonial.created_at).toLocaleString()}</p>
                                    <p class="text-xs text-gray-500">Status: ${testimonial.approved ? '<span class="text-green-600">Approved</span>' : '<span class="text-yellow-600">Pending Approval</span>'}</p>
                                </div>
                                <div class="flex space-x-2">
                                    ${!testimonial.approved ? `<button class="text-green-600 hover:text-green-800 approve-testimonial-btn" data-id="${testimonial.id}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>` : ''}
                                    <button class="text-red-600 hover:text-red-800 delete-testimonial-btn" data-id="${testimonial.id}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                    </button>
                                </div>
                            `;
                            testimonialsList.appendChild(testimonialDiv);
                        });
                        addTestimonialEventListeners();
                    } else {
                        testimonialsList.innerHTML = '<p class="text-gray-500 italic">No testimonials to manage.</p>';
                    }
                }
            } catch (error) {
                console.error("Error fetching testimonials:", error);
                showAdminMessage('Failed to load testimonials: ' + error.message, 'error');
            }
        }

        function addTestimonialEventListeners() {
            document.querySelectorAll('.approve-testimonial-btn').forEach(button => {
                button.addEventListener('click', async (e) => {
                    const id = e.currentTarget.dataset.id;
                    try {
                        // Corrected path for testimonials_handler.php
                        const response = await fetch(`${window.BASE_URL_JS.replace('/public/', '')}/api/testimonials_handler.php?id=${id}&action=approve`, { method: 'PUT' });
                        const data = await response.json();
                        if (data.success) {
                            showAdminMessage('Testimonial approved.', 'success');
                            fetchAdminTestimonials();
                            // Also refresh testimonials on landing page if it's open
                            if (typeof fetchTestimonials === 'function') {
                                fetchTestimonials();
                            }
                        } else {
                            showAdminMessage('Failed to approve testimonial: ' + data.message, 'error');
                        }
                    } catch (error) {
                        console.error("Error approving testimonial:", error);
                        showAdminMessage('An error occurred while approving the testimonial.', 'error');
                    }
                });
            });

            document.querySelectorAll('.delete-testimonial-btn').forEach(button => {
                button.addEventListener('click', async (e) => {
                    showGlobalMessage('Are you sure you want to delete this testimonial? This action is irreversible.', 'confirm', async () => {
                        const id = e.currentTarget.dataset.id;
                        try {
                            // Corrected path for testimonials_handler.php
                            const response = await fetch(`${window.BASE_URL_JS.replace('/public/', '')}/api/testimonials_handler.php?id=${id}`, { method: 'DELETE' });
                            const data = await response.json();
                            if (data.success) {
                                showAdminMessage('Testimonial deleted.', 'success');
                                fetchAdminTestimonials();
                                // Also refresh testimonials on landing page if it's open
                                if (typeof fetchTestimonials === 'function') {
                                    fetchTestimonials();
                                }
                            } else {
                                showAdminMessage('Failed to delete testimonial: ' + data.message, 'error');
                            }
                        } catch (error) {
                            console.error("Error deleting testimonial:", error);
                            showAdminMessage('An error occurred while deleting the testimonial.', 'error');
                        }
                    });
                });
            });
        }

        // --- Admin Users Management ---
        async function fetchAdminUsers() {
            try {
                // Corrected path for users_handler.php
                const response = await fetch(window.BASE_URL_JS.replace('/public/', '') + '/api/users_handler.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                const usersList = document.getElementById('users-admin-list');
                if (usersList) {
                    usersList.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(user => {
                            const userDiv = document.createElement('div');
                            userDiv.className = 'bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex justify-between items-center';
                            userDiv.innerHTML = `
                                <div>
                                    <p class="font-semibold text-gray-900">Email: ${user.email}</p>
                                    <p class="text-sm text-gray-700">ID: <span class="font-mono bg-gray-100 p-1 rounded text-xs">${user.firebase_uid}</span></p>
                                    <p class="text-sm text-gray-700">Role: <span class="font-bold ${user.role === 'admin' ? 'text-purple-600' : 'text-blue-600'}">${user.role}</span></p>
                                    <p class="text-xs text-gray-500 mt-1">Joined: ${new Date(user.created_at).toLocaleString()}</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button class="px-3 py-1 text-sm rounded-md bg-blue-100 text-blue-800 hover:bg-blue-200 transition duration-300 change-role-btn" data-uid="${user.firebase_uid}" data-current-role="${user.role}">
                                        Change Role to ${user.role === 'admin' ? 'Member' : 'Admin'}
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-user-btn" data-uid="${user.firebase_uid}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                    </button>
                                </div>
                            `;
                            usersList.appendChild(userDiv);
                        });
                        addUserEventListeners();
                    } else {
                        usersList.innerHTML = '<p class="text-gray-500 italic">No users to manage.</p>';
                    }
                }
            } catch (error) {
                console.error("Error fetching users:", error);
                showAdminMessage('Failed to load users: ' + error.message, 'error');
            }
        }

        function addUserEventListeners() {
            document.querySelectorAll('.change-role-btn').forEach(button => {
                button.addEventListener('click', async (e) => {
                    const uid = e.currentTarget.dataset.uid;
                    const currentRole = e.currentTarget.dataset.currentRole;
                    const newRole = currentRole === 'admin' ? 'member' : 'admin'; // Simple toggle

                    showGlobalMessage(`Are you sure you want to change role of user ${uid} to ${newRole}?`, 'confirm', async () => {
                        try {
                            // Corrected path for users_handler.php
                            const response = await fetch(`${window.BASE_URL_JS.replace('/public/', '')}/api/users_handler.php?firebase_uid=${uid}`, {
                                method: 'PUT',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ role: newRole })
                            });
                            const data = await response.json();
                            if (data.success) {
                                showAdminMessage(`User role updated to ${newRole}.`, 'success');
                                fetchAdminUsers();
                            } else {
                                showAdminMessage('Failed to update user role: ' + data.message, 'error');
                            }
                        } catch (error) {
                            console.error("Error changing user role:", error);
                            showAdminMessage('An error occurred while changing the user role.', 'error');
                        }
                    });
                });
            });

            document.querySelectorAll('.delete-user-btn').forEach(button => {
                button.addEventListener('click', async (e) => {
                    showGlobalMessage("Are you sure you want to delete this user? This action is irreversible for the database record.", 'confirm', async () => {
                        const uid = e.currentTarget.dataset.uid;
                        try {
                            // Corrected path for users_handler.php
                            const response = await fetch(`${window.BASE_URL_JS.replace('/public/', '')}/api/users_handler.php?firebase_uid=${uid}`, { method: 'DELETE' });
                            const data = await response.json();
                            if (data.success) {
                                showAdminMessage('User deleted from database. (Firebase Auth user still exists and needs manual deletion if desired.)', 'success');
                                fetchAdminUsers();
                            } else {
                                showAdminMessage('Failed to delete user: ' + data.message, 'error');
                            }
                        } catch (error) {
                            console.error("Error deleting user:", error);
                            showAdminMessage('An error occurred while deleting the user.', 'error');
                        }
                    });
                });
            });
        }


        // Initial load for admin panel
        document.addEventListener('firebaseAuthReady', () => {
            if (window.currentUserRole === 'admin') {
                // Attach tab button listeners only once, when admin content is ready
                if (!adminTabListenersAttached) {
                    const adminTabButtons = document.querySelectorAll('.admin-tab-button');
                    adminTabButtons.forEach(button => {
                        button.addEventListener('click', () => {
                            const tab = button.dataset.tab;
                            switchAdminTab(tab);
                        });
                    });
                    adminTabListenersAttached = true;
                }
                switchAdminTab(currentAdminTab); // Load initial tab
            }
        });
    }
});
