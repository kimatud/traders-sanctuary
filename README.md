# 🏦 Traders Sanctuary - Forex Trading Platform (React, PHP & MySQL)

[![React](https://img.shields.io/badge/Frontend-React-61DAFB?style=flat-square&logo=react&logoColor=white)](https://react.dev/)
[![PHP](https://img.shields.io/badge/Backend-PHP-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/Database-MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](https://opensource.org/licenses/MIT)
[![Theme Modes](https://img.shields.io/badge/Themes-Light%20%7C%20Dark%20%7C%20System-blueviolet?style=flat-square)](#-features)
![Status](https://img.shields.io/badge/Status-Active-success?style=flat-square)

Traders Sanctuary is a **modern, single-page React application** with a PHP backend, designed for **Forex traders** who want an all-in-one platform for charts, education, community, and premium trading tools.  

It supports **multiple themes** — Light, Dark, and System — for a personalized user experience.

Build handoff page: [`build-process-template.html`](build-process-template.html)

---

## 📸 Site Preview (Dark Mode)

The screenshots follow the scroll flow from the **Landing Page → Contact Page → Login → Dashboard → Premium Trading Journal → Admin Panel**.

<p align="center"> 
  <img src="screenshots/Screenshot (305).png" width="700" alt="Landing Page"/> 
  <img src="screenshots/Screenshot (306).png" width="700" alt="Landing Page"/> 
  <img src="screenshots/Screenshot (307).png" width="700" alt="Landing Page"/> 
  <img src="screenshots/Screenshot (308).png" width="700" alt="Landing Page"/> 
  <img src="screenshots/Screenshot (309).png" width="700" alt="Contact Page"/> 
  <img src="screenshots/Screenshot (310).png" width="700" alt="Login/Register"/> 
  <img src="screenshots/Screenshot (311).png" width="700" alt="User Dashboard"/> 
  <img src="screenshots/Screenshot (312).png" width="700" alt="Premium Journal"/> 
  <img src="screenshots/Screenshot (313).png" width="700" alt="Premium Journal"/> 
  <img src="screenshots/Screenshot (314).png" width="700" alt="Admin Dashboard"/> 
</p>

---

## ✨ Features

### 🌐 Public Pages
- **Landing Page** — Eye-catching hero section, quick platform overview, live features showcase.
- **Contact Page** — Users can send inquiries directly via a PHP-powered form.

### 🔐 Authentication
- **Login & Registration** with secure password handling.
- Firebase Authentication for client-side session management.
- Password reset functionality.

### 📊 User Dashboard
- Live **TradingView chart embed** for real-time market data.
- Announcements section & trading education resources.
- **Premium Trading Journal** (locked for free users).

### 🛠 Admin Panel
- Manage users, announcements, and testimonials.
- Update user roles (Member / Premium / Admin).
- Approve or remove user-submitted testimonials.

### 🎨 Theme Modes
- **Light Mode**
- **Dark Mode**
- **System Mode** (auto-switches based on OS settings)

---

## 🖥 Technology Stack

**Frontend**
- React (Single Page Application)
- Tailwind CSS (responsive, modern UI)
- JavaScript (Vanilla for some client interactions)

**Backend**
- PHP
- MySQL (MariaDB)
- PHPMailer (email integration)

**Other**
- Firebase Authentication
- TradingView API Embed

---

## 🚀 Setup & Installation

### Prerequisites
- Node.js & npm
- PHP 7.4+ (with `php-curl`, `php-pdo_mysql`, `php-json`, `php-mbstring`)
- MySQL database
- Composer (for PHPMailer)
- Firebase project (Google Cloud Console)

📜 License This project is licensed under the MIT License — feel free to modify and distribute.

Developer: Dennis Kimatu (@kimatud)

### Installation
```bash
# Clone repository
git clone https://github.com/kimatud/traders-sanctuary.git
cd traders-sanctuary

# Install frontend dependencies
npm install

# Install backend dependencies
composer install
