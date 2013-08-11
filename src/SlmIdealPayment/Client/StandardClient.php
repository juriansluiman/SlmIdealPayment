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

use Zend\Http\Client as HttpClient;
use Zend\Http\Response as HttpResponse;

use SlmIdealPayment\Exception;

class StandardClient implements ClientInterface
{
    /**#@+
     * @var string
     */
    protected $requestUrl;
    protected $merchantId;
    protected $subId;
    protected $publicCertificate;
    protected $privateCertificate;
    protected $keyFile;
    protected $keyPassword;
    protected $validationSchema;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    public function getRequestUrl()
    {
        return $this->requestUrl;
    }

    public function setRequestUrl($requestUrl)
    {
        $this->requestUrl = $requestUrl;
        return $this;
    }

    public function getPublicCertificate()
    {
        return $this->publicCertificate;
    }

    public function setPublicCertificate($publicCertificate)
    {
        $this->publicCertificate = $publicCertificate;
        return $this;
    }

    public function getPrivateCertificate()
    {
        return $this->privateCertificate;
    }

    public function setPrivateCertificate($privateCertificate)
    {
        $this->privateCertificate = $privateCertificate;
        return $this;
    }

    public function getKeyFile()
    {
        return $this->keyFile;
    }

    public function setKeyFile($keyFile)
    {
        $this->keyFile = $keyFile;
        return $this;
    }

    public function getKeyPassword()
    {
        return $this->keyPassword;
    }

    public function setKeyPassword($keyPassword)
    {
        $this->keyPassword = $keyPassword;
        return $this;
    }

    /**
     * Getter for validation schema
     *
     * @return mixed
     */
    public function getValidationSchema()
    {
        return $this->validationSchema;
    }

    /**
     * Setter for validation schema
     *
     * @param mixed $validationSchema Value to set
     * @return self
     */
    public function setValidationSchema($validationSchema)
    {
        $this->validationSchema = $validationSchema;
        return $this;
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
     * @param mixed $merchantId
     */
    public function setMerchantId($merchantId)
    {
        $this->merchantId = $merchantId;
    }

    /**
     * @return mixed
     */
    public function getMerchantId()
    {
        return $this->merchantId;
    }

    /**
     * @param mixed $subId
     */
    public function setSubId($subId)
    {
        $this->subId = $subId;
    }

    /**
     * @return mixed
     */
    public function getSubId()
    {
        return $this->subId;
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
    public function sendDirectoryRequest(Request\DirectoryRequest $directoryRequest)
    {
        $xml = $this->createXmlForRequestIssuers(array(
            'merchantId' => $this->getMerchantId(),
            'subId'      => $this->getSubId(),
        ));

        $response = $this->request($xml);

        if ('DirectoryRes' !== $response->firstChild->nodeName) {
            throw new Exception\IdealRequestException('Expecting DirectoryRes as root element in response');
        }

        $countries = array();
        foreach ($response->getElementsByTagName('Country') as $child) {
            $country = $child->getElementsByTagName('countryNames')->item(0)->textContent;

            $list = array();
            foreach ($child->getElementsByTagName('Issuer') as $issuer) {
                $issuerModel = new Model\Issuer();
                $issuerModel->setId($this->getTag($issuer, 'issuerID'));
                $issuerModel->setName($this->getTag($issuer, 'issuerName'));

                $list[] = $issuerModel;
            }

            $countries[$country] = $list;
        }

        // @todo create a DirectoryResponse and insert all issuers there
        return $countries;
    }

    /**
     * {@inheritdoc}
     */
    public function sendTransactionRequest(Request\TransactionRequest $transactionRequest)
    {
        $xml = $this->_createXmlForRequestTransaction(array(
            'issuerId'    => $transactionRequest->getIssuer()->getId(),
            'merchantId'  => $this->getMerchantId(),
            'subId'       => $this->getSubId(),
            'returnUrl'   => $transactionRequest->getReturnUrl(),
            'purchaseId'  => $transactionRequest->getTransaction()->getPurchaseId(),
            'amount'      => $transactionRequest->getTransaction()->getAmount(),
            'expiration'  => $transactionRequest->getTransaction()->getExpirationPeriod(),
            'currency'    => $transactionRequest->getTransaction()->getCurrency(),
            'language'    => $transactionRequest->getTransaction()->getLanguage(),
            'description' => $transactionRequest->getTransaction()->getDescription(),
            'entrance'    => $transactionRequest->getTransaction()->getEntranceCode()
        ));

        $response = $this->request($xml);

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
    public function sendStatusRequest(Request\StatusRequest $statusRequest)
    {
        $xml = $this->_createXmlForRequestStatus(
            array(
                'merchantId'    => $this->getMerchantId(),
                'subId'         => $this->getSubId(),
                'transactionId' => $statusRequest->getTransaction()->getTransactionId()
            )
        );

        $response = $this->request($xml);

        if ('AcquirerStatusRes' !== $response->firstChild->nodeName) {
            throw new Exception\IdealRequestException('Expecting AcquirerStatusRes as root element in response');
        }

        $transaction = new Model\Transaction();
        $transaction->setTransactionId($this->getTag($response, 'transactionID'));
        $transaction->setStatus($this->getTag($response, 'status'));
        // statusDateTimestamp

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
        $client->setUri($this->getRequestUrl());
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
        $signature->sign($document, $this->getPrivateCertificate(), $this->getKeyFile(), $this->getKeyPassword());
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

        if (!$signature->verify($document, $this->getPublicCertificate())) {
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
        if (null === ($schema = $this->getValidationSchema())) {
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
     * @param  array $data
     * @return DOMDocument
     */
    protected function createXmlForRequestIssuers(array $data)
    {
        $timestamp = utf8_encode(gmdate('Y-m-d\TH:i:s.000\Z'));
        $merchant  = $data['merchantId'];
        $subid     = $data['subId'];

        $xml      = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<DirectoryReq xmlns="http://www.idealdesk.com/ideal/messages/mer-acq/3.3.1" version="3.3.1">
  <createDateTimestamp>$timestamp</createDateTimestamp>
  <Merchant>
    <merchantID>$merchant</merchantID>
    <subID>$subid</subID>
  </Merchant>
</DirectoryReq>
EOT;
        $document = new DOMDocument();
        $document->loadXML($xml);

        $this->sign($document);
        $this->validate($document);

        return $document;
    }

    protected function _createXmlForRequestTransaction(array $data)
    {
        $timestamp = utf8_encode(gmdate('Y-m-d\TH:i:s.000\Z'));

        $issuer = $data['issuerId'];

        $merchant  = $data['merchantId'];
        $subid     = $data['subId'];
        $returnUrl = $data['returnUrl'];

        $purchaseId  = $data['purchaseId'];
        $amount      = $data['amount'];
        $currency    = $data['currency'];
        $expiration  = $data['expiration'];
        $language    = $data['language'];
        $description = $data['description'];
        $entrance    = $data['entrance'];

        $xml      = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<AcquirerTrxReq xmlns="http://www.idealdesk.com/ideal/messages/mer-acq/3.3.1" version="3.3.1">
  <createDateTimestamp>$timestamp</createDateTimestamp>
  <Issuer>
    <issuerID>$issuer</issuerID>
  </Issuer>
  <Merchant>
    <merchantID>$merchant</merchantID>
    <subID>$subid</subID>
    <merchantReturnURL>$returnUrl</merchantReturnURL>
  </Merchant>
  <Transaction>
    <purchaseID>$purchaseId</purchaseID>
    <amount>$amount</amount>
    <currency>$currency</currency>
    <expirationPeriod>$expiration</expirationPeriod>
    <language>$language</language>
    <description>$description</description>
    <entranceCode>$entrance</entranceCode>
  </Transaction>
</AcquirerTrxReq>
EOT;
        $document = new DOMDocument();
        $document->loadXML($xml);

        $this->sign($document);
        $this->validate($document);

        return $document;
    }

    protected function _createXmlForRequestStatus(array $data)
    {
        $timestamp = utf8_encode(gmdate('Y-m-d\TH:i:s.000\Z'));

        $merchant = $data['merchantId'];
        $subid    = $data['subId'];

        $transactionId = $data['transactionId'];

        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<AcquirerStatusReq xmlns="http://www.idealdesk.com/ideal/messages/mer-acq/3.3.1" version="3.3.1">
  <createDateTimestamp>$timestamp</createDateTimestamp>
  <Merchant>
    <merchantID>$merchant</merchantID>
    <subID>$subid</subID>
  </Merchant>
  <Transaction>
    <transactionID>$transactionId</transactionID>
  </Transaction>
</AcquirerStatusReq>
EOT;

        $document = new DOMDocument();
        $document->loadXML($xml);

        $this->sign($document);

        if (!$document->schemaValidate($this->getValidationSchema())) {
            throw new Exception\XmlValidationException('Generated XML for status request could not be validated');
        }

        return $document;
    }
}