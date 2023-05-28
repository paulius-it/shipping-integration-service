<?php

namespace App\Enums;

enum LpExpressEndpointStructureToApi: string
{
    case shipping = 'shipping';
    case shipping_call_courier = 'shipping/courier/call';
    case SHIPPING_PRINT_LABEL = 'documents/item/sticker/?';
    case shipping_track = 'shipping/tracking';
}
