[![Latest Stable Version](http://img.shields.io/github/release/ezstoritve/m365-mail.svg)](https://packagist.org/packages/ezstoritve/m365-mail) [![Total Downloads](http://img.shields.io/packagist/dm/ezstoritve/m365-mail.svg)](https://packagist.org/packages/ezstoritve/m365-mail) [![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://www.paypal.me/egizaberl)

# Laravel M365 Mail Package

This package provides seamless access to Microsoft M365 mail functions, allowing you to integrate email handling within your Laravel application effortlessly. 
It supports sending and reading emails using the Microsoft Graph API, making it easy to work with M365 mailboxes directly from your code. 
With this package, you can leverage features like sending, fetching emails and downloading attachments, all while securely managing authentication through your Microsoft Azure App credentials.

## Installation

You can install the package via composer:


```bash
// add repository to composer.json in your project - not needed anymore as package is on https://packagist.org/packages/ezstoritve/m365-mail
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/ezstoritve/m365-mail.git"
    }
],
    
// install package
composer require ezstoritve/m365-mail
```

## Configuration

### Register and configure the Microsoft Azure App

[Quickstart: Register an application with the Microsoft identity platform](https://learn.microsoft.com/en-us/entra/identity-platform/quickstart-register-app)

### Configuring your Laravel app

To integrate the m365-mail driver, begin by adding a new entry to the mailers array in your config/mail.php configuration file:

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
This entry configures the m365-mail transport and sets the required credentials and sender information.

Next, configure the following variables in your .env file to use the credentials from your Microsoft Azure App:

```dotenv
MAIL_MAILER=m365-mail
MICROSOFT_TENANT_ID="your_tenant_id"
MICROSOFT_CLIENT_ID="your_client_id"
MICROSOFT_CLIENT_SECRET="your_client_secret_value"
MAIL_FROM_ADDRESS="from.mail@domain.com"
MAIL_FROM_NAME="from_name"
```

These variables will be used to authenticate and send emails through the Microsoft Graph API, ensuring your Laravel application is properly connected to your Azure setup.

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

The Mail::read method returns an array containing the following fields:

- id: The unique identifier of the email.
- subject: The subject line of the email.
- from: The sender's email address.
- fromName: The sender's display name.
- bodyPreview: A preview of the email body.
- receivedDateTime: The timestamp when the email was received.
- hasAttachments: Boolean indicating if the email has attachments.
- to: An array of recipients, including their addresses and names.
- cc: An array of CC recipients with their addresses and names.
- bcc: An array of BCC recipients with their addresses and names.
- attachments: An array of attachments, each including the filename and contentBytes (if GetFiles is true, allowing manual download).

```php
$emailDetails = [
    'id' => $email['id'],
    'subject' => $email['subject'],
    'from' => $email['from']['emailAddress']['address'],
    'fromName' => $email['from']['emailAddress']['name'],
    'bodyPreview' => $email['bodyPreview'],
    'receivedDateTime' => $email['receivedDateTime'],
    'hasAttachments' => $email['hasAttachments'],
    'to' => array_map(fn($recipient) => [
        'address' => $recipient['emailAddress']['address'],
        'name' => $recipient['emailAddress']['name'],
    ], $email['toRecipients'] ?? []),
    'cc' => array_map(fn($recipient) => [
        'address' => $recipient['emailAddress']['address'],
        'name' => $recipient['emailAddress']['name'],
    ], $email['ccRecipients'] ?? []),
    'bcc' => array_map(fn($recipient) => [
        'address' => $recipient['emailAddress']['address'],
        'name' => $recipient['emailAddress']['name'],
    ], $email['bccRecipients'] ?? []),
    'attachments' => [
        0 => [
            'name' => 'file name', 
            'contentBytes' => '' // if GetFiles is set to true - you can manualy download files in Mail:read function
        ],
        ...
    ]
];
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Egi Žaberl](https://github.com/ezstoritve)
- [Egi Žaberl Home](https://www.ezstoritve.com/)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
