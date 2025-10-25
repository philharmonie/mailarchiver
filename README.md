# MailArchive - Email Archiving System

[![License](https://img.shields.io/badge/License-Non--Commercial-blue.svg)](LICENSE.md)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?logo=react)](https://react.dev)
[![Inertia](https://img.shields.io/badge/Inertia-2.0-9553E9)](https://inertiajs.com)

An open-source email archiving system built with Laravel and React. This project aims to provide GoBD-compliant email archiving for German businesses.

**⚠️ Project Status**: This is a work in progress. GoBD compliance is a goal, not yet fully implemented. Contributions are welcome!

---

## Features

### Email Archiving
- **IMAP Integration** - Connect multiple IMAP accounts (Gmail, Outlook, custom servers)
- **Automatic Syncing** - Scheduled email fetching with configurable intervals (15min, hourly, daily, weekly)
- **Deduplication** - Attachment deduplication to reduce storage usage
- **Compression** - Automatic gzip compression for emails over 10KB
- **BCC Mapping** - Handles internal vs. external email classification

### Security & Data Integrity
- **SHA256 Checksums** - Every email stored with cryptographic hash
- **Tamper Detection** - Hash verification to detect modifications
- **Encrypted Credentials** - IMAP passwords encrypted at rest using Laravel encryption
- **Audit Logging** - Database logging of all system actions
- **Role-Based Access** - Admin and user roles with different permissions

### Search & Access
- **Full-Text Search** - Optional Meilisearch integration for fast searching
- **Database Search** - Fallback search using MySQL/PostgreSQL full-text search
- **Filtering** - Filter emails by sender, recipient, subject, date range
- **Access Control** - Users can only access emails where they are sender or recipient

### Export
- **Individual Downloads** - Download emails as .eml files
- **Bulk Export** - Export all emails or date ranges as ZIP archives
- **XML Metadata** - Exports include XML index files
- **CSV Support** - Machine-readable CSV format included in exports

### User Interface
- **Dark Mode** - Light/dark theme support
- **Responsive Design** - Works on desktop, tablet, and mobile
- **TypeScript** - Type-safe React frontend with Inertia.js
- **Real-Time Stats** - Dashboard with email counts and storage statistics

---

## Installation

### Prerequisites

- PHP 8.3+
- Composer 2.0+
- Node.js 20+
- MySQL 8.0+ or PostgreSQL 14+
- Meilisearch (optional, for full-text search)

### Setup

```bash
# Clone repository
git clone https://github.com/philharmonie/mailarchive.git
cd mailarchive

# Install dependencies
composer install
npm install

# Environment configuration
cp .env.example .env
php artisan key:generate

# Configure database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_DATABASE=mailarchive
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations with test user
php artisan migrate --seed

# Build frontend
npm run build

# Start server
php artisan serve
```

Login at `http://localhost:8000`:
- Email: `test@example.com`
- Password: `password`

**⚠️ Change the default password immediately!**

### Optional: Meilisearch Setup

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

### Production

```bash
# Optimize
composer install --optimize-autoloader --no-dev
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue worker
php artisan queue:work --daemon

# Scheduler (add to crontab)
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Configuration

### IMAP Accounts

1. Navigate to **IMAP Accounts** (admin only)
2. Add account with credentials:
   - **Host**: `imap.gmail.com:993` (Gmail), `outlook.office365.com:993` (Outlook), or custom
   - **Username**: Email address
   - **Password**: IMAP password or app-specific password
   - **SSL**: Enabled for port 993
3. Set sync interval
4. Test connection

### BCC Map (Optional)

To properly classify internal emails, create a BCC map table in your database listing internal email addresses.

---

## Usage

### Admin Functions
- Add/edit/delete IMAP accounts
- View system-wide statistics
- Access audit logs
- View all accounts and email counts

### User Functions
- Search and filter archived emails
- View email content and attachments
- Download individual emails as .eml files
- Export emails to ZIP archives

---

## Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=EmailArchivingTest

# With coverage (requires Xdebug)
php artisan test --coverage
```

---

## Contributing

Contributions are welcome! This project aims to build a robust, GoBD-compliant email archiving solution.

### How to Contribute

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes
4. Run tests: `php artisan test`
5. Run linter: `vendor/bin/pint`
6. Commit: `git commit -m 'Add feature'`
7. Push: `git push origin feature/your-feature`
8. Open a Pull Request

### Development Guidelines

- Follow PSR-12 coding standards (enforced by Pint)
- Write tests for new features
- Update documentation
- Keep commits atomic and descriptive
- All tests must pass

### Areas Needing Work

- **GoBD Compliance Certification** - Legal review and certification
- **Retention Policies** - Automatic deletion after retention periods
- **Advanced Export Formats** - MBOX, PST support
- **Email Parsing** - Improved handling of edge cases
- **Performance** - Optimization for large archives (millions of emails)
- **Documentation** - User guides, API documentation

---

## Roadmap

### GoBD Compliance (Priority)
- [ ] Legal review of compliance requirements
- [ ] Implement retention period enforcement
- [ ] Enhanced audit trail with complete change history
- [ ] Export format validation and certification
- [ ] Documentation for tax auditors

### Features
- [ ] S3/Object storage backend
- [ ] Microsoft 365 Graph API integration
- [ ] Multi-tenancy support
- [ ] RESTful API
- [ ] Webhook notifications
- [ ] Advanced search operators

### Technical Improvements
- [ ] Horizontal scaling support
- [ ] Read replicas for search
- [ ] Background job optimization
- [ ] Monitoring and alerting
- [ ] Backup/restore functionality

---

## Tech Stack

- **Backend**: Laravel 12, PHP 8.3
- **Frontend**: React 19, TypeScript, Inertia.js 2.0, Tailwind CSS 4
- **Database**: MySQL 8.0+ / PostgreSQL 14+
- **Search**: Meilisearch (optional) or database full-text search
- **Queue**: Laravel Queue (database/redis)
- **Email**: Webklex PHP-IMAP library

---

## License

This project is licensed under the **Non-Commercial Open Source License**.

**Permitted**:
- Personal, educational, or internal business use
- Modification and adaptation
- Study and learning

**Prohibited**:
- Commercial sale or SaaS offerings
- Use in commercial products without permission
- Removing license/copyright notices

See [LICENSE.md](LICENSE.md) for details. For commercial licensing, please contact the project maintainers.

---

## Security

Please report security vulnerabilities privately via GitHub Security Advisories or email the project maintainers. Do not open public issues for security bugs.

See [SECURITY.md](SECURITY.md) for details.

---

## Acknowledgments

- [Laravel](https://laravel.com) - PHP framework
- [Inertia.js](https://inertiajs.com) - Modern monolith approach
- [shadcn/ui](https://ui.shadcn.com) - UI components
- [Webklex/php-imap](https://github.com/Webklex/php-imap) - IMAP library

---

## Support

- **Issues**: [GitHub Issues](https://github.com/philharmonie/mailarchive/issues)
- **Discussions**: Use issues for questions and feature requests
