<?php

namespace SlmIdealPayment\Client\StandardClient;

use DOMDocument;
use XMLSecurityDSig;
use XMLSecurityKey;

class Signature
{
    const XMLSECLIBS_PATH = '/../../../../data/xmlseclibs.php';

    public function __construct()
    {
        /**
         * We cannot load this xmlseclibs via composer
         *
         * Check if the class exists to only load the
         * library once.
         */
        if (!class_exists('XMLSecurityDSig')) {
            include __DIR__ . self::XMLSECLIBS_PATH;
        }
    }

    public function sign(DOMDocument $document, $certificate, $keyfile, $passphrase = null)
    {
        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $dsig->addReference($document, XMLSecurityDSig::SHA256,
            array('http://www.w3.org/2000/09/xmldsig#enveloped-signature'),
            array('force_uri' => true)
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type' => 'private'));
        if ($passphrase !== null) {
            $key->passphrase = $passphrase;
        }

        $key->loadKey($keyfile, true);
        $dsig->sign($key);

        $dsig->addKeyInfoAndName($this->getFingerprint($certificate));
        $dsig->appendSignature($document->documentElement);
    }

    public function verify(DOMDocument $document, $certificate)
    {
        $dsig = new XMLSecurityDSig();

        if (!$dsig->locateSignature($document)) {
            throw new \Exception("Cannot locate Signature Node");
        }

        $dsig->canonicalizeSignedInfo();

        if (!$dsig->validateReference()) {
            throw new \Exception("Reference Validation Failed");
        }

        $key = $dsig->locateKey();
        if (!$key) {
            throw new \Exception("We have no idea about the key");
        }

        $key->loadKey($certificate, true);

        return (bool) $dsig->verify($key);
    }

    protected function getFingerprint($path)
    {
        if (false === ($fp = fopen($path, 'r'))) {
            throw new Exception\CertificateNotFoundException('Cannot open certificate file');
        }

        $rawData = fread($fp, 8192);
        $data    = openssl_x509_read($rawData);
        fclose($fp);

        if (!openssl_x509_export($data, $data)) {
            throw new Exception\CertificateNotValidException('Error in certificate');
        }

        $data = str_replace(array('-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'), '', $data);
        return strtoupper(sha1(base64_decode($data)));
    }
}