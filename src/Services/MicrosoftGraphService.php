<?php

namespace EZStoritve\M365Mail\Services;

use Illuminate\Support\Facades\Cache;
use Exception;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use EZStoritve\M365Mail\Enums\MailReadParams;

class MicrosoftGraphService
{
    protected string $tenantId;
    protected string $clientId;
    protected string $clientSecret;
    protected string $mailFromAddress;
    protected string $mailFromName;

    public function __construct(
        string $tenantId = null,
        string $clientId = null,
        string $clientSecret = null,
        string $mailFromAddress = null,
        string $mailFromName = null
    ) {
        $config = config('mail.mailers.m365-mail');
        $this->tenantId = $tenantId ?? $config['tenant_id'];
        $this->clientId = $clientId ?? $config['client_id'];
        $this->clientSecret = $clientSecret ?? $config['client_secret'];
        $this->mailFromAddress = $mailFromAddress ?? $config['from_address'];
        $this->mailFromName = $mailFromName ?? $config['from_name'];
    }

    public function getMailFromAddress(): string
    {
        return $this->mailFromAddress;
    }

    public function getMailFromName(): string
    {
        return $this->mailFromName;
    }

    public function getAccessToken(): string
    {
        if (Cache::has('ez_microsoft_graph_access_token')) {
            return Cache::get('ez_microsoft_graph_access_token');
        }

        try {
            $client = new Client();
            $tokenResponse = $client->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $tokenData = json_decode($tokenResponse->getBody()->getContents(), true);
            if (!isset($tokenData['access_token']) || !is_string($tokenData['access_token'])) {
                $tokenValue = isset($tokenData['access_token']) ? json_encode($tokenData['access_token']) : '/';
                throw new \Exception("Access token is missing or is not a string in the response. Value: {$tokenValue}");
            }

            $accessToken = $tokenData['access_token'];
            $expiresIn = $tokenData['expires_in'];

            Cache::put('ez_microsoft_graph_access_token', $accessToken, $expiresIn - 60);
            return $accessToken;
        } catch (RequestException $e) {
            throw new \Exception("Failed to obtain access token due to HTTP error. Error: {$e->getMessage()}");
        } catch (\Exception $e) {
            throw new \Exception("Failed to obtain access token due to an unexpected error. Error: {$e->getMessage()}");
        }
    }

    //public function readEmailsFromFolder(string $folderPath, string $mailbox, bool $getFiles, bool $download, string $filePath): array
    public function readEmailsFromFolder(array $params): array
    {        
        $mailbox = $params[MailReadParams::Mailbox] ?? null;
        if (!$mailbox)
            return [];

        $folderPath = $params[MailReadParams::FolderPath] ?? 'Inbox';
        $getFiles = $params[MailReadParams::GetFiles] ?? false;
        $download = $params[MailReadParams::Download] ?? false;
        $filePath = $params[MailReadParams::FilePath] ?? public_path('temp/');
        
        $accessToken = $this->getAccessToken();

        $client = new Client();

        $folders = explode('\\', $folderPath);
        $currentFolderId = null;

        foreach ($folders as $folderName) {
            $url = $currentFolderId ?
                "https://graph.microsoft.com/v1.0/users/$mailbox/mailFolders/$currentFolderId/childFolders" :
                "https://graph.microsoft.com/v1.0/users/$mailbox/mailFolders";

            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => "Bearer $accessToken",
                    'Content-Type' => 'application/json',
                ],
            ]);

            $folderData = json_decode($response->getBody()->getContents(), true);

            $foundFolder = false;
            foreach ($folderData['value'] as $folder) {
                if ($folder['displayName'] === $folderName) {
                    $currentFolderId = $folder['id'];
                    $foundFolder = true;
                    break;
                }
            }

            if (!$foundFolder) {
                throw new Exception("Folder '$folderName' not found in path '$folderPath'.");
            }
        }

        $messagesUrl = "https://graph.microsoft.com/v1.0/users/$mailbox/mailFolders/$currentFolderId/messages";
        $response = $client->get($messagesUrl, [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
            ],
        ]);

        $emails = json_decode($response->getBody()->getContents(), true);

        $emailsData = [];
        foreach ($emails['value'] as $email)
        {
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
                'attachments' => [],
            ];

            if ($email['hasAttachments']) {
                $attachmentsUrl = "https://graph.microsoft.com/v1.0/users/$mailbox/messages/{$email['id']}/attachments";
                $attachmentsResponse = $client->get($attachmentsUrl, [
                    'headers' => [
                        'Authorization' => "Bearer $accessToken",
                        'Content-Type' => 'application/json',
                    ],
                ]);
                $attachmentsData = json_decode($attachmentsResponse->getBody()->getContents(), true);

                foreach ($attachmentsData['value'] as $attachment) {
                    if ($attachment['@odata.type'] === '#microsoft.graph.fileAttachment') {
                        $attachmentDetails = [
                            'name' => $attachment['name'],
                            'contentBytes' => $getFiles ? base64_decode($attachment['contentBytes']) : null,
                        ];
                        $emailDetails['attachments'][] = $attachmentDetails;
                        if ($download) {
                            $filePath = $filePath.$attachment['name'];
                            File::put($filePath, base64_decode($attachmentDetails['contentBytes']));
                        }
                    }
                }
            }

            $emailsData[] = $emailDetails;
        }

        return $emailsData;
    }
}