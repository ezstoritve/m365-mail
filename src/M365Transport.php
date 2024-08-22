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

        $emailData = [
            'message' => [
                'subject' => $email->getSubject(),
                'body' => [
                    'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                    'content' => $email->getHtmlBody() ?? $email->getTextBody(),
                ],
                'toRecipients' => $this->formatRecipients($email->getTo()),
                'ccRecipients' => $this->formatRecipients($email->getCc()),
                'bccRecipients' => $this->formatRecipients($email->getBcc()),
                'replyTo' => $this->formatRecipients($email->getReplyTo()),
                'sender' => $this->formatRecipients([$email->getSender()]),
            ],
        ];

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
        $client->post('https://graph.microsoft.com/v1.0/me/sendMail', [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
            'json' => $emailData,
        ]);
    }

    protected function formatRecipients($recipients)
    {
        return array_map(function ($recipient) {
            return ['emailAddress' => ['address' => $recipient->getAddress(), 'name' => $recipient->getName()]];
        }, $recipients);
    }

}