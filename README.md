# 📧 MailArchive - GoBD-Compliant Email Archiving System

[![License](https://img.shields.io/badge/License-Non--Commercial-blue.svg)](LICENSE.md)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?logo=react)](https://react.dev)
[![Inertia](https://img.shields.io/badge/Inertia-2.0-9553E9)](https://inertiajs.com)

> **Professional email archiving made simple.** A modern, self-hosted email archiving solution built for German tax compliance (GoBD) with enterprise-grade features and a beautiful user interface.

---

## ✨ Why MailArchive?

MailArchive is the **only open-source email archiving solution** specifically designed for **German tax compliance (GoBD)**. Whether you're a small business, freelancer, or enterprise, MailArchive ensures your emails are properly archived, tamper-proof, and audit-ready.

### 🎯 Perfect For

- **German Businesses** requiring GoBD-compliant email archiving
- **Tax Consultants** managing multiple client accounts
- **Law Firms** needing secure, immutable email records
- **Healthcare Providers** requiring GDPR-compliant email storage
- **Freelancers** who need professional email archiving on a budget

---

## 🚀 Key Features

### 📋 GoBD Compliance
- ✅ **Vollständigkeit** - Complete archiving of all emails
- ✅ **Unveränderbarkeit** - Tamper-proof with SHA256 checksums
- ✅ **Nachvollziehbarkeit** - Full audit trail with timestamps
- ✅ **Maschinelle Auswertung** - XML/CSV exports for tax audits
- ✅ **Lesbarkeit** - Standard .eml format readable in any email client

### 🔐 Security & Integrity
- **SHA256 Hash Verification** - Every email is cryptographically verified
- **Tamper Detection** - Automatic detection of modified emails
- **Audit Logging** - Complete history of all actions
- **Role-Based Access Control** - Admin and user roles with fine-grained permissions
- **Data Compression** - Automatic gzip compression for large emails

### 📨 Email Management
- **Multi-Account Support** - Connect multiple IMAP accounts (Gmail, Outlook, etc.)
- **Automatic Archiving** - Scheduled fetching via configurable intervals
- **Full-Text Search** - Lightning-fast search with Meilisearch/Scout
- **Smart Filtering** - Filter by sender, recipient, date, and more
- **Attachment Deduplication** - Save storage with intelligent deduplication
- **BCC Map Support** - Proper handling of internal vs. external emails

### 📤 Export & Compliance
- **One-Click GoBD Export** - Generate audit-ready ZIP archives
- **XML/CSV Metadata** - Machine-readable indexes for tax authorities
- **Integrity Verification** - Includes SHA256 hashes for verification
- **Date Range Exports** - Export specific time periods
- **User-Specific Exports** - Users can export their own emails

### 🎨 Modern User Experience
- **Beautiful Dark Mode** - Easy on the eyes, works perfectly in dark environments
- **Responsive Design** - Works flawlessly on desktop, tablet, and mobile
- **Real-Time Updates** - See archiving progress in real-time
- **Intuitive Interface** - No training required, just works
- **Type-Safe Frontend** - Built with TypeScript and React 19

---

## 📸 Screenshots

> *Screenshots coming soon - see it in action!*

<!--
![Dashboard](docs/screenshots/dashboard.png)
![Email List](docs/screenshots/emails.png)
![IMAP Accounts](docs/screenshots/imap-accounts.png)
-->

---

## 🛠️ Tech Stack

### Backend
- **[Laravel 12](https://laravel.com)** - The PHP framework for web artisans
- **[Laravel Fortify](https://laravel.com/docs/fortify)** - Frontend-agnostic authentication
- **[Laravel Scout](https://laravel.com/docs/scout)** - Full-text search with Meilisearch
- **[webklex/php-imap](https://github.com/Webklex/php-imap)** - IMAP library for PHP
- **MySQL/PostgreSQL** - Rock-solid database options

### Frontend
- **[React 19](https://react.dev)** - Modern, component-based UI
- **[Inertia.js 2.0](https://inertiajs.com)** - The modern monolith
- **[TypeScript](https://typescriptlang.org)** - Type safety for JavaScript
- **[Tailwind CSS 4](https://tailwindcss.com)** - Utility-first CSS framework
- **[shadcn/ui](https://ui.shadcn.com)** - Beautiful, accessible components
- **[Laravel Wayfinder](https://github.com/claudiodekker/laravel-wayfinder)** - Type-safe routing

### Testing & Quality
- **[Pest](https://pestphp.com)** - Delightful PHP testing framework
- **[Laravel Pint](https://laravel.com/docs/pint)** - Opinionated PHP code formatter
- **72+ Tests** - Comprehensive test coverage

---

## 📦 Installation

### Prerequisites

- **PHP 8.3+** with required extensions (mbstring, pdo, openssl, etc.)
- **Composer 2.0+**
- **Node.js 20+** and npm
- **MySQL 8.0+** or **PostgreSQL 14+**
- **Meilisearch** (optional, for full-text search)

### Quick Start

```bash
# Clone the repository
git clone https://github.com/yourusername/mailarchive.git
cd mailarchive

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=mailarchive
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations
php artisan migrate

# Build frontend assets
npm run build

# Start the development server
php artisan serve
```

Visit `http://localhost:8000` and create your admin account!

### Production Deployment

For production, we recommend using **[Laravel Forge](https://forge.laravel.com)** or **[Ploi](https://ploi.io)** for zero-configuration deployment.

<details>
<summary>Manual Production Setup</summary>

```bash
# Optimize for production
composer install --optimize-autoloader --no-dev
npm run build

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set up queue worker
php artisan queue:work --daemon

# Set up scheduler (add to crontab)
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

</details>

---

## ⚙️ Configuration

### IMAP Setup

1. Navigate to **IMAP Accounts** in the admin panel
2. Click **Add Account**
3. Enter your IMAP credentials:
   - **Gmail**: `imap.gmail.com:993` (SSL)
   - **Outlook**: `outlook.office365.com:993` (SSL)
   - **Custom**: Your IMAP server details
4. Configure sync interval (15min, hourly, daily, etc.)
5. Test the connection and save

### Meilisearch (Optional)

For lightning-fast full-text search, install Meilisearch:

```bash
# Install Meilisearch
curl -L https://install.meilisearch.com | sh

# Start Meilisearch
./meilisearch --master-key=YOUR_MASTER_KEY

# Configure in .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=YOUR_MASTER_KEY

# Index existing emails
php artisan scout:import "App\Models\Email"
```

### Scheduled Archiving

MailArchive automatically archives emails based on the sync interval configured for each IMAP account. The scheduler runs every 15 minutes and checks which accounts need syncing.

---

## 📖 Usage

### For Admins

1. **Add IMAP Accounts** - Configure email accounts to archive
2. **Monitor Dashboard** - See real-time statistics and top accounts
3. **Manage Users** - Create user accounts for email access
4. **Review Audit Logs** - Full transparency of all actions

### For Users

1. **Browse Emails** - Search and filter your archived emails
2. **View Details** - Read email content, view attachments
3. **Download .eml Files** - Export individual emails
4. **GoBD Export** - Generate compliant ZIP archives for tax audits

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=EmailArchivingTest

# Run with coverage (requires Xdebug)
php artisan test --coverage
```

---

## 🤝 Contributing

We love contributions! Here's how you can help:

1. **Fork the repository**
2. **Create a feature branch** (`git checkout -b feature/amazing-feature`)
3. **Commit your changes** (`git commit -m 'Add amazing feature'`)
4. **Push to the branch** (`git push origin feature/amazing-feature`)
5. **Open a Pull Request**

### Development Guidelines

- Follow **PSR-12** coding standards (enforced by Pint)
- Write **tests** for new features
- Update **documentation** as needed
- Keep commits **atomic** and descriptive
- Ensure all tests pass before submitting

---

## 📄 License

This project is licensed under the **Non-Commercial Open Source License**.

**You are free to:**
- ✅ Use the software for personal, educational, or internal business purposes
- ✅ Modify and adapt the code for your needs
- ✅ Study and learn from the codebase

**You may NOT:**
- ❌ Sell the software or offer it as a commercial service
- ❌ Use the software in a commercial product without permission
- ❌ Remove or modify the license or copyright notices

For commercial licensing inquiries, please contact us.

See [LICENSE.md](LICENSE.md) for full details.

---

## 🙏 Acknowledgments

- **[Laravel](https://laravel.com)** - For making PHP development a joy
- **[Inertia.js](https://inertiajs.com)** - For the perfect SPA experience
- **[shadcn/ui](https://ui.shadcn.com)** - For beautiful UI components
- **[Webklex](https://github.com/Webklex)** - For the excellent IMAP library
- **German Tax Authorities** - For GoBD compliance requirements that inspired this project

---

## 📞 Support

- **Documentation**: [Coming Soon]
- **Issues**: [GitHub Issues](https://github.com/yourusername/mailarchive/issues)
- **Discussions**: [GitHub Discussions](https://github.com/yourusername/mailarchive/discussions)
- **Email**: support@mailarchive.example.com

---

## 🗺️ Roadmap

- [ ] **S3/Object Storage Support** - Archive to cloud storage
- [ ] **Microsoft 365 Integration** - Native Graph API support
- [ ] **Advanced Search Filters** - More granular search options
- [ ] **Multi-Tenancy** - Multiple organizations in one installation
- [ ] **Email Templates** - Customizable export templates
- [ ] **RESTful API** - Programmatic access to archives
- [ ] **Mobile Apps** - Native iOS and Android apps
- [ ] **Backup/Restore** - Built-in backup functionality

---

## ⭐ Star History

If you find MailArchive useful, please consider giving it a star! It helps us grow and improve.

[![Star History Chart](https://api.star-history.com/svg?repos=yourusername/mailarchive&type=Date)](https://star-history.com/#yourusername/mailarchive&Date)

---

<div align="center">

**Built with ❤️ for the Laravel community**

[Website](https://mailarchive.example.com) • [Documentation](https://docs.mailarchive.example.com) • [Twitter](https://twitter.com/mailarchive)

</div>
