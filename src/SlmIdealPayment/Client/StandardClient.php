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
use DateTime;

use SlmIdealPayment\Request;
use SlmIdealPayment\Response;
use SlmIdealPayment\Model;

use Zend\Http\Client   as HttpClient;
use Zend\Http\Response as HttpResponse;

use SlmIdealPayment\Exception;

class StandardClient implements ClientInterface
{
    protected $requestUrl;
    protected $publicCertificate;
    protected $privateCertificate;
    protected $keyFile;
    protected $keyPassword;

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
     * {@inheritdoc}
     */
    public function sendDirectoryRequest(Request\DirectoryRequest $request)
    {
        // Prepare request
        $message  = $this->createMessage($request, array(
            $request->getMerchantId(),
            $request->getSubId()
        ));

        // Grab result and parse to XML object
        $response = $this->send($message);
        $xml      = $this->extractResponse($response);

        // Create response object
        $response = new Response\DirectoryResponse;
        $acquirer = (string) $xml->Acquirer->acquirerID;
        $response->setAcquirer($acquirer);

        $collection = new Model\IssuerCollection;
        foreach ($xml->Directory->children() as $item) {
            if ($item->getName() != 'Issuer') {
                continue;
            }

            $issuer = new Model\Issuer();
            $issuer->setId((string) $item->issuerID);
            $issuer->setName((string) $item->issuerName);
            $issuer->setType((string) $item->issuerList);
            $collection->append($issuer);
        }
        $response->setIssuers($collection);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function sendTransactionRequest(Request\TransactionRequest $request)
    {
        // Prepare request
        $message  = $this->createMessage($request, array(
            $request->getIssuer()->getId(),
            $request->getMerchantId(),
            $request->getSubId(),
            $request->getReturnUrl(),
            $request->getTransaction()->getPurchaseId(),
            $request->getTransaction()->getAmount(),
            $request->getTransaction()->getCurrency(),
            $request->getTransaction()->getLanguage(),
            $request->getTransaction()->getDescription(),
            $request->getTransaction()->getEntranceCode()
        ));

        $message->Merchant->addChild('merchantReturnURL', $request->getReturnUrl());

        $issuer = $message->addChild('Issuer');
        $issuer->addChild('issuerID', $request->getIssuer()->getId());

        $transaction = $message->addChild('Transaction');
        $transaction->addChild('purchaseID',       $request->getTransaction()->getPurchaseId());
        $transaction->addChild('amount',           $request->getTransaction()->getAmount());
        $transaction->addChild('currency',         $request->getTransaction()->getCurrency());
        $transaction->addChild('expirationPeriod', $request->getTransaction()->getExpirationPeriod());
        $transaction->addChild('language',         $request->getTransaction()->getLanguage());
        $transaction->addChild('description',      $request->getTransaction()->getDescription());
        $transaction->addChild('entranceCode',     $request->getTransaction()->getEntranceCode());

        // Grab result and parse to XML object
        $response = $this->send($message);
        $xml      = $this->extractResponse($response);

        // Create response object
        $response = new Response\TransactionResponse;
        $acquirer = (string) $xml->Acquirer->acquirerID;
        $url      = (string) $xml->Issuer->issuerAuthenticationURL;
        $response->setAcquirer($acquirer);
        $response->setAuthenticationUrl($url);

        $transaction   = new Model\Transaction;
        $transactionId = (string) $xml->Transaction->transactionID;
        $purchaseId    = (string) $xml->Transaction->purchaseID;
        $transaction->setTransactionId($transactionId);
        $transaction->setPurchaseId($purchaseId);

        $response->setTransaction($transaction);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function sendStatusRequest(Request\StatusRequest $request)
    {
        $message  = $this->createMessage($request, array(
            $request->getMerchantId(),
            $request->getSubId(),
            $request->getTransaction()->getTransactionId()
        ));

        $transaction = $message->addChild('Transaction');
        $transaction->addChild('transactionID', $request->getTransaction()->getTransactionId());

        $response = $this->send($message);
        $response = new Response\StatusResponse;

        return $response;
    }

    protected function createMessage(Request\RequestInterface $request, array $signedFields = array())
    {
        $class = get_class($request);
        $class = substr($class, strrpos($class, '\\') + 1);

        switch ($class) {
            case 'DirectoryRequest':
                $type = 'DirectoryReq';
                break;
            case 'TransactionRequest':
                $type = 'AcquirerTrxReq';
                break;
            case 'StatusRequest':
                $type = 'AcquirerStatusReq';
                break;
        }

        // Start to create message
        $xml = new SimpleXMLElement(sprintf('<?xml version="1.0" encoding="UTF-8"?><%1$s></%1$s>', $type));
        $xml->addAttribute('xmlns', 'http://www.idealdesk.com/Message');
        $xml->addAttribute('version', '1.1.0');

        // Every message needs a time stamp
        $date  = gmdate('Y-m-d\TH:i:s.000\Z');
        $xml->addChild('createDateTimeStamp', $date);

        // Set standard fields
        $merchant = $xml->addChild('Merchant');
        $merchant->addChild('merchantID', $request->getMerchantId());
        $merchant->addChild('subID', $request->getSubId());
        $merchant->addChild('authentication', 'SHA1_RSA');

        // Set cryptographic fields
        $fingerprint = $this->getFingerprint();
        $signature   = $this->getMessageSignature($date, $signedFields);

        $merchant->addChild('token', $fingerprint);
        $merchant->addChild('tokenCode', $signature);

        return $xml;
    }

    protected function getFingerprint($public = false)
    {
        $certificate = ($public) ? $this->getPublicCertificate() : $this->getPrivateCertificate();

        if (false === ($fp = fopen($certificate, 'r'))) {
            throw new Exception\CertificateNotFoundException(
                'Cannot find the certificate at %s', $certificate
            );
        }

        $rawData = fread($fp, 8192);
        $data    = openssl_x509_read($rawData);
        fclose($fp);

        if (!openssl_x509_export($data, $data)) {
            throw new Exception\CertificateNotValidException(
                'Certificate %s cannot be read due to errors in the file', $certificate
            );
        }

        $data = str_replace(array('-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'), '', $data);
        return strtoupper(sha1(base64_decode($data)));
    }

    protected function getMessageSignature($timestamp, array $signedFields)
    {
        $message = $timestamp;
        foreach ($signedFields as $value) {
            $message .= $value;
        }

        $message = str_replace(array(" ", "\t", "\n"), array('', '', ''), $message);
        $keyFile = $this->getKeyFile();
        $keyPwd  = $this->getKeyPassword();


        if(false === ($fp = fopen($this->keyFile, 'r'))) {
            throw new Exception\CertificateNotFoundException(
                'Cannot find the keyfile at %s', $certificate
            );
        }

        $keyFile = fread($fp, 8192);
        fclose($fp);

        if (!$privateKey = openssl_pkey_get_private($keyFile, $keyPwd)) {
            throw new Exception\CertificateNotValidException(
                'Certificate %s cannot be opened with the provided password', $certificate
            );
        }

        $signature = '';
        if (!openssl_sign($message, $signature, $privateKey)) {
            throw new Exception\CertificateNotValidException(
                'Message cannot be signed with certificate %s due to errors in the file', $certificate
            );
        }

        openssl_free_key($privateKey);
        return base64_encode($signature);
    }

    protected function send(SimpleXMLElement $xml)
    {
        $data   = $xml->asXml();

        $client = new HttpClient($this->getRequestUrl());
        $client->setRawBody($data);
        return $client->send();
    }

    protected function extractResponse(HttpResponse $response)
    {
        if (!$response->isOk()) {
            throw new Exception\HttpRequestException(
                'Request is not successfully executed'
            );
        }

        $body = $response->getBody();
        $xml  = simplexml_load_string($body);

        if (isset($xml->Error)) {
            $error = $xml->Error;
            throw new Exception\IdealRequestException(
                sprintf('%s (%s): "%s"', $error->errorMessage, $error->errorCode, $error->errorDetail)
            );
        }

        return $xml;
    }
}