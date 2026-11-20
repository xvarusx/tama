<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require 'vendor/autoload.php';

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\InvoiceReference;
use App\Entity\Payment;
use App\Enum\InstallmentStatus;
use App\Enum\PaymentDirection;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;

class TestClient extends WebTestCase {
    public function testRoutes(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        // Create schema for tests
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Create a payment
        $payment = new Payment(
            'PYMT-SHOW-001',
            PaymentDirection::INCOMING,
            PaymentMethod::BANK_TRANSFER,
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            100000
        );
        $payment->confirm();
        $entityManager->persist($payment);
        $entityManager->flush();

        echo 'Payment ID: ' . $payment->getId()->toString() . "\n";

        $client->request('GET', '/payments/' . $payment->getId()->toString());
        
        echo 'Status: ' . $client->getResponse()->getStatusCode() . "\n";
        echo 'Content: ' . $client->getResponse()->getContent() . "\n";
    }
}

$test = new TestClient();
TestClient::bootKernel();
$test->testRoutes();