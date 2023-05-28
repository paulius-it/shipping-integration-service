<?php

namespace App\Services\ApiClients;

use App\Exceptions\OmnivaAPIException;
use Mijora\Omniva\OmnivaException;
use Mijora\Omniva\Shipment\CallCourier;
use Mijora\Omniva\Shipment\Package\AdditionalService;
use Mijora\Omniva\Shipment\Package\Address;
use Mijora\Omniva\Shipment\Package\Contact;
use Mijora\Omniva\Shipment\Package\Measures;
use Mijora\Omniva\Shipment\Package\Cod;
use Mijora\Omniva\Shipment\Package\Package;
use Mijora\Omniva\Shipment\Shipment;
use Mijora\Omniva\Shipment\ShipmentHeader;
use Mijora\Omniva\Shipment\Label;
use Mijora\Omniva\Shipment\Manifest;
use Mijora\Omniva\Shipment\Order;
use Mijora\Omniva\Shipment\Tracking;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;

class OmnivaApiClient
{
    private const baseApiUrl = 'https://edixml.post.ee';
    private string $provider;
    private string $endpoint;
    private string $accessToken;

    private array $authData = [];
    private bool $successful = false;
    private string $authServiceEndpoint = 'localhost:8000/api';
    private bool $shouldAuthenticate = false;
    private string $type;
    private array $apiResult;

    private array $headers = [];

    public function __construct()
    {
        $this->provider = 'omniva';
    }

    public function authorizeClient(): self
    {
        $response = Http::get($this->authServiceEndpoint . '/get-api-credentials', [
            'provider' => $this->provider,
        ]);
        $this->authData = $response->json('omniva_config') ?? '';

        if (!$this->authData) {
            $this->shouldAuthenticate = true;
        }
        if ($this->shouldAuthenticate) {
            $providers['omniva'] = true;
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

    public function processRequest(
        ?string $method = null,
        ?string $uri = null,
        array $requestData,
        bool $log = false
    ): JsonResponse {
        $this->authorizeClient();
        $this->endpoint = self::baseApiUrl . $uri;
        $this->method = $method;
        if ($uri == 'shipping.create') {
            $result = $this->createShipping($requestData);
        } else if ($uri == 'shipping.call-courier') {
            $result = $this->callCourier($requestData);
        } else if ($uri == 'shipping.download-labels') {
            $result = $this->downloadShippingLabels($requestData);
        } else if ($uri == 'shipping.download-manifest') {
            $result = $this->downloadManifest($requestData);
        }

        if ($result) {
            return response()->json($result, 200);
        }
    }

    /**
     * Some helper methods for an easier Omniva API integration process
     * Lib used: https://github.com/omniva-baltic/omniva-api-lib
     */

    /**
     * Method for creating shipping in Omniva
     */
    private function createShipping(array $shippingData): array
    {
        try {
            $shipment = new Shipment();
            $shipment
                ->setComment($shippingData['comment'])
                ->setShowReturnCodeEmail(true);
            $shipmentHeader = new ShipmentHeader();
            $shipmentHeader
                ->setSenderCd($this->authData['api_access_key'])
                ->setFileId(date('Ymdhis'));
            $shipment->setShipmentHeader($shipmentHeader);
            $package = new Package();
            $package
                ->setId($shippingData['package_id'])
                ->setService($shippingData['service']);
            $additionalService = (new AdditionalService())->setServiceCode($shippingData['service_code']);
            $package->setAdditionalServices([$additionalService]);
            $measures = new Measures();
            $measures
                ->setWeight($shippingData['weight'])
                ->setVolume($shippingData['volume'])
                ->setHeight($shippingData['height'])
                ->setWidth($shippingData['width']);
            $package->setMeasures($measures);

            $cod = new Cod();
            $cod
                ->setAmount($shippingData['cod_amount'])
                ->setBankAccount($shippingData['bank_account'])
                ->setReceiverName($shippingData['receiver_name'])
                ->setReferenceNumber($shippingData['reference_number']);
            $package->setCod($cod);

            $receiverContact = new Contact();
            $address = new Address();
            $address
                ->setCountry($shippingData['country'])
                ->setPostcode($shippingData['postcode'])
                ->setDeliverypoint($shippingData['delivery'])
                ->setOffloadPostcode($shippingData['off_post'])
                ->setStreet($shippingData['street']);
            $receiverContact
                ->setAddress($address)
                ->setMobile($shippingData['receiver_mobile'])
                ->setEmail($shippingData['email'])
                ->setPersonName($shippingData['receiver_name']);
            $package->setReceiverContact($receiverContact);

            $senderContact = new Contact();
            $senderContact
                ->setAddress($address)
                ->setMobile($shippingData['sender_mobile'])
                ->setPersonName($shippingData['sender_name']);
            $package->setSenderContact($senderContact);

            $shipment->setPackages([$package, $package]);

            $shipment->setShowReturnCodeSms(false);
            $shipment->setShowReturnCodeEmail(false);

            $shipment->setAuth($this->authData['api_access_key'], $this->authData['api_secret']);
            $this->apiResult = $shipment->registerShipment();
            if (isset($this->apiResult['barcodes'])) {
                $response = [
                    'status_code' => 200,
                    'response' => $this->apiResult,
                ];
            }
        } catch (OmnivaException $e) {
            $this->apiResult = [
                'error' => true,
                'message' => $e->getMessage()
                    . $e->getTraceAsString(),
            ];
        }
        return $this->apiResult;
    }

    /**
     * Method for calling courier for the individual shipping item in Omniva provider
     */
    private function callCourier(array $shippingData): array
    {
        try {
            $address = new Address();
            $address
                ->setCountry($shippingData['country'])
                ->setPostcode($shippingData['postcode'])
                ->setDeliverypoint($shippingData['city'])
                ->setStreet($shippingData['street']);

            $senderContact = new Contact();
            $senderContact
                ->setAddress($address)
                ->setMobile($shippingData['sender_mobile'])
                ->setPersonName($shippingData['person_name']);


            $call = new CallCourier();
            $call->setAuth($this->authData['api_access_key'], $this->authData['api_secret']);
            $call->setSender($senderContact);

            $result = $call->callCourier();

            if ($result) {
                $this->apiResult = [
                    'success' => $result,
                    'message' => "Courier called",
                ];
            } else {
                $this->apiResult = [
                    'success' => $result,
                    'message' => "Courier call failed",
                ];
            }
        } catch (OmnivaException $e) {
            $this->apiResult = [
                'error' => true,
                'message' => $e->getMessage()
                    . $e->getTraceAsString(),
            ];
        }
        return $this->apiResult;
    }

    /**
     * Requests Omniva API to download labels of the barcodes of the shipment
     */
    private function downloadShippingLabels(array $requestData)
    {
        try {
            $label = new Label();
            $label->setAuth($this->authData['api_access_key'], $this->authData['api_secret']);

            $result = $label->downloadLabels($requestData['barcode']);

            $decodedPdfContent = base64_ddcode($result);
            $this->apiResult = [
                'success' => true,
                'message' => $decodedPdfContent,
            ];
        } catch (OmnivaException $e) {
            $this->apiResult = [
                'success' => true,
                'message' => $e->getMessage()
                    . $e->getTraceAsString(),
            ];
        }
        return $this->apiResult;
    }

    /**
     * Downloads omniva manifest document
     */
    private function downloadManifest(array $requestData)
    {
        try {
            $address = new Address();
            $address
                ->setCountry($requestData['country'])
                ->setPostcode($requestData['postcode'])
                ->setDeliverypoint($requestData['city'])
                ->setStreet($requestData['street']);

            $senderContact = new Contact();
            $senderContact
                ->setAddress($address)
                ->setMobile($requestData['sender_mobile'])
                ->setPersonName($requestData['sender_name']);

            $manifest = new Manifest();
            $manifest->setSender($senderContact);

            // Handleing barcodes
            foreach ($requestData['barcodes'] as $barcode) {
                $order = new Order();
                $order->setTracking($barcode);
                $order->setQuantity($requestData['quantity']);
                $order->setWeight($requestData['weight']);
                $order->setReceiver($requestData['receiver']);
                $manifest->addOrder($order);
            }

            $result = $manifest->downloadManifest('I');
        } catch (OmnivaException $e) {
            $this->apiResult = [
                'error' => true,
                'message' => $e->getMessage()
                    . $e->getTraceAsString(),
            ];
        }
        return $this->apiResult;
    }
}
