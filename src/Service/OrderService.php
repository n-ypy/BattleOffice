<?php

namespace App\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OrderService
{
    private string $commerceApiKey;
    private string $stripeApiKey;
    private string $stripeEndpointSecret;

    public function __construct(
        $commerceApiKey,
        $stripeApiKey,
        $stripeEndpointSecret,
        private ParameterBagInterface $parameterBag,
        private HttpClientInterface $client,
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private MailerInterface $mailer,
    ) {
        $this->commerceApiKey = $commerceApiKey;
        $this->stripeApiKey = $stripeApiKey;
        $this->stripeEndpointSecret = $stripeEndpointSecret;
    }

    public function sendOrder(Order $order)
    {

        if ($order->getShippingAdress() !== null) {
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

        if ($billingAdress->getAdditionalAdress() !== null) {
            $requestBody['order']['addresses']['billing']['address_line2'] = $billingAdress->getAdditionalAdress();
        }

        if ($shippingAdress->getAdditionalAdress() !== null) {
            $requestBody['order']['addresses']['shipping']['address_line2'] = $shippingAdress->getAdditionalAdress();
        }

        $response = $this->client->request('POST', 'https://api-commerce.simplon-roanne.com/order', [
            'headers' =>  [
                'Authorization' => "Bearer {$this->commerceApiKey}",
            ],
            'json' =>  $requestBody
        ]);

        $responseData = $response->toArray();

        $order->setOrderId($responseData['order_id']);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        if ($order->getPaymentMethod() === 'stripe') {
            return $this->startStripePayment($order);
        }
    }


    private function startStripePayment(Order $order)
    {
        Stripe::setApiVersion('2023-10-16');
        Stripe::setApiKey($this->stripeApiKey);

        $stripe = new StripeClient($this->stripeApiKey);

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => 'http://battleoffice.dvl.to/confirmation',
            'cancel_url' => 'http://battleoffice.dvl.to/',
            'customer_email' => $order->getCustomer()->getEmail(),
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $order->getProduct()->getName(),
                        ],
                        'unit_amount' => $order->getProduct()->getPrice(),
                        'tax_behavior' => 'inclusive'
                    ],
                    'quantity' => 1
                ]
            ],
            'metadata' => [
                'order_id'  => $order->getOrderId()
            ],
        ]);

        $order->setStripeSessionId($session->id);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return new RedirectResponse($session->url);
    }


    public function webhook()
    {
        $stripe = new StripeClient($this->stripeApiKey);

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sig_header,
                $this->stripeEndpointSecret
            );
        } catch (UnexpectedValueException $e) {
            // Invalid payload
            return (new Response())->setStatusCode(400);
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            return (new Response())->setStatusCode(400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $orderId = $event->data->object->id;
                $this->setOrderStatusAndSendEmail($orderId);
                return (new Response())->setStatusCode(200);
            default:
                echo 'Received unknown event type ' . $event->type;
                return (new Response())->setStatusCode(200);
        }
        return (new Response())->setStatusCode(400);
    }

    private function setOrderStatusAndSendEmail(string $stripeSessionId){

        $order = $this->orderRepository->findOneBy(['stripeSessionId' => $stripeSessionId]);


        $order->setStatus('PAID');

        $response = $this->client->request('POST', "https://api-commerce.simplon-roanne.com/order/{$order->getOrderId()}/status", [
            'headers' =>  [
                'Authorization' => "Bearer {$this->commerceApiKey}",
            ],
            'json' =>  [
                "status" => $order->getStatus()
            ]
        ]);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->sendOrderConfirmationEmail($order);
    }

    private function sendOrderConfirmationEmail($order){

        $email = (new TemplatedEmail())
        ->from('g404ebus@gmail.com')
        ->to($order->getCustomer()->getEmail())
        ->subject('Order confirmation ')
        ->htmlTemplate('emails/confirmation.html.twig')
        ->context([
            'name' => $order->getBillingAdress()->getFirstName(),
            'productName' => $order->getProduct()->getName(),
            'productPrice' => ($order->getProduct()->getPrice()) /100,
        ]);

        $this->mailer->send($email);
    }
}
