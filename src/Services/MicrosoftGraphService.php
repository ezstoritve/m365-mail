<?php

namespace EZStoritve\M365Mail\Services;

use Illuminate\Support\Facades\Cache;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MicrosoftGraphService
{
    public function __construct(
        protected readonly string $tenantId,
        protected readonly string $clientId,
        protected readonly string $clientSecret,
        protected readonly string $mailFromAddress,
        protected readonly string $mailFromName,
    ) {
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
}