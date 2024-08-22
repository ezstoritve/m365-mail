# Laravel M365 Mail Package

This package provides a simple access to Microsoft M365 mail functions.

## Installation

You can install the package via composer:

```bash
composer require ezstoritve/m365-mail
```

## Configuration

### Register and configure the Microsoft Azure App

[Quickstart: Register an application with the Microsoft identity platform](https://learn.microsoft.com/en-us/entra/identity-platform/quickstart-register-app)

### Configuring your Laravel app

First you need to add a new entry to the mail drivers array (mailers) in your `config/mail.php` configuration file:

```php
'mailers' => [
    'm365-mail' => [
        'transport' => 'm365-mail',
        'tenant_id' => env('MICROSOFT_GRAPH_TENANT_ID'),
        'client_id' => env('MICROSOFT_GRAPH_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_GRAPH_CLIENT_SECRET'),
        'from_address' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME')
    ],
    ...
]
```

Then set up variables in an .env file to use data from Microsoft Azure App.

```dotenv
MAIL_MAILER=m365-mail
MICROSOFT_TENANT_ID="your_tenant_id"
MICROSOFT_CLIENT_ID="your_client_id"
MICROSOFT_CLIENT_SECRET="your_client_secret_value"
MAIL_FROM_ADDRESS="from.mail@domain.com"
MAIL_FROM_NAME="from_name"
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Egi Žaberl](https://github.com/ezstoritve)
- [Egi Žaberl Home](https://www.ezstoritve.com/)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
