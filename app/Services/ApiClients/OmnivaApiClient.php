<?php

namespace App\Services\ApiClients;

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

class OmnivaApiClient
{
    private const baseApiUrl = 'https://edixml.post.ee';
    private string $provider;
    private array $authData = [];
    private bool $successful = false;
    private string $authServiceEndpoint = 'localhost/api';
    private bool $shouldAuthenticate = false;

    private array $headers = [];

    public function __construct(private BaseApiService $base)
    {
        $this->provider = 'omniva';
    }

    public function authorizeClient(): self
    {
        try {
            $this->authData = Http::get($this->authServiceEndpoint . '/get-api-credentials', [
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
                ]);
                $this->authData = $response->json('omniva_auth_data');
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
                ->setHeight($shippingData['heiught'])
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
                ->setMobile($shippingData['mobile'])
                ->setEmail($shippingData['email'])
                ->setPersonName($shippingData['receiver_name']);
            $package->setReceiverContact($receiverContact);

            $senderContact = new Contact();
            $senderContact
                ->setAddress($address)
                ->setMobile($shippingData['mobile'])
                ->setPersonName($shippingData['sender_name']);
            $package->setSenderContact($senderContact);

            $shipment->setPackages([$package, $package]);

            $shipment->setShowReturnCodeSms(false);
            $shipment->setShowReturnCodeEmail(false);

            $shipment->setAuth($this->authData['api_access_key'], $this->authData['api_secret']);
            $result = $shipment->registerShipment();
            if (isset($result['barcodes'])) {
                $response = [
                    'status_code' => 200,
                    'response' => $result,
                ];
            }
        } catch (OmnivaException $e) {
            $result = [
                'error' => true,
                'message' => $e->getMessage()
                    . $e->getTraceAsString(),
            ];
        }
    }

    
}
