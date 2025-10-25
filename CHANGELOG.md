# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Coming Soon
- S3/Object Storage support for email archives
- Microsoft 365 Graph API integration
- RESTful API for programmatic access
- Mobile apps (iOS/Android)

---

## [1.0.0] - 2025-10-25

### 🎉 Initial Release

MailArchive 1.0.0 is here! A modern, self-hosted email archiving solution built for German tax compliance (GoBD).

### Added

#### Core Features
- **IMAP Email Archiving** - Automatic archiving from multiple IMAP accounts
- **GoBD Compliance** - Full compliance with German tax requirements
- **SHA256 Verification** - Cryptographic integrity verification for all emails
- **Compression** - Automatic gzip compression for large emails
- **Deduplication** - Smart deduplication of email attachments
- **Full-Text Search** - Lightning-fast search with Meilisearch/Scout
- **Audit Logging** - Complete audit trail of all actions

#### User Interface
- **Modern Dashboard** - Clean, intuitive dashboard with statistics
- **Email Browser** - Browse, search, and filter archived emails
- **Email Viewer** - View email content with attachments
- **Dark Mode** - Beautiful dark theme support
- **Responsive Design** - Works perfectly on all devices

#### IMAP Management
- **Multi-Account Support** - Connect unlimited IMAP accounts
- **Auto-Sync** - Configurable sync intervals (15min to weekly)
- **Connection Testing** - Test IMAP connections before saving
- **SSL/TLS Support** - Secure connections to mail servers
- **Delete After Archive** - Optional deletion of emails after archiving

#### Export & Compliance
- **GoBD Export** - One-click export to audit-ready ZIP archives
- **XML/CSV Metadata** - Machine-readable indexes for tax authorities
- **Date Range Exports** - Export specific time periods
- **User Exports** - Users can export their own emails
- **Integrity Hashes** - SHA256 hashes included for verification

#### Security
- **Two-Factor Authentication** - Optional 2FA for enhanced security
- **Role-Based Access Control** - Admin and user roles
- **Encrypted Storage** - IMAP credentials encrypted at rest
- **Session Management** - Secure session handling
- **Password Policies** - Strong password requirements

#### Technical
- **Laravel 12** - Built on the latest Laravel framework
- **React 19** - Modern, component-based UI
- **Inertia.js 2.0** - SPA experience with server-side rendering
- **TypeScript** - Type-safe frontend code
- **Tailwind CSS 4** - Utility-first styling
- **Pest Testing** - 72+ tests with comprehensive coverage

### Tech Stack
- PHP 8.3+
- Laravel 12
- React 19
- Inertia.js 2.0
- TypeScript
- Tailwind CSS 4
- MySQL 8.0+ / PostgreSQL 14+
- Meilisearch (optional)

---

## Version History

### Version Numbering

We use [Semantic Versioning](https://semver.org/):
- **MAJOR**: Breaking changes
- **MINOR**: New features (backwards compatible)
- **PATCH**: Bug fixes (backwards compatible)

### Release Cycle

- **Major releases**: Every 12-18 months
- **Minor releases**: Every 2-3 months
- **Patch releases**: As needed for bug fixes
- **Security patches**: Immediate

---

## How to Update

### Minor/Patch Updates

```bash
# Backup your database first!
# Then pull the latest changes
git pull origin main

# Update dependencies
composer install
npm install

# Run migrations
php artisan migrate

# Clear caches
php artisan optimize:clear

# Rebuild frontend
npm run build
```

### Major Updates

Major updates may require additional steps. Always check the [upgrade guide](UPGRADING.md) before updating.

---

## Support

For questions about specific releases:
- **Changelog**: https://github.com/yourusername/mailarchive/blob/main/CHANGELOG.md
- **Releases**: https://github.com/yourusername/mailarchive/releases
- **Issues**: https://github.com/yourusername/mailarchive/issues

---

[Unreleased]: https://github.com/yourusername/mailarchive/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/yourusername/mailarchive/releases/tag/v1.0.0
