<?php

namespace App\Services\ApiClients;

use App\Exceptions\LpExpressAPIException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class LpExpressApiClient
{
    private const baseApiUrl = 'https://api-manosiuntostst.post.lt/api/v1/';
    private string $endpoint;
    private string $provider;
    private string $accessToken;
    private string $type;
    public Response $apiCallResult;
    private bool $successful = false;
    private string $authServiceEndpoint = '127.0.0.1:8000/api';
    private bool $shouldAuthenticate = false;
    private string $uri;
    private array $headers = [];

    public function __construct()
    {
        $this->provider = 'lp_express';
    }

    public function authorizeClient(): self
    {
        $response = Http::get($this->authServiceEndpoint . '/get-api-credentials', [
            'provider' => $this->provider,
        ]);
        $this->accessToken = $response->json('lp_express_access_token') ?? '';

        if (!$this->accessToken) {
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
                $this->accessToken = $response['lp_api_response']['access_token'];
                $this->shouldAuthenticate = false;
                $this->successful = true;
            } catch (\Exception $e) {
                throw new LpExpressAPIException('Unsupported type!');
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
        ?string $method = null,
        ?string $uri = null,
        array $requestData,
        bool $log = false
    ): Response {
        $this->authorizeClient();
        $this->endpoint = self::baseApiUrl . $uri;
        $this->headers = ['Authorization: Bearer ' . $this->accessToken]; // Not needed i using Laravel's default HTTP client
        try {
            $this->apiCallResult = Http::withToken($this->accessToken)
                ->$method($this->endpoint, $requestData);
        } catch (\Exception $e) {
            $this->apiCallResult = response()->json([
                'errors' => $e->getMessage(),
            ], 400);
        }

        return $this->apiCallResult;
    }
}
