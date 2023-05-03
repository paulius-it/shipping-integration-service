<?php

namespace App\Services\ApiClients;

use App\Exceptions\LpExpressAPIException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class LpExpressApiClient
{
    private const baseApiUrl = 'https://api-manosiuntostst.post.lt/';
    private string $provider;
    private string $accessToken;
    private Response $apiCallResult;
    private bool $successful = false;
    private string $authServiceEndpoint = 'localhost/api';
    private bool $shouldAuthenticate = false;
    private string $requestPath;

    public function __construct(private BaseApiService $base)
    {
        $this->provider = 'lp_express';
    }

    public function authorizeClient(): self
    {
        try {
            $this->accessToken = Http::get($this->authServiceEndpoint . '/get-api-credentials', [
                'provider' => $this->provider,
            ]);
        } catch (\Exception $e) {
            $this->shouldAuthenticate = true;
        }

        if ($this->shouldAuthenticate) {
            $providers['{$this->provider}'] = true;
            try {
                $response = Http::post($this->authServiceEndpoint . '/authenticate-provider', [
                    'providers' => $providers,
                    'cache' => true,
                ]);
                $this->accessToken = $response->json('access_token');
                $this->shouldAuthenticate = false;
                $this->successful = true;
            } catch (\Exception $e) {
                $this->errors->add('Failed to authenticate provider {$this->provider}!');
            }

            if ($this->errors->count() > 0) {
                $this->successful = false;
            }
        }
        return $this;
    }

    /**
     * Makes an actual request to LP Express
     * @param type: request type
     * @param requestData: request params according to LP Express API specs
     * @param log: logs request for debugging reasons
     * @return $this
     */
    public function processRequest(
        ?string $type = null,
        array $requestData,
        bool $log = false
    ): self {
        $this->authorizeClient();

        $url = $this->lpEndpoint . $this->requestPath;

        try {
            switch ($type) {
                case 'get':
                    $this->apiCallResult = Http::withToken($this->accessToken)
                        ->get($url, $requestData);
                    break;
                case 'post':
                    $this->apiCallResult = Http::withToken($this->accessToken)
                        ->post($url, $requestData);
                    break;
                case 'delete':
                    $this->apiCallResult = Http::withToken($this->accessToken)
                        ->delete($url, $requestData);
                    break;
                case 'put':
                    $this->apiCallResult = Http::withToken($this->accessToken)
                        ->put($url, $requestData);
                    break;
                default:
                    throw new LpExpressAPIException('Unsupported tyupe!');
            }
        } catch (\Exception $e) {
            throw new LpExpressAPIException('Api call failed: ' . $e->getMessage());
        }
    }

    public function creatShippingItem(array $data): array
    {
        $this->requestPath = 'api/v1/shipping/';

        $this->processRequest(
            type: $data['request_type'],
            requestData: $data['request_data']
        );

        if ($this->errors->count() > 0) {
            $response = [
                'success' => false,
                'errors' => $this->errors?->first(),
            ];

            return $response;
        }
        $response = [
            'success' => true,
            'message' => 'Shipment created',
        ];
        return $response;
    }
}
