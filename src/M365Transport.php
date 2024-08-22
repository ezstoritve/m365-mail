<?php

namespace EZStoritve\M365Mail;

use Illuminate\Mail\Transport\Transport;

use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\MessageConverter;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

use EZStoritve\M365Mail\Services\MicrosoftGraphService;

use GuzzleHttp\Client;

class M365Transport extends AbstractTransport
{
    protected MicrosoftGraphService $microsoftGraphService;

    public function __construct(
        MicrosoftGraphService $microsoftGraphService,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null)
    {
        parent::__construct($dispatcher, $logger);
        $this->microsoftGraphService = $microsoftGraphService;
    }

    public function __toString(): string
    {
        return 'm365-mail';
    }

    protected function doSend(SentMessage $sentMessage): void
    {
        $accessToken = $this->microsoftGraphService->getAccessToken();

        $email = MessageConverter::toEmail($sentMessage->getOriginalMessage());

        if ($email->getFrom()) {
            $fromAddress = $email->getFrom()[0]->getAddress();
            $fromName = $email->getFrom()[0]->getName();
        } else {
            $fromAddress = $this->microsoftGraphService->getMailFromAddress();
            $fromName = $this->microsoftGraphService->getMailFromName();
            $email->from(new Address($this->microsoftGraphService->getMailFromAddress(), $this->microsoftGraphService->getMailFromName()));
        }

        $emailData = [
            'message' => [
                'subject' => $email->getSubject(),
                'body' => [
                    'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                    'content' => $email->getHtmlBody() ?? $email->getTextBody(),
                ],
                'toRecipients' => $this->formatRecipients($email->getTo()),
            ],
        ];

        if ($ccRecipients = $email->getCc()) {
            if ($this->validateRecipients($ccRecipients)) {
                $emailData['message']['ccRecipients'] = $this->formatRecipients($ccRecipients);
            }
        }

        if ($bccRecipients = $email->getBcc()) {
            if ($this->validateRecipients($bccRecipients)) {
                $emailData['message']['bccRecipients'] = $this->formatRecipients($bccRecipients);
            }
        }

        if ($replyTo = $email->getReplyTo()) {
            if ($this->validateRecipients($replyTo)) {
                $emailData['message']['replyTo'] = $this->formatRecipients($replyTo);
            }
        }

        if ($sender = $email->getSender()) {
            if ($this->validateRecipients([$sender])) {
                $emailData['message']['sender'] = $this->formatRecipients([$sender]);
            }
        }

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $fileName = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $emailData['message']['attachments'][] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $fileName,
                'contentType' => $attachment->getMediaType(),
                'contentBytes' => base64_encode($attachment->getBody()),
                'contentId' => $fileName,
                'isInline' => $headers->getHeaderBody('Content-Disposition') === 'inline',
            ];
        }

        $client = new Client();
        $client->post("https://graph.microsoft.com/v1.0/users/{$fromAddress}/sendMail", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
            'json' => $emailData,
        ]);
    }

    protected function validateRecipients($recipients)
    {
        foreach ($recipients as $recipient) {
            if (!filter_var($recipient->getAddress(), FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }
        return true;
    }

    protected function formatRecipients($recipients)
    {
        return array_map(function ($recipient) {
            return ['emailAddress' => ['address' => $recipient->getAddress(), 'name' => $recipient->getName()]];
        }, $recipients);
    }

}