# Contributing to SEO-Forge Professional

Thank you for your interest in contributing to SEO-Forge Professional! This document provides guidelines and information for contributors.

## 🤝 How to Contribute

### Reporting Bugs
1. Check existing issues to avoid duplicates
2. Use the bug report template
3. Provide detailed reproduction steps
4. Include environment information

### Suggesting Features
1. Check existing feature requests
2. Use the feature request template
3. Explain the use case and benefits
4. Consider implementation complexity

### Code Contributions
1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes following our coding standards
4. Add tests for new functionality
5. Run the test suite: `composer test`
6. Commit with descriptive messages
7. Push to your fork: `git push origin feature/amazing-feature`
8. Open a Pull Request

## 📋 Development Setup

### Prerequisites
- PHP 8.0 or higher
- Composer
- WordPress development environment
- Git

### Installation
```bash
# Clone your fork
git clone https://github.com/yourusername/seo-forge-professional.git
cd seo-forge-professional

# Install dependencies
composer install

# Install development dependencies
composer install --dev
```

### Running Tests
```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit --testsuite=unit

# Run with coverage
composer test-coverage
```

### Code Quality
```bash
# Check coding standards
composer phpcs

# Fix coding standards
composer phpcbf

# Run static analysis
composer phpstan

# Run all quality checks
composer quality
```

## 🎯 Coding Standards

### PHP Standards
- Follow WordPress PHP Coding Standards
- Use PSR-4 autoloading
- PHP 8.0+ features encouraged
- Strict typing: `declare(strict_types=1);`
- Comprehensive PHPDoc comments

### WordPress Standards
- Use WordPress hooks and filters
- Follow WordPress security practices
- Proper sanitization and escaping
- Use WordPress coding conventions

### Code Structure
- SOLID principles
- Dependency injection
- Service-oriented architecture
- Proper error handling
- Comprehensive logging

## 🔒 Security Guidelines

### Security Practices
- Always sanitize input
- Always escape output
- Use nonces for forms
- Validate user capabilities
- Use prepared statements for SQL

### Security Review
- All security-related changes require review
- Include security test cases
- Document security implications
- Follow WordPress security guidelines

## 📝 Commit Guidelines

### Commit Messages
Use conventional commit format:
```
type(scope): description

[optional body]

[optional footer]
```

### Types
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Test additions/changes
- `chore`: Maintenance tasks

### Examples
```
feat(api): add content generation endpoint
fix(security): resolve XSS vulnerability in admin panel
docs(readme): update installation instructions
```

## 🧪 Testing Guidelines

### Test Requirements
- Unit tests for new functionality
- Integration tests for complex features
- Security tests for sensitive operations
- Performance tests for critical paths

### Test Structure
```php
<?php
declare(strict_types=1);

namespace SEOForge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEOForge\Core\Plugin;

class PluginTest extends TestCase
{
    public function test_plugin_initialization(): void
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(Plugin::class, $plugin);
    }
}
```

## 📚 Documentation

### Code Documentation
- PHPDoc for all public methods
- Inline comments for complex logic
- README updates for new features
- API documentation for endpoints

### User Documentation
- Update user guides for new features
- Include screenshots when helpful
- Provide configuration examples
- Document troubleshooting steps

## 🔄 Pull Request Process

### Before Submitting
1. Ensure all tests pass
2. Update documentation
3. Add changelog entry
4. Verify coding standards compliance
5. Test in WordPress environment

### PR Requirements
- Clear description of changes
- Link to related issues
- Screenshots for UI changes
- Test coverage for new code
- Backward compatibility consideration

### Review Process
1. Automated checks must pass
2. Code review by maintainers
3. Security review for sensitive changes
4. Testing in multiple environments
5. Documentation review

## 🏷️ Release Process

### Version Numbering
We follow Semantic Versioning (SemVer):
- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes (backward compatible)

### Release Checklist
- [ ] All tests passing
- [ ] Documentation updated
- [ ] Changelog updated
- [ ] Version numbers updated
- [ ] Security review completed
- [ ] Performance testing done

## 📞 Getting Help

### Community Support
- GitHub Discussions for questions
- Issues for bug reports
- Pull requests for contributions

### Development Help
- Check existing documentation
- Review code examples
- Ask in GitHub Discussions
- Contact maintainers for complex issues

## 📄 License

By contributing to SEO-Forge Professional, you agree that your contributions will be licensed under the GPL v2.0 or later license.

## 🙏 Recognition

Contributors will be recognized in:
- CONTRIBUTORS.md file
- Release notes
- Plugin credits
- Annual contributor highlights

Thank you for contributing to SEO-Forge Professional! 🚀