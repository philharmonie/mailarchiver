<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MailcowService
{
    protected string $apiUrl;

    protected string $apiKey;

    protected bool $verifySsl;

    protected int $timeout;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('mailcow.api_url'), '/');
        $this->apiKey = config('mailcow.api_key');
        $this->verifySsl = config('mailcow.verify_ssl');
        $this->timeout = config('mailcow.timeout');
    }

    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->when(! $this->verifySsl, fn ($http) => $http->withoutVerifying());
    }

    public function getBccMaps(): array
    {
        $response = $this->client()->get("{$this->apiUrl}/api/v1/get/bcc");

        if ($response->failed()) {
            throw new \Exception('Failed to fetch BCC maps from mailcow: '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function getDomains(): array
    {
        $response = $this->client()->get("{$this->apiUrl}/api/v1/get/domain/all");

        if ($response->failed()) {
            throw new \Exception('Failed to fetch domains from mailcow: '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function getMailboxes(?string $domain = null): array
    {
        $url = $domain
            ? "{$this->apiUrl}/api/v1/get/mailbox/{$domain}"
            : "{$this->apiUrl}/api/v1/get/mailbox/all";

        $response = $this->client()->get($url);

        if ($response->failed()) {
            throw new \Exception('Failed to fetch mailboxes from mailcow: '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function getAliases(?string $domain = null): array
    {
        $url = $domain
            ? "{$this->apiUrl}/api/v1/get/alias/{$domain}"
            : "{$this->apiUrl}/api/v1/get/alias/all";

        $response = $this->client()->get($url);

        if ($response->failed()) {
            throw new \Exception('Failed to fetch aliases from mailcow: '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->client()->get("{$this->apiUrl}/api/v1/get/status/version");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
