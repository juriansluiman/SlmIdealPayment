<?php
/**
 * Copyright (c) 2012 Jurian Sluiman.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the names of the copyright holders nor the names of the
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package     SlmIdealPayment
 * @subpackage  Client
 * @author      Jurian Sluiman <jurian@juriansluiman.nl>
 * @copyright   2012 Jurian Sluiman.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://juriansluiman.nl
 */

namespace SlmIdealPayment\Client;

use DOMDocument;
use DOMNode;

use SlmIdealPayment\Request;
use SlmIdealPayment\Response;
use SlmIdealPayment\Model;
use SlmIdealPayment\Client\StandardClient\Signature;
use SlmIdealPayment\Options\StandardClientOptions;
use SlmIdealPayment\Exception;

use Zend\Http\Client as HttpClient;
use Zend\Http\Response as HttpResponse;

class StandardClient implements ClientInterface
{
    /**
     * @var Options
     */
    protected $options;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    public function __construct(StandardClientOptions $options)
    {
        $this->options = $options;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getHttpClient()
    {
        if (!$this->httpClient instanceof HttpClient) {
            $this->httpClient = new HttpClient;
        }

        return $this->httpClient;
    }

    public function setHttpClient(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Request\RequestInterface $request)
    {
        switch (get_class($request)) {
            case 'SlmIdealPayment\Request\DirectoryRequest':
                return $this->sendDirectoryRequest($request);
                break;
            case 'SlmIdealPayment\Request\TransactionRequest':
                return $this->sendTransactionRequest($request);
                break;
            case 'SlmIdealPayment\Request\StatusRequest':
                return $this->sendStatusRequest($request);
                break;
            default:
                throw new Exception\InvalidArgumentException('Unknown class for send() proxy method');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sendDirectoryRequest(Request\DirectoryRequest $request)
    {
        $document = $this->getDirectoryRequestXML();
        $response = $this->request($document);

        if ('DirectoryRes' !== $response->firstChild->nodeName) {
            throw new Exception\IdealRequestException('Expecting DirectoryRes as root element in response');
        }

        $countries = array();
        foreach ($response->getElementsByTagName('Country') as $child) {
            $name = $child->getElementsByTagName('countryNames')->item(0)->textContent;

            $issuers = array();
            foreach ($child->getElementsByTagName('Issuer') as $issuer) {
                $issuerModel = new Model\Issuer();
                $issuerModel->setId($this->getTag($issuer, 'issuerID'));
                $issuerModel->setName($this->getTag($issuer, 'issuerName'));

                $issuers[] = $issuerModel;
            }

            $country = new Model\Country;
            $country->setName($name);
            $country->setIssuers($issuers);

            $countries[] = $country;
        }

        $response = new Response\DirectoryResponse;
        $response->setCountries($countries);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function sendTransactionRequest(Request\TransactionRequest $request)
    {
        $document = $this->getTransactionRequestXML($request);
        $response = $this->request($document);

        if ('AcquirerTrxRes' !== $response->firstChild->nodeName) {
            throw new Exception\IdealRequestException('Expecting AcquirerTrxRes as root element in response');
        }

        $url = $this->getTag($response, 'issuerAuthenticationURL');

        $transaction = new Model\Transaction();
        $transaction->setTransactionId($this->getTag($response, 'transactionID'));
        $transaction->setPurchaseId($this->getTag($response, 'purchaseID'));

        $response = new Response\TransactionResponse();
        $response->setAuthenticationUrl($url);
        $response->setTransaction($transaction);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function sendStatusRequest(Request\StatusRequest $request)
    {
        $document = $this->getStatusRequestXML($request);
        $response = $this->request($document);

        if ('AcquirerStatusRes' !== $response->firstChild->nodeName) {
            throw new Exception\IdealRequestException('Expecting AcquirerStatusRes as root element in response');
        }

        $transaction = new Model\Transaction();
        $transaction->setTransactionId($this->getTag($response, 'transactionID'));
        $transaction->setStatus($this->getTag($response, 'status'));
        // @todo add statusDateTimestamp

        $consumer = new Model\Consumer();
        $consumer->setName($this->getTag($response, 'consumerName'));
        $consumer->setAccountIBAN($this->getTag($response, 'consumerIBAN'));
        $consumer->setAccountBIC($this->getTag($response, 'consumerBIC'));

        $transaction->setAmount($this->getTag($response, 'amount'));
        $transaction->setCurrency($this->getTag($response, 'currency'));
        $transaction->setConsumer($consumer);

        $response = new Response\TransactionResponse();
        $response->setTransaction($transaction);

        return $response;
    }

    protected function request(DOMDocument $document)
    {
        $client = $this->getHttpClient();
        $client->setUri($this->getOptions()->getRequestUrl());
        $client->setRawBody($document->saveXML());

        $response = $client->send();

        if (!$response->isOk()) {
            // @todo supply status code + message
            throw new Exception\HttpRequestException(
                'Request is not successfully executed'
            );
        }

        $body = $response->getBody();

        $document = new DOMDocument;
        $document->loadXML($body);

        $this->validate($document);
        $this->verify($document);

        $errors = $document->getElementsByTagName('Error');
        if ($errors->length !== 0) {
            $error = $errors->item(0);

            $code    = $this->getTag($error, 'errorCode');
            $message = $this->getTag($error, 'errorMessage');
            $detail  = $this->getTag($error, 'errorDetail');

            throw new Exception\IdealRequestException(
                sprintf('%s (%s): "%s"', $message, $code, $detail)
            );
        }

        return $document;
    }

    /**
     * Sign document and append <Signature> dom node
     *
     * @param  DOMDocument $document
     * @return void
     */
    protected function sign(DOMDocument $document)
    {
        $signature = new Signature;
        $signature->sign(
            $document,
            $this->getOptions()->getPrivateCertificate(),
            $this->getOptions()->getKeyFile(),
            $this->getOptions()->getKeyPassword()
        );
    }

    /**
     * Verify provided document
     *
     * @param  DOMDocument $document
     * @throws Exception\IdealRequestException If the signature is not valid
     * @return void
     */
    protected function verify(DOMDocument $document)
    {
        $signature = new Signature;

        if (!$signature->verify($document, $this->getOptions()->getPublicCertificate())) {
            throw new Exception\IdealRequestException('iDEAL response could not be verified from acquirer');
        }
    }

    /**
     * Validate XML document with XSD schema
     *
     * @param  DOMDocument $document
     * @throws Exception\XmlValidationException If document is not valid
     * @return void
     */
    protected function validate(DOMDocument $document)
    {
        if (null === ($schema = $this->getOptions()->getValidationSchema())) {
            return;
        }

        if (!$document->schemaValidate($schema)) {
            throw new Exception\XmlValidationException('Generated XML for directory request could not be validated');
        }
    }

    protected function getTag(DOMNode $node, $tag)
    {
        return $node->getElementsByTagName($tag)
                    ->item(0)
                    ->textContent;
    }

    /**
     * Create signed XML to request issuer listing
     *
     * @return DOMDocument
     */
    protected function getDirectoryRequestXML()
    {
        $document = $this->createXMLMessage('DirectoryReq');
        $document = $this->repairDOMDocument($document);

        $this->sign($document);
        $this->validate($document);

        return $document;
    }

    /**
     * Create signed XML to request a transaction
     *
     * @param  Request\TransactionRequest $request
     * @return DOMDocument
     */
    protected function getTransactionRequestXML(Request\TransactionRequest $request)
    {
        $document = $this->createXMLMessage('AcquirerTrxReq');

        $issuer = $document->createElement('Issuer');
        $issuer->appendChild($document->createElement('issuerID', $request->getIssuer()->getId()));

        $transaction = $document->createElement('Transaction');
        $transaction->appendChild($document->createElement('purchaseID', $request->getTransaction()->getPurchaseId()));
        $transaction->appendChild($document->createElement('amount', $request->getTransaction()->getAmount()));
        $transaction->appendChild($document->createElement('currency', $request->getTransaction()->getCurrency()));
        $transaction->appendChild($document->createElement('expirationPeriod', $request->getTransaction()->getExpirationPeriod()));
        $transaction->appendChild($document->createElement('language', $request->getTransaction()->getLanguage()));
        $transaction->appendChild($document->createElement('description', $request->getTransaction()->getDescription()));
        $transaction->appendChild($document->createElement('entranceCode', $request->getTransaction()->getEntranceCode()));

        $merchant = $document->getElementsByTagName('Merchant')->item(0);
        $merchant->appendChild($document->createElement('merchantReturnURL', $request->getReturnUrl()));

        $request = $document->getElementsByTagName('AcquirerTrxReq')->item(0);
        $request->insertBefore($issuer, $merchant);
        $request->appendChild($transaction);

        $document = $this->repairDOMDocument($document);

        $this->sign($document);
        $this->validate($document);

        return $document;
    }

    /**
     * Create signed XML to request the status of a transaction
     *
     * @param  Request\StatusReqest $request
     * @return DOMDocument
     */
    protected function getStatusRequestXML(Request\StatusRequest $request)
    {
        $document = $this->createXMLMessage('AcquirerStatusReq');

        $transaction = $document->createElement('Transaction');
        $transaction->appendChild($document->createElement('transactionID', $request->getTransaction()->getTransactionId()));

        $request = $document->getElementsByTagName('AcquirerStatusReq')->item(0);
        $request->appendChild($transaction);

        $document = $this->repairDOMDocument($document);

        $this->sign($document);
        $this->validate($document);

        return $document;
    }

    protected function createXMLMessage($rootElement)
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $request  = $document->createElement($rootElement);

        $xmlns = $document->createAttribute('xmlns');
        $xmlns->value = 'http://www.idealdesk.com/ideal/messages/mer-acq/3.3.1';

        $version = $document->createAttribute('version');
        $version->value = '3.3.1';

        $request->appendChild($xmlns);
        $request->appendChild($version);

        $merchant = $document->createElement('Merchant');
        $merchant->appendChild($document->createElement('merchantID', $this->getOptions()->getMerchantId()));
        $merchant->appendChild($document->createElement('subID', $this->getOptions()->getSubId()));

        $request->appendChild($document->createElement('createDateTimestamp', $this->getTimestamp()));
        $request->appendChild($merchant);

        $document->appendChild($request);

        return $document;
    }

    protected function getTimestamp()
    {
        return gmdate('Y-m-d\TH:i:s.000\Z');
    }

    protected function repairDOMDocument(DOMDocument $document)
    {
        $xml = $document->saveXML();

        $document = new DOMDocument;
        $document->loadXML($xml);

        return $document;
    }
}
