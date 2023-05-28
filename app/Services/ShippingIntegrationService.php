<?php

namespace App\Services;

use App\Enums\LpExpressEndpointStructureToApi;
use App\Services\ApiClients\LpExpressApiClient;
use App\Services\ApiClients\OmnivaApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Log;

/**
 * Shipping Integration service which handles the business logic of the specified shipping operation: sends a request to a wanted API, handles the response and returns the content (or errors if any)where to send the incoming requests e.g. shipment creating/editing, tracking, label printing etc.
 */

class ShippingIntegrationService
{

    private string $endpointPath = '';

    public function __construct(
        private LpExpressApiClient $lpExpress,
        private OmnivaApiClient $omniva
    ) {
    }

    public function sendApiRequest(Request $shippingData, string $endpointPath)
    {


        $provider = $shippingData->input('provider');
        $method = $shippingData->input('method');
        $requestData = $shippingData->input('request_data');
        $lpShippingId = $requestData['lp_shipping_id'] ?? ''; // Is added to the endpoint if needed
        $this->endpointPath = $endpointPath;

        // Log the request if an option is enabled
        if ($shippingData->input('debug')) {
            Log::debug(print_r($requestData, true));
        }

        if ($provider == 'lp_express') {
            $this->resolveLpApiEndpoint();

            if ($lpShippingId) {
                $this->endpointPath .= '/' . $lpShippingId;
                Arr::forget($requestData, 'lp_shipping_id'); // Eliminate as it is no longer needed
            }
            $result = $this->lpExpress->processRequest($method, $this->endpointPath, $requestData);
        } else if ($provider == 'omniva') {
            $result = $this->omniva->processRequest($method, $this->endpointPath, $requestData);
        }
// Log the response before return
        if ($shippingData->input('debug')) {
            Log::debug(print_r($result, true));
        }

        return $result;
    }

    /**
     * Parses API endpoint according to chosen provider
     * @return $this
     */
    private function resolveLpApiEndpoint(): self
    {
        switch ($this->endpointPath) {
            case 'shipping.create':
            case 'shipping.get':
            case 'shipping.update':
            case 'shipping.delete':
                $this->endpointPath = LpExpressEndpointStructureToApi::shipping->value;
                break;
            case 'shipping.call-courier':
                $this->endpointPath = LpExpressEndpointStructureToApi::shipping_call_courier->value;
                break;
            case 'shipping.track':
                $this->endpointPath = LpExpressEndpointStructureToApi::shipping_track->value;
                break;
        }
        return $this;
    }
}
