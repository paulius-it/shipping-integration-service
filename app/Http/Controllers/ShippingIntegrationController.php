<?php

namespace App\Http\Controllers;

use App\Services\ShippingIntegrationService;
use Illuminate\Http\Request;

/**
 * Class responsible for handleing which Shipping elements are used in the request
 * @param Request $request
 * @return JsonResponse actual API response
 */

class ShippingIntegrationController extends Controller
{

    public function __construct(private ShippingIntegrationService $service)
    {
    }

    /**
     * Creates shipping item using the requested carrier
     * @param Request $shippingData: shipping item information
     */
    public function createShipping(Request $request)
    {
        $shippingData = $request->all();
        $result = $this->service->sendApiRequest($request, endpointPath: 'shipping.create');
        return $result;
    }

    /**
     * Gets shipping item using the requested carrier
     * @param Request $shippingData: shipping item information
     */
    public function getShipping(Request $request)
    {
        $shippingData = $request->all();
        $result = $this->service->sendApiRequest($request, endpointPath: 'shipping.get');
        return $result;
    }

    public function updateShipping(Request $request)
    {
        $shippingData = $request->all();
        $result = $this->service->sendApiRequest($request, endpointPath: 'shipping.update');
        return $result;
    }

    public function deleteShipping(Request $request)
    {
        $shippingData = $request->all();
        $result = $this->service->sendApiRequest($request, endpointPath: 'shipping.delete');
        return $result;
    }

    public function shippingTracking(Request $request)
    {
        $shippingData = $request->all();
        $result = $this->service->sendApiRequest($request, endpointPath: 'shipping.track');
        return $result;
    }

    public function callCourier(Request $request)
    {
        $shippingData = $request->all();
        $result = $this->service->sendApiRequest($request, endpointPath: 'shipping.call-courier');
        return $result;
    }

    public function downloadLabels(Request $request)
    { // Method for Omniva only to receive labels in PDF
        $shippingData = $request->all();
        $result = $this->service->sendApiRequest($request, endpointPath: 'shipping.download-labels');
        return $result;
    }

    public function downloadManifest(Request $request)
    { // Omniva-only, to receive manifest of the shipping item
        $shippingData = $request->all();
        $result = $this->service->sendApiRequest($request, endpointPath: 'shipping.download-manifest');
        return $result;
    }
}
