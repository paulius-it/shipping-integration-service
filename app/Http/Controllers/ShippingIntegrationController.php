<?php

namespace App\Http\Controllers;

use App\Services\LpExpressApiService;
use Illuminate\Http\Request;

class ShippingIntegrationController extends Controller
{
    public function __construct(private LpExpressApiService $lpExpress)
    {
    }

public function createShipping(Request $shippingData) {
    $provider = $shippingData['provider'];
    $requestPath = $shippingData['request_path'];
    $type = $shippingData['type'];
    
    $this->lpExpress->creatShippingItem([]);
}



}
