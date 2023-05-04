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
    public Response $apiCallResult;
    private bool $successful = false;
    private string $authServiceEndpoint = '127.0.0.1:8002/api';
    private bool $shouldAuthenticate = false;
    private string $requestPath;

    public function __construct()
    {
        $this->provider = 'lp_express';
    }

    public function authorizeClient(): self
    {
        try {
            $response = Http::get($this->authServiceEndpoint . '/get-api-credentials', [
                'provider' => $this->provider,
            ]);
            $this->accessToken = $response->json('lp_express_access_token') ?? '';
        } catch (\Exception $e) {
            $this->shouldAuthenticate = true;
        }

        if ($this->shouldAuthenticate) {
            $providers['lp_express'] = true;
            try {
                $response = Http::post($this->authServiceEndpoint . '/authenticate-provider', [
                    'providers' => $providers,
                    'cache' => true,
                ]);
                $response = $response->json();
                dd($response);
                $this->accessToken = $response['lp_api_response']['access_token'];
                dd($this->accessToken);
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
        ?string $requestPath = null,
        array $requestData,
        bool $log = false
    ): Response {
        $this->authorizeClient();

        $url = self::baseApiUrl . $requestPath;

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

        return $this->apiCallResult;
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
            'message' => $this->apiCallResult,
        ];
        return $response;
    }
}
