# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability, please send an email to:
**security@mailarchive.example.com**

### What to Include

Please include the following information in your report:

- **Type of vulnerability** (e.g., XSS, SQL injection, authentication bypass)
- **Full paths of affected source files**
- **Location of the affected source code** (tag/branch/commit or direct URL)
- **Step-by-step instructions to reproduce the issue**
- **Proof-of-concept or exploit code** (if possible)
- **Impact of the vulnerability** (what an attacker could do)

### What to Expect

- **Acknowledgment**: We will acknowledge receipt of your vulnerability report within 48 hours
- **Assessment**: We will assess the vulnerability and determine its severity within 5 business days
- **Fix Development**: We will work on a fix and keep you updated on progress
- **Disclosure**: We will coordinate with you on public disclosure timing
- **Credit**: We will credit you in the security advisory (unless you prefer to remain anonymous)

## Security Update Process

1. **Reported**: Security issue reported to security@mailarchive.example.com
2. **Confirmed**: We confirm the vulnerability and assess severity
3. **Fix Developed**: We develop and test a fix
4. **Patch Released**: We release a security patch
5. **Advisory Published**: We publish a security advisory with details
6. **Credits Given**: We credit the reporter (if they wish)

## Security Best Practices

When deploying MailArchive, please follow these security best practices:

### Environment Configuration

```env
# Use strong, random application key
APP_KEY=base64:RANDOM_32_CHARACTER_STRING

# Force HTTPS in production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Use secure session configuration
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict

# Secure database credentials
DB_PASSWORD=STRONG_RANDOM_PASSWORD
```

### Server Configuration

- **Use HTTPS**: Always use SSL/TLS certificates
- **Firewall Rules**: Restrict database access to application servers only
- **Regular Updates**: Keep PHP, Laravel, and all dependencies up to date
- **File Permissions**: Set proper file permissions (755 for directories, 644 for files)
- **Disable Directory Listing**: Prevent directory browsing
- **Rate Limiting**: Enable rate limiting on authentication routes

### Application Security

- **Strong Passwords**: Enforce strong password requirements
- **Two-Factor Authentication**: Enable 2FA for admin accounts
- **Regular Backups**: Implement automated backup strategy
- **Audit Logging**: Monitor audit logs for suspicious activity
- **Access Control**: Use role-based access control (RBAC)
- **Input Validation**: All user input is validated server-side

### Database Security

- **Encrypted Connections**: Use SSL/TLS for database connections
- **Principle of Least Privilege**: Database user should have minimal required permissions
- **Regular Backups**: Automated daily backups with encryption
- **Sensitive Data**: Sensitive fields (passwords, tokens) are encrypted at rest

### IMAP Credentials

IMAP passwords are encrypted in the database using Laravel's encryption:

```php
protected function casts(): array
{
    return [
        'password' => 'encrypted',
    ];
}
```

**Important**: Never commit `.env` files or expose `APP_KEY`

## Known Security Considerations

### IMAP Credentials Storage

- IMAP credentials are encrypted at rest using Laravel's encryption
- Credentials are decrypted in memory only when needed
- Consider using OAuth2 where supported by mail providers

### Email Content

- All emails are stored with SHA256 hash verification
- Raw email content is stored compressed (gzip)
- Emails are immutable once archived (tamper detection)

### Access Control

- Users can only access emails where they are sender or recipient
- Admin accounts have full access to statistics but not email content
- Role-based access control prevents privilege escalation

## Security Advisories

Security advisories will be published at:
https://github.com/philharmonie/mailarchive/security/advisories

Subscribe to our security announcements:
- **GitHub Watch**: Watch the repository for security advisories
- **Email**: Subscribe at security-announce@mailarchive.example.com

## Bug Bounty Program

We currently do not have a bug bounty program, but we deeply appreciate security researchers who responsibly disclose vulnerabilities. Contributors who report valid security issues will be:

- Credited in security advisories
- Acknowledged in release notes
- Listed in our security hall of fame (coming soon)

## Compliance

MailArchive is designed to comply with:

- **GoBD** (Grundsätze zur ordnungsmäßigen Führung und Aufbewahrung von Büchern)
- **GDPR** (General Data Protection Regulation)
- **BSI IT-Grundschutz** (German IT security standards)

For compliance questions, contact: compliance@mailarchive.example.com

## Security Contacts

- **General Security Issues**: security@mailarchive.example.com
- **PGP Key**: Available at https://keybase.io/mailarchive
- **GitHub Security**: Use GitHub's private vulnerability reporting

## Attribution

This security policy is based on best practices from:
- OWASP Security Guidelines
- Laravel Security Best Practices
- GitHub Security Policy Templates
