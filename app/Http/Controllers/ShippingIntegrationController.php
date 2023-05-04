<?php

namespace App\Http\Controllers;

use App\Services\ApiClients\LpExpressApiClient;
use Illuminate\Http\Request;

class ShippingIntegrationController extends Controller
{
    public function __construct(private LpExpressApiClient $lpExpress)
    {
    }

    public function createShipping(Request $shippingData)
    {
        $provider = $shippingData->input('provider');
        $provider = 'lp_express';
        $requestPath = $shippingData->input('request_path');
        $requestPath = 'api/v1/shipping';
        $type = $shippingData->input('type');
        $type = 'post';
        $requestData = json_decode($shippingData->input('request_data'), true);
        $requestData = json_decode('{"receiver": { "address" : { "apartment": "5", "building": "20", "country": "LT", "locality": "Vilnius", "postalCode": "10200", "street":"Gedimino pr." }, "email": "test@post.lt", "name": "Test1", "phone": "37064155444", "terminalId": "5101" }, "template": "54", "partCount": "1"}', true);

        $result = $this->lpExpress->processRequest($type, $requestPath, $requestData);
        return $result;
    }
}
