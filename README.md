# 🚗 Tunisia Carpool — Ride-Sharing and Booking Platform

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?style=flat-square&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

A full-stack web application for ride-sharing and carpooling, featuring ride management, seat reservations, user profiles, notifications, and an administration dashboard.

---

## 📋 Table of Contents

- [About the Project](#-about-the-project)
- [Key Features](#-key-features)
- [Tech Stack](#-tech-stack)
- [Architecture](#-architecture)
- [Installation](#-installation)
- [Database Setup](#-database-setup)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [Project Structure](#-project-structure)
- [Security](#-security)
- [API Endpoints](#-api-endpoints)
- [Roadmap](#-roadmap)
- [Contributing](#-contributing)
- [License](#-license)
- [Author](#-author)
- [Acknowledgements](#-acknowledgements)

---

## 📖 About the Project

**Tunisia Carpool** is a web-based ride-sharing platform designed to connect drivers and passengers through an intuitive interface. It enables users to publish rides, search for available trips, reserve seats, and manage their bookings.

The project aims to encourage shared mobility by helping users coordinate journeys, reduce transportation costs, and make more efficient use of available vehicle seats.

### 🎯 Project Objectives

- Enable drivers to publish and manage their rides.
- Allow passengers to search for rides using multiple filters.
- Simplify seat reservations and booking management.
- Provide user profiles and activity tracking.
- Offer administrators tools for platform management and monitoring.
- Promote more affordable and sustainable transportation.

---

## ✨ Key Features

### 👤 User Authentication

- User registration and login.
- Email validation and password recovery.
- Password strength requirements.
- Login attempt rate limiting.
- Session management and timeout.
- CSRF protection for forms.

### 🚙 Ride Management

- Publish rides with departure, destination, date, time, price, and available seats.
- Manage vehicle information, including brand, model, year, and passenger capacity.
- Edit rides subject to validation rules.
- Delete rides and manage associated bookings.
- View and filter personal rides.

### 🔍 Search and Discovery

- Search by departure city and destination.
- Filter by date, available seats, and maximum price.
- Display ride details and driver information.
- Sort and explore available rides.

### 🛒 Booking System

- Add rides to a shopping cart.
- Select the number of seats to reserve.
- Validate seat availability before confirmation.
- Update available seats when bookings are confirmed.
- Track booking status and history.
- Cancel bookings according to configured rules.

### 👥 User Profiles

- View and edit personal information.
- Manage vehicles.
- View ride and booking history.
- Manage account credentials.
- Access personal activity information.

### 🔔 Notifications

- In-app notifications.
- Booking-related alerts.
- Notifications about ride changes.
- Unread notification tracking.
- Email notifications, when configured.

### 🛡️ Administration Dashboard

- View platform statistics.
- Manage user accounts.
- Moderate published rides.
- Monitor bookings.
- Display charts and reports.
- Review activity logs.
- Send system notifications, where supported.

---

## 🛠️ Tech Stack

### Backend

| Technology | Purpose |
|---|---|
| PHP 8.x | Server-side logic and application functionality |
| MySQL | Relational database management |
| Apache | Web server |
| PDO | Database access and prepared statements |

### Frontend

| Technology | Purpose |
|---|---|
| HTML5 | Page structure |
| CSS3 | Styling and layout |
| Bootstrap 5.3 | Responsive interface |
| JavaScript ES6 | Client-side interactivity |
| Font Awesome | Icons |
| Chart.js | Data visualization |

### Development Tools

| Tool | Purpose |
|---|---|
| XAMPP | Local development environment |
| phpMyAdmin | Database administration |
| Git | Version control |
| Composer | PHP dependency management, if required |

---

## 🏗️ Architecture

The application follows a simplified MVC-inspired architecture that separates the user interface, application logic, database access, and persistent data.

```text
┌──────────────────────────────────────────────┐
│              PRESENTATION LAYER              │
│             HTML / CSS / JavaScript          │
│                  Bootstrap                   │
└──────────────────────┬───────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────┐
│              APPLICATION LAYER               │
│                     PHP                      │
│                                              │
│  Authentication | Ride Management            │
│  Booking System | Administration             │
│  Notifications   | User Profiles              │
└──────────────────────┬───────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────┐
│               DATA ACCESS LAYER              │
│                      PDO                     │
│                                              │
│  Prepared Statements | Query Execution       │
│  Transactions        | Database Operations   │
└──────────────────────┬───────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────┐
│                 DATABASE LAYER               │
│                    MySQL                     │
│                                              │
│  Users | Rides | Vehicles | Bookings          │
│  Cart  | Reviews | Notifications | Logs       │
└──────────────────────────────────────────────┘
```


## ⚙️ Installation

### Prerequisites

- XAMPP or another compatible PHP development environment.
- PHP 8.x.
- MySQL or a compatible MariaDB version.
- A modern web browser.
- Git, if cloning the repository.

Download XAMPP from the [official website](https://www.apachefriends.org/).

### 1. Clone the Repository

```bash
git clone https://github.com/marwensellami/marketplace-covoiturage.git
cd marketplace-covoiturage
```

The repository URL and directory name must match your actual GitHub repository.

Alternatively, download the repository as a ZIP file and extract it into your XAMPP `htdocs` directory.

### 2. Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 3. Create the Database

Open phpMyAdmin:

http://localhost/phpmyadmin

Create a database named:

```sql
CREATE DATABASE covoiturage_tunisie
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

If the repository contains `config/database.sql`, import it through phpMyAdmin or use the MySQL command line:

```bash
mysql -u root -p covoiturage_tunisie < config/database.sql
```

Adapt the database name and SQL file path to match your actual configuration.

### 4. Configure the Database Connection

Open `config/db_connection.php` and configure the connection parameters according to your local environment.

Example:

```php
private $host = "localhost";
private $db_name = "covoiturage_tunisie";
private $username = "root";
private $password = "";
```

The empty password is appropriate only if your local MySQL account is configured that way. Use secure credentials in production.

### 5. Configure the Application URL

If your project directory is `marketplace-covoiturage` under XAMPP's `htdocs`, configure the application URL in `config/app_settings.php` if the application uses this setting:

```php
define('APP_URL', 'http://localhost/marketplace-covoiturage');
```

### 6. Configure Email Notifications

If email functionality is implemented, configure the SMTP settings using environment variables or an appropriate configuration file.

Do not commit real SMTP passwords, email credentials, or other secrets to GitHub.

### 7. Launch the Application

Open the following URL in your browser:

http://localhost/marketplace-covoiturage/

Replace `marketplace-covoiturage` with your actual project directory if it has a different name.

---

## 🗄️ Database Setup

The application uses a relational database to manage user accounts, rides, vehicles, reservations, and notifications.

The following tables describe the intended database structure. Confirm the names and relationships against your actual SQL schema.

| Table | Purpose |
|---|---|
| `users` | User accounts and profile information |
| `vehicles` | Vehicle information associated with users |
| `rides` | Published rides and trip details |
| `bookings` | Passenger reservations |
| `cart` | Selected rides awaiting booking confirmation |
| `reviews` | Ratings and reviews, if implemented |
| `notifications` | User notifications |
| `password_resets` | Password recovery records |
| `activity_logs` | Application activity and audit records |
| `scheduled_notifications` | Scheduled notification records, if implemented |

### Database Relationships

Typical relationships include:

- A user can publish multiple rides.
- A user can register multiple vehicles.
- A ride can be associated with a driver and a vehicle.
- A ride can have multiple bookings.
- A passenger can make multiple bookings.
- A user can receive multiple notifications.

The actual relationships depend on the database constraints defined in your schema.

### Administrator Account

Configure an administrator account using the application's intended account-creation or database-seeding process.

Do not publish default administrator passwords or real user credentials in this README.

---

## ⚙️ Configuration

Application settings may include:

- Application name and version.
- Base application URL.
- Database connection parameters.
- Session timeout.
- Login attempt limits.
- Password requirements.
- Ride capacity and pricing limits.
- File upload restrictions.
- Email notification settings.

Example configuration values, if supported by your implementation:

```php
define('APP_NAME', 'Tunisia Carpool');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/marketplace-covoiturage');
define('APP_TIMEZONE', 'Africa/Tunis');

define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 3600);
define('PASSWORD_MIN_LENGTH', 8);
```

Verify that these constants are compatible with the actual application code before adding them to your configuration.

---

## 🎮 Usage

### For Users

#### 1. Create an Account

1. Open the application homepage.
2. Navigate to the registration page.
3. Complete the required personal information.
4. Submit the registration form.
5. Log in using your account credentials.

#### 2. Publish a Ride

1. Sign in to your account.
2. Navigate to the ride publication page.
3. Add your vehicle information if required.
4. Enter the departure and destination details.
5. Select the departure date and time.
6. Specify the available seats and price.
7. Publish the ride.

#### 3. Search and Book a Ride

1. Open the ride search page.
2. Enter your departure city, destination, and travel date.
3. Apply additional filters as needed.
4. Open the details of a suitable ride.
5. Select the number of seats.
6. Add the ride to the cart if this feature is enabled.
7. Review and confirm the booking.

#### 4. Manage Your Activities

Use your profile or dashboard to view your rides, reservations, notifications, and personal information.

### For Administrators

Administrators can access the administration dashboard, subject to the application's role-based authorization rules, to manage user accounts, moderate rides, review platform activity, and consult available statistics.

---

## 📁 Project Structure

The following is an illustrative structure based on the described features. Adapt it to match the actual files in your repository.

```text
marketplace-covoiturage
├── admin                          # Administration (dashboard, user/ride management, reports, debug)
│    ├── admin_dashboard.php
│    ├── admin_reports.php
│    ├── debug_admin.php
│    ├── manage_rides.php
│    └── manage_users.php
├── assets                        # Static resources (CSS, JS)
│    ├── css
│    │    └── style.css
│    └── js
│         ├── script.js
│         ├── success-override.js
│         └── test-dropdown.js
├── auth                          # Authentication (login, register, logout, password reset)
│    ├── login.php
│    ├── logout.php
│    ├── password_reset.php
│    └── register.php
├── booking                       # Bookings (cart, confirmation, details, cancellation, driver/passenger bookings)
│    ├── add_to_cart.php
│    ├── booking_confirmation.php
│    ├── booking_details.php
│    ├── booking_success.php
│    ├── cancel_booking.php
│    ├── cart.php
│    ├── confirm_booking.php
│    ├── driver_bookings.php
│    ├── my_bookings.php
│    └── remove_from_cart.php
├── config                        # Configuration (app settings, DB connection, SQL script)
│    ├── app_settings.php
│    ├── database.sql
│    └── db_connection.php
├── includes                      # Shared includes (header, footer, functions, notifications)
│    ├── footer.php
│    ├── functions.php
│    ├── header.php
│    ├── notifications.php
│    └── notification_functions.php
├── rides                         # Rides (browse, create, edit, delete, deactivate, details, my rides)
│    ├── browse_rides.php
│    ├── create_ride.php
│    ├── deactivate_ride.php
│    ├── delete_ride.php
│    ├── edit_ride.php
│    ├── my_rides.php
│    └── ride_details.php
├── users                         # Users (profile, edit profile, dashboard, notifications)
│    ├── edit_profile.php
│    ├── notifications.php
│    ├── profile.php
│    └── user_dashboard.php
├── .gitignore
├── index.php
├── README.md
├── .LICENSE

```

---

## 🔒 Security

The following security measures should be implemented and verified against the actual application.

### Authentication and Authorization

- Password hashing using PHP's `password_hash()` and verification using `password_verify()`.
- Session ID regeneration after successful authentication.
- CSRF protection for state-changing requests.
- Rate limiting for repeated failed login attempts.
- Session expiration and secure logout.
- Role-based authorization for administrative operations.

### Database and Input Protection

- PDO prepared statements for parameterized database queries.
- Input validation and output escaping.
- Context-appropriate HTML escaping to reduce cross-site scripting risks.
- Email validation.
- Server-side authorization checks for sensitive operations.
- Database transactions for booking operations where appropriate.

### File Upload Protection

If the application supports file uploads:

- Validate file types and sizes on the server.
- Verify file content rather than relying exclusively on file extensions.
- Generate unique filenames.
- Restrict executable file uploads.
- Store uploaded files in an appropriately protected location.

**Security note:** These are recommended practices, not an independent security audit of the project.

---

## 📡 API Endpoints

The application is primarily organized around PHP pages and request handlers. If `includes/notifications.php` implements action-based notification handlers, the following table illustrates their intended functionality.

| Method | Action | Description |
|---|---|---|
| `GET` | `action=mark_read&id=X` | Mark a notification as read |
| `GET` | `action=mark_all_read` | Mark all notifications as read |
| `GET` | `action=get_unread_count` | Retrieve the unread notification count |
| `POST` | `action=send_ride_notification` | Trigger a ride-related notification |
| `POST` | `action=send_booking_notification` | Trigger a booking-related notification |
| `POST` | `action=system_broadcast` | Send an administrative announcement |

These are illustrative action names based on the proposed application structure. Check the actual implementation before treating them as available endpoints.

**Security recommendation:** Operations that modify data, such as marking notifications as read or sending notifications, should use appropriate HTTP methods, authorization checks, and CSRF protection where applicable.

---

## 🗺️ Roadmap

Potential future improvements include:

- [ ] Integrate interactive maps for displaying routes.
- [ ] Add real-time messaging between drivers and passengers.
- [ ] Integrate a secure payment provider.
- [ ] Develop a mobile application.
- [ ] Add Arabic, French, and English language support.
- [ ] Improve platform analytics and reporting.
- [ ] Support recurring rides.
- [ ] Introduce ride preferences and matching.
- [ ] Add additional driver verification features.

---

## 🤝 Contributing

Contributions, suggestions, and bug reports are welcome.

### Report a Bug

1. Check whether the issue has already been reported.
2. Describe the problem and the steps needed to reproduce it.
3. Include expected and actual behavior.
4. Add screenshots or error messages when useful.

### Submit Changes

```bash
git checkout -b feature/your-feature
git add .
git commit -m "feat: describe your changes"
git push origin feature/your-feature
```

Open a pull request from your branch if you are contributing to a repository that accepts external contributions.

### Code Style

- **PHP:** Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) where applicable.
- **JavaScript:** Prefer modern ES6+ syntax.
- **Git:** Use clear and descriptive commit messages.

---

## 📄 License

This project is distributed under the MIT License. See the [LICENSE](LICENSE) file for the complete license text.

If you use the MIT License, replace the copyright holder and year in the license file with the appropriate information before publishing.

---

## 👨‍💻 Author

**Marwen Sellami**

Engineering student in **Data Science** at the National School of Electronics and Telecommunications of Sfax (ENET'Com), Tunisia.

- **GitHub:** [@marwensellami](https://github.com/marwensellami)
- **LinkedIn:** [Marwen Sellami](https://www.linkedin.com/)
- **Mail:** marwen.sellami111@gmail.com
- **Location:** Sfax, Tunisia

For professional inquiries, please contact me through my LinkedIn profile.

---

## 🙏 Acknowledgements

- [PHP](https://www.php.net/) — Server-side programming.
- [MySQL](https://www.mysql.com/) — Relational database management.
- [Bootstrap](https://getbootstrap.com/) — Responsive interface components.
- [Font Awesome](https://fontawesome.com/) — Icon library.
- [Chart.js](https://www.chartjs.org/) — Data visualization.
- [XAMPP](https://www.apachefriends.org/) — Local development environment.

---

⭐ If you find this project useful, consider giving the repository a star!
