# Contributing to MailArchive

First off, thank you for considering contributing to MailArchive! It's people like you that make MailArchive such a great tool.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Coding Guidelines](#coding-guidelines)
- [Submitting Changes](#submitting-changes)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Enhancements](#suggesting-enhancements)

---

## Code of Conduct

This project and everyone participating in it is governed by our [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to conduct@mailarchive.example.com.

---

## How Can I Contribute?

### 🐛 Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

**Bug Report Template:**
```markdown
**Describe the bug**
A clear and concise description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

**Expected behavior**
A clear and concise description of what you expected to happen.

**Screenshots**
If applicable, add screenshots to help explain your problem.

**Environment:**
 - OS: [e.g. Ubuntu 22.04]
 - PHP Version: [e.g. 8.3.0]
 - Laravel Version: [e.g. 12.0]
 - Browser: [e.g. Chrome 120]

**Additional context**
Add any other context about the problem here.
```

### 💡 Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, include:

**Feature Request Template:**
```markdown
**Is your feature request related to a problem?**
A clear and concise description of what the problem is.

**Describe the solution you'd like**
A clear and concise description of what you want to happen.

**Describe alternatives you've considered**
A clear and concise description of any alternative solutions or features you've considered.

**Additional context**
Add any other context or screenshots about the feature request here.
```

### 🔧 Pull Requests

We actively welcome your pull requests:

1. Fork the repo and create your branch from `main`
2. If you've added code that should be tested, add tests
3. If you've changed APIs, update the documentation
4. Ensure the test suite passes
5. Make sure your code follows our coding standards
6. Issue that pull request!

---

## Development Setup

### Prerequisites

- PHP 8.3+
- Composer 2.0+
- Node.js 20+
- MySQL 8.0+ or PostgreSQL 14+

### Local Development

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/mailarchive.git
cd mailarchive

# Add upstream remote
git remote add upstream https://github.com/ORIGINAL_OWNER/mailarchive.git

# Install dependencies
composer install
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Configure database in .env
# Then run migrations
php artisan migrate

# Build frontend
npm run dev

# Run tests
php artisan test
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/EmailArchivingTest.php

# Run specific test
php artisan test --filter="email parser receives and archives email"
```

---

## Coding Guidelines

### PHP (Backend)

We follow **PSR-12** coding standards, enforced by Laravel Pint:

```bash
# Format your code
vendor/bin/pint

# Check only (don't fix)
vendor/bin/pint --test
```

**Key Guidelines:**
- Use type declarations for all function parameters and return types
- Use PHP 8.3+ features (constructor property promotion, match expressions, etc.)
- Write descriptive variable and function names
- Keep functions small and focused
- Document complex logic with comments
- Use dependency injection over facades

**Example:**
```php
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
    ]);

    $user = User::create($validated);

    return redirect()->route('users.show', $user);
}
```

### JavaScript/TypeScript (Frontend)

We use **Prettier** and **ESLint** for code formatting:

```bash
# Format code
npm run format

# Check formatting
npm run format:check
```

**Key Guidelines:**
- Use TypeScript for type safety
- Use functional components with hooks
- Keep components small and reusable
- Use descriptive prop names
- Extract complex logic into custom hooks
- Avoid inline styles, use Tailwind classes

**Example:**
```tsx
type Props = {
    user: {
        name: string;
        email: string;
    };
};

export default function UserCard({ user }: Props) {
    return (
        <div className="rounded-lg border p-4">
            <h3 className="font-semibold">{user.name}</h3>
            <p className="text-neutral-600">{user.email}</p>
        </div>
    );
}
```

### Testing

**Backend (Pest):**
```php
test('user can create account', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    assertDatabaseHas('users', ['email' => 'john@example.com']);
});
```

**Guidelines:**
- Write tests for all new features
- Test both happy paths and edge cases
- Use descriptive test names
- Keep tests isolated (no dependencies between tests)
- Use factories for test data

### Database

**Migrations:**
- Never modify existing migrations that have been deployed
- Use descriptive migration names
- Include both `up()` and `down()` methods
- Add indexes for foreign keys and frequently queried columns

**Models:**
```php
class Email extends Model
{
    protected $fillable = [
        'subject',
        'from_address',
        'to_addresses',
    ];

    protected function casts(): array
    {
        return [
            'to_addresses' => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
```

### Git Commit Messages

Follow conventional commits format:

```
type(scope): subject

body (optional)

footer (optional)
```

**Types:**
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation changes
- `style:` Code style changes (formatting, etc.)
- `refactor:` Code refactoring
- `test:` Adding or updating tests
- `chore:` Maintenance tasks

**Examples:**
```bash
feat(email): add full-text search with Meilisearch

fix(auth): resolve session timeout issue

docs(readme): update installation instructions

refactor(dashboard): extract stats calculation to service

test(imap): add tests for IMAP connection handling
```

---

## Submitting Changes

### Before Submitting

1. **Run tests**: `php artisan test`
2. **Format code**: `vendor/bin/pint` and `npm run format`
3. **Update documentation** if needed
4. **Test manually** in the browser
5. **Check for console errors**

### Pull Request Process

1. **Update your fork:**
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

2. **Create a feature branch:**
   ```bash
   git checkout -b feature/amazing-feature
   ```

3. **Make your changes and commit:**
   ```bash
   git add .
   git commit -m "feat: add amazing feature"
   ```

4. **Push to your fork:**
   ```bash
   git push origin feature/amazing-feature
   ```

5. **Create Pull Request** on GitHub with:
   - Clear title and description
   - Reference related issues
   - Screenshots/videos for UI changes
   - List of changes made

### PR Review Process

- Maintainers will review your PR within 1-2 weeks
- Address any requested changes
- Once approved, a maintainer will merge it
- Your contribution will be credited in the release notes

---

## Development Tips

### Debugging

**Backend:**
```php
// Use ray() for debugging (requires ray.so)
ray($variable);

// Use logs
Log::info('Debug info', ['data' => $variable]);

// Use dd() / dump()
dd($variable);
```

**Frontend:**
```tsx
// Console logging
console.log('Debug:', data);

// React DevTools (browser extension)
// Vue DevTools for inspecting components
```

### Database Queries

```bash
# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh migrations (WARNING: destroys data)
php artisan migrate:fresh

# Seed database
php artisan db:seed
```

### Cache Clearing

```bash
# Clear all caches
php artisan optimize:clear

# Or individually:
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

---

## Getting Help

- **GitHub Discussions**: Ask questions and share ideas
- **GitHub Issues**: Report bugs and request features
- **Discord**: Join our community (link coming soon)

---

## Recognition

Contributors will be recognized in:
- Our [CONTRIBUTORS.md](CONTRIBUTORS.md) file
- Release notes
- Project website (coming soon)

---

## License

By contributing, you agree that your contributions will be licensed under the same license as the project (Non-Commercial Open Source License).

---

**Thank you for contributing to MailArchive! 🎉**
