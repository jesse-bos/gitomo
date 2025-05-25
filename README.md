# OpenAI Commit Messages - AI-Generated Git Commit Messages

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jesse-bos/openai-commit-messages.svg?style=flat-square)](https://packagist.org/packages/jesse-bos/openai-commit-messages)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jesse-bos/openai-commit-messages/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jesse-bos/openai-commit-messages/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jesse-bos/openai-commit-messages/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jesse-bos/openai-commit-messages/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jesse-bos/openai-commit-messages.svg?style=flat-square)](https://packagist.org/packages/jesse-bos/openai-commit-messages)

OpenAI Commit Messages is a Laravel package that generates meaningful commit messages using OpenAI based on your staged git changes. Just run one command and get a suggested commit message!

## Installation

### Step 1: Install the Package

```bash
composer require jesse-bos/openai-commit-messages
```

*This automatically installs the required OpenAI Laravel package as well.*

### Step 2: Publish OpenAI Configuration

```bash
php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"
```

### Step 3: Add your OpenAI API Key

1. Get an API key from [OpenAI's website](https://platform.openai.com/api-keys)
2. Add it to your `.env` file:

```env
OPENAI_API_KEY=sk-your-actual-api-key-here
```

### Step 4: Optionally configure the Package

If you want to customize the OpenAI model, publish the package config:

```bash
php artisan vendor:publish --tag="openai-commit-messages-config"
```

## Prerequisites

- PHP 8.3+
- Laravel 11.0+ or 12.0+
- OpenAI API key with sufficient credits

## Usage

### Verify your setup (recommended first time)

After installation, verify that everything is configured correctly:

```bash
php artisan openai:commit --check
```

This will check:
- ✓ OpenAI API key is set
- ✓ OpenAI configuration is published  
- ✓ You're in a git repository
- ✓ Model configuration

### Generate commit messages

After making changes in your git repository, simply run:

```bash
php artisan openai:commit
```

The package will automatically:
1. **First check for staged changes** (files added with `git add` or staged via Tower, GitHub Desktop, etc.)
2. **If no staged changes found**, it will analyze unstaged changes
3. **Generate a commit message** based on the found changes using OpenAI

The tool works seamlessly with any git workflow:
- **Command line**: `git add .` then `php artisan openai:commit`
- **Tower/GitHub Desktop**: Stage files in the GUI, then run `php artisan openai:commit`
- **Quick preview**: Just run `php artisan openai:commit` to see a message for unstaged changes

After getting the suggested message, create your commit:

```bash
git commit -m "the suggested message"
```

## Configuration

You can configure which OpenAI model to use by setting an environment variable:

```
OPENAI_COMMIT_MESSAGES_MODEL=gpt-4o
```

Or by publishing and editing the config file:

```php
return [
    'openai' => [
        'model' => env('OPENAI_COMMIT_MESSAGES_MODEL', 'gpt-4o-mini'),
    ],
];
```

## Testing

```bash
composer test
```

## Credits

- [Jesse Bos](https://github.com/jesse-bos)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
