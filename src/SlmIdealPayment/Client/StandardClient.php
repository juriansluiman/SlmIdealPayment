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

use SimpleXMLElement;
use DOMDocument;

use RunTimeException;

use SlmIdealPayment\Request;
use SlmIdealPayment\Response;
use SlmIdealPayment\Model;

use Zend\Http\Client as HttpClient;
use Zend\Http\Response as HttpResponse;
use Zend\Math\Rand;

use SlmIdealPayment\Exception;

class StandardClient implements ClientInterface
{
    /**
     * @var string
     */
    protected $requestUrl;
    protected $merchantId;
    protected $subId;
    protected $publicCertificate;
    protected $privateCertificate;
    protected $keyFile;
    protected $keyPassword;

    protected $httpClient;

    /**
     * @var string
     */
    const EXPIRATION = 'PT1H';
    const CURRENCY   = 'EUR';

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
     * Create request for a list of available issuers
     *
     * Return array is a key=>value for a shortlist and a longlist of
     * issuers available with keys respectively 'short' and 'long'.
     *
     * @return array
     */
    public function sendDirectoryRequest(Request\DirectoryRequest $directoryRequest)
    {
        $list = $this->_requestIssuers();
        return $list;
    }

    /**
     * Create a request to start a transaction
     *
     * Based on the order information, a request is send to the iDeal server
     * to request for a url. The url will be used to send the user to, to
     * perform the actual transaction.
     *
     * @param Request\TransactionRequest $transactionRequest
     * @return array
     */
    public function sendTransactionRequest(Request\TransactionRequest $transactionRequest)
    {
        $xml = $this->_createXmlForRequestTransaction(
            array(
                'issuerId'    => $transactionRequest->getIssuer()->getId(),
                'merchantId'  => $this->getMerchantId(),
                'subId'       => $this->getSubId(),
                'returnUrl'   => $transactionRequest->getReturnUrl(),
                'purchaseId'  => $transactionRequest->getTransaction()->getPurchaseId(),
                'amount'      => round($transactionRequest->getTransaction()->getAmount() / 100, 2),
                'expiration'  => self::EXPIRATION,
                'currency'    => self::CURRENCY,
                'language'    => $transactionRequest->getTransaction()->getLanguage(),
                'description' => $transactionRequest->getTransaction()->getDescription(),
                'entrance'    => $transactionRequest->getTransaction()->getEntranceCode()
            )
        );

        $response = $this->_postMessageXml($xml->saveXML());

        $authenticationUrl = '';
        $transactionId     = '';
        $purchaseId        = '';
        foreach ($response->children() as $child) {
            if ('Issuer' === $child->getName()) {
                foreach ($child->children() as $property) {
                    if ('issuerAuthenticationURL' === $property->getName()) {
                        $authenticationUrl = (string)$property;
                    }
                }
            } elseif ('Transaction' === $child->getName()) {
                foreach ($child->children() as $property) {
                    if ('transactionID' === $property->getName()) {
                        $transactionId = (string)$property;
                    } elseif ('purchaseID' === $property->getName()) {
                        $purchaseId = (string)$property;
                    }
                }
            }
        }

        $response = new Response\TransactionResponse();
        $response->setAuthenticationUrl($authenticationUrl);
        $transaction = new Model\Transaction();
        $transaction->setTransactionId($transactionId);
        $transaction->setPurchaseId($purchaseId);
        $response->setTransaction($transaction);

        return $response;
    }

    /**
     * Create a request to validate the transaction
     *
     * Every transaction must be validated if the payment is completed. The
     * request is made to the iDeal server, based on the given transactionId
     * which was a return value for the request for transaction.
     *
     * In case of a failed transaction, false will be returned. In case of a
     * succeeded transaction, true will be returned.
     *
     * @param Request\StatusRequest $statusRequest
     * @return array
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

        $response = $this->_postMessageXml($xml->saveXML());

        $transaction = array();
        foreach ($response->children() as $child) {
            if ('Transaction' === $child->getName()) {
                foreach ($child->children() as $property) {
                    $transaction[$property->getName()] = (string)$property;
                }
            }
        }

        $transactionModel = new Model\Transaction();
        $transactionModel->setTransactionId($transaction['transactionID']);
        $transactionModel->setStatus($transaction['status']);

        $consumer = new Model\Consumer();
        $consumer->setName($transaction['consumerName']);
        $consumer->setAccountIBAN($transaction['consumerIBAN']);
        $consumer->setAccountBIC($transaction['consumerBIC']);

        $transactionModel->setAmount($transaction['amount']);
        $transactionModel->setCurrency($transaction['currency']);
        $transactionModel->setConsumer($consumer);

        $transactionResponse = new Response\TransactionResponse();
        $transactionResponse->setTransaction($transactionModel);

        return $transactionResponse;
    }

    /**
     * @return Model\Issuer[]
     * @throws \RuntimeException
     */
    protected function _requestIssuers()
    {
        $xml = $this->createXmlForRequestIssuers(
            array(
                'merchantId' => $this->getMerchantId(),
                'subId'      => $this->getSubId(),
            )
        );


        $response = $this->_postMessageXml($xml->saveXML());


        if ('DirectoryRes' !== $response->getName()) {
            throw new \RuntimeException('iDeal error: expects DirectoryRes as root element');
        }

        $countries = array();
        foreach ($response->Directory->children() as $child) {
            if ('Country' !== $child->getName()) {
                continue;
            }
            $country = (string)$child->countryNames;


            $list = array();
            foreach ($child->children() as $issuer) {
                if ('Issuer' !== $issuer->getName()) {
                    continue;
                }

                $issuerModel = new Model\Issuer();
                $issuerModel->setId((string)$issuer->issuerID);
                $issuerModel->setName((string)$issuer->issuerName);

                $list[] = $issuerModel;
            }

            $countries[$country] = $list;
        }


        return $countries;
    }

    protected function _postMessage(DOMDocument $document)
    {
        $xml = $document->saveXML();

        $response = $this->_postMessageXml($xml);
        return $this->_parseResponse($response);
    }

    /**
     * @param $xml
     * @return SimpleXMLElement
     * @throws \Exception
     */
    protected function _postMessageXml($xml)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->getRequestUrl());
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $output = curl_exec($ch);
        curl_close($ch);

        $prev = libxml_use_internal_errors(true);

        $xml = simplexml_load_string($output);
        if ($xml === false) {
            $errors = "";
            foreach (libxml_get_errors() as $error) {
                $errors .= sprintf("%s\n", $error->message);
            }
            throw new \Exception(sprintf("The response packet could not be successfully parsed: %s", $errors));
        }
        libxml_use_internal_errors($prev);

        return $xml;
    }

    protected function _parseResponse($response)
    {
        if (!$this->_verify($response)) {
            throw new RuntimeException('Response from server is invalid!');
        }

        $xml = new SimpleXMLElement($response);

        if (isset($xml->Error)) {
            $error = $xml->Error;

            $message = sprintf(
                '%s (%s): %s',
                $error->errorMessage,
                $error->errorCode,
                $error->errorDetail
            );

            throw new RuntimeException($message);
        }

        return $xml;
    }

    protected function getFingerprint($public = false)
    {
        $certificate = ($public) ? $this->getPublicCertificate() : $this->getPrivateCertificate();

        if (false === ($fp = fopen($certificate, 'r'))) {
            throw new RuntimeException('Cannot open certificate file');
        }

        $rawData = fread($fp, 8192);
        $data    = openssl_x509_read($rawData);
        fclose($fp);

        if (!openssl_x509_export($data, $data)) {
            throw new RuntimeException('Error in certificate');
        }

        $data = str_replace(array('-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'), '', $data);
        return strtoupper(sha1(base64_decode($data)));
    }

    protected function _getSignature($message)
    {
        if (false === ($fp = fopen($this->_keyFile, 'r'))) {
            throw new RuntimeException('Cannot open key file');
        }

        $keyFile = fread($fp, 8192);
        fclose($fp);

        if (!$privateKey = openssl_pkey_get_private($keyFile, $this->_keyPassword)) {
            throw new RuntimeException('Invalid password for key file');
        }

        $signature = '';
        if (!opensslsign($message, $signature, $privateKey)) {
            throw new RuntimeException('Cannot sign message with private key');
        }

        openssl_free_key($privateKey);
        return base64_encode($signature);
    }

    public function getEntranceCode(Deal_Model_Order $order)
    {
        return Rand::getString(40, implode(array_merge(range('a', 'z'), range('A', 'Z'), range(1, 9))));
    }

    protected function sign(DOMDocument $document)
    {
        $objDSig = new \XMLSecurityDSig();
        $objDSig->setCanonicalMethod(\XMLSecurityDSig::EXC_C14N);
        $objDSig->addReference(
            $document,
            \XMLSecurityDSig::SHA256,
            array('http://www.w3.org/2000/09/xmldsig#enveloped-signature'),
            array('force_uri' => true)
        );

        $objKey             = new \XMLSecurityKey(\XMLSecurityKey::RSA_SHA256, array('type' => 'private'));
        $objKey->passphrase = $this->getKeyPassword();
        $objKey->loadKey($this->getKeyFile(), true);
        $objDSig->sign($objKey);

        $objDSig->addKeyInfoAndName($this->getFingerprint());
        $objDSig->appendSignature($document->documentElement);

        return $document;
    }

    protected function _verify($response)
    {
        $document = new DOMDocument();
        $document->loadXML($response);

        $objXMLSecDSig = new \XMLSecurityDSig();
        $objDSig       = $objXMLSecDSig->locateSignature($document);

        if (!$objDSig) {
            throw new \Exception("Cannot locate Signature Node");
        }
        $objXMLSecDSig->canonicalizeSignedInfo();
        $retVal = $objXMLSecDSig->validateReference();

        if (!$retVal) {
            throw new \Exception("Reference Validation Failed");
        }

        $objKey = $objXMLSecDSig->locateKey();
        if (!$objKey) {
            throw new \Exception("We have no idea about the key");
        }

        $objKey->loadKey($this->getPublicCertificate(), true);

        if ($objXMLSecDSig->verify($objKey)) {
            return true;
        }
        return false;
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
        $document = new \DOMDocument();
        $document->loadXML($xml);
        // Sign document and return
        return $this->sign($document);
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

        return $this->sign($document);
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

        // Sign document and return
        return $this->sign($document);
    }
}