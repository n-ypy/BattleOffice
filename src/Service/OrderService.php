<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OrderService
{
    private string $apiKey;

    public function __construct(
        $apiKey,
        private ParameterBagInterface $parameterBag,
        private HttpClientInterface $client
    ) {
        $this->apiKey = $apiKey;
    }

    function sendOrder(Order $order)
    {

        if($order->getShippingAdress() !== null){
            $shippingAdress = $order->getShippingAdress();
        } else {
            $shippingAdress = $order->getBillingAdress();
        }

        $billingAdress = $order->getBillingAdress();
        $customer = $order->getCustomer();
        $product = $order->getProduct();

        $requestBody = [
            'order' => [
                'id' => $order->getId(),
                'product' => $product->getName(),
                'payment_method' => $order->getPaymentMethod(),
                'status' => $order->getStatus(),
                'client' => [
                    'firstname' => $billingAdress->getFirstName(),
                    'lastname' => $billingAdress->getLastName(),
                    'email' => $customer->getEmail()
                ],
                'addresses' => [
                    'billing' => [
                        'address_line1' => $billingAdress->getAdress(),
                        'city' => $billingAdress->getCity(),
                        'zipcode' => $billingAdress->getZipCode(),
                        'country' => $billingAdress->getCountry(),
                        'phone' => $billingAdress->getPhoneNumber(),
                    ],
                    'shipping' => [
                        'address_line1' => $shippingAdress->getAdress(),
                        'city' => $shippingAdress->getCity(),
                        'zipcode' => $shippingAdress->getZipCode(),
                        'country' => $shippingAdress->getCountry(),
                        'phone' => $shippingAdress->getPhoneNumber(),
                    ]
                ]
            ]
        ];

        if($billingAdress->getAdditionalAdress() !== null){
            $requestBody['order']['addresses']['billing']['address_line2'] = $billingAdress->getAdditionalAdress();
        }

        if($shippingAdress->getAdditionalAdress() !== null){
            $requestBody['order']['addresses']['shipping']['address_line2'] = $shippingAdress->getAdditionalAdress();
        }

        $response = $this->client->request('POST', 'https://api-commerce.simplon-roanne.com/order', [
            'headers' =>  [
                'Authorization' => "Bearer {$this->apiKey}",
            ],
            'json' =>  $requestBody
        ]);

        $responseData = $response->toArray();

        return $order->setOrderId($responseData['order_id']);

    }
}
