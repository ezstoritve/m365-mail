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

# Usage

The Mail::send method and the $m object in the callback accept all standard Laravel parameters, including:

- To, Cc, Bcc: Specify recipients.
- Subject: Set the email subject.
- From: Define the sender’s email.
- ReplyTo: Set the reply-to address.
- Attachments: Attach files to the email.
- Body (text or HTML): Compose the email content.

These parameters allow for full customization of email messages within your application.

Sample code:
```php
Mail::send('blade.file', [], function ($m) {
    $m->to('email.to@domain.com', 'Recipient Name')
        ->subject('Mail subject')
        ->getHeaders()->addTextHeader('X-Save', 'true'); // save an email to the sent items folder - optional
});
```

The Mail::read function accepts the following parameters:

- FolderPath (Inbox\Folder1...): Specifies which folder in the Microsoft 365 mailbox to read from.
- Mailbox (user email): The email address of the user’s mailbox to read.
- GetFiles (true, false): Determines whether to retrieve the attachment contentBytes, allowing manual download.
- Download (true, false): Automatically downloads attachments to the specified folder.
- FilePath (e.g., public_path('temp')): Allows you to set a custom path for downloaded files.

This code can be customized to suit your specific requirements.

Sample code:
```php
$folderPath = 'Inbox\Folder1';
$mailbox = 'user.mail@domain.com';

$result = Mail::read([
    MailReadParams::FolderPath => $folderPath,
    MailReadParams::Mailbox => $mailbox,
    MailReadParams::GetFiles => false
], function ($emails) {
    $output = '';
    foreach ($emails as $email) {
        $output .= '<h3>' . htmlspecialchars($email['subject']) . '</h3>';
        $output .= '<p>From: ' . htmlspecialchars($email['fromName']) . ' (' . htmlspecialchars($email['from']) . ')</p>';
        $output .= '<p>To: ' . htmlspecialchars(implode(', ', array_column($email['to'], 'address'))) . '</p>';
        $output .= '<p>CC: ' . htmlspecialchars(implode(', ', array_column($email['cc'], 'address'))) . '</p>';
        $output .= '<p>BCC: ' . htmlspecialchars(implode(', ', array_column($email['bcc'], 'address'))) . '</p>';
        $output .= '<p>Date: ' . htmlspecialchars($email['receivedDateTime']) . '</p>';
        $output .= '<p>' . htmlspecialchars($email['bodyPreview']) . '</p>';
        if (!empty($email['attachments'])) {
            $output .= '<p>Attachments:</p><ul>';
            foreach ($email['attachments'] as $attachment) {
                $output .= '<li>' . htmlspecialchars($attachment['name']) . '</li>';
                //$output .= '<li>' . htmlspecialchars($attachment['contentBytes']) . '</li>';
            }
            $output .= '</ul>';
        }
        $output .= '<hr>';
    }
    return $output;
});

print_r($result);
```


## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Egi Žaberl](https://github.com/ezstoritve)
- [Egi Žaberl Home](https://www.ezstoritve.com/)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
