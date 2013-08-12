<?php

namespace SlmIdealPaymentTest\Client;

use PHPUnit_Framework_TestCase as TestCase;
use SlmIdealPayment\Client\StandardClient;
use SlmIdealPayment\Options\StandardClientOptions;
use SlmIdealPayment\Request;

use Zend\Http\Client as HttpClient;

class StandardClientTest extends TestCase
{
    public function testSendProxiesToRequestMethods()
    {
        $options = new StandardClientOptions;
        $client  = $this->getMock('SlmIdealPayment\Client\StandardClient', array(
            'sendDirectoryRequest',
            'sendTransactionRequest',
            'sendStatusRequest',
        ), array($options));

        $client->expects($this->once())
               ->method('sendDirectoryRequest');

        $request = new Request\DirectoryRequest;
        $client->send($request);

        $client->expects($this->once())
               ->method('sendTransactionRequest');

        $request = new Request\TransactionRequest;
        $client->send($request);

        $client->expects($this->once())
               ->method('sendStatusRequest');

        $request = new Request\StatusRequest;
        $client->send($request);
    }

    public function testClientInstantiatesHttpClient()
    {
        $options = new StandardClientOptions;
        $client  = new StandardClient($options);

        $expected = 'Zend\Http\Client';
        $actual   = $client->getHttpClient();

        $this->assertInstanceOf($expected, $actual);
    }

    public function testClientCanHaveHttpClientInjected()
    {
        $options = new StandardClientOptions;
        $client  = new StandardClient($options);

        $httpClient = new HttpClient;
        $client->setHttpClient($httpClient);

        $expected = spl_object_hash($httpClient);
        $actual   = spl_object_hash($client->getHttpClient());

        $this->assertEquals($expected, $actual);
    }
}
