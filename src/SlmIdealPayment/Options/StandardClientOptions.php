<?php

namespace SlmIdealPayment\Options;

use Zend\Stdlib\AbstractOptions;

class StandardClientOptions extends AbstractOptions
{
    /**
     * Turn off strict options mode
     */
    protected $__strictMode__ = false;

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
     * Getter for requestUrl
     *
     * @return string
     */
    public function getRequestUrl()
    {
        return $this->requestUrl;
    }

    /**
     * Setter for requestUrl
     *
     * @param string $requestUrl Value to set
     * @return self
     */
    public function setRequestUrl($requestUrl)
    {
        $this->requestUrl = $requestUrl;
        return $this;
    }

    /**
     * Getter for publicCertificate
     *
     * @return string
     */
    public function getPublicCertificate()
    {
        return $this->publicCertificate;
    }

    /**
     * Setter for publicCertificate
     *
     * @param string $publicCertificate Value to set
     * @return self
     */
    public function setPublicCertificate($publicCertificate)
    {
        $this->publicCertificate = $publicCertificate;
        return $this;
    }


    /**
     * Getter for privateCertificate
     *
     * @return string
     */
    public function getPrivateCertificate()
    {
        return $this->privateCertificate;
    }

    /**
     * Setter for privateCertificate
     *
     * @param string $privateCertificate Value to set
     * @return self
     */
    public function setPrivateCertificate($privateCertificate)
    {
        $this->privateCertificate = $privateCertificate;
        return $this;
    }

    /**
     * Getter for keyFile
     *
     * @return string
     */
    public function getKeyFile()
    {
        return $this->keyFile;
    }

    /**
     * Setter for keyFile
     *
     * @param string $keyFile Value to set
     * @return self
     */
    public function setKeyFile($keyFile)
    {
        $this->keyFile = $keyFile;
        return $this;
    }

    /**
     * Getter for keyPassword
     *
     * @return string
     */
    public function getKeyPassword()
    {
        return $this->keyPassword;
    }

    /**
     * Setter for keyPassword
     *
     * @param string $keyPassword Value to set
     * @return self
     */
    public function setKeyPassword($keyPassword)
    {
        $this->keyPassword = $keyPassword;
        return $this;
    }

    /**
     * Getter for validation schema
     *
     * @return string
     */
    public function getValidationSchema()
    {
        return $this->validationSchema;
    }

    /**
     * Setter for validation schema
     *
     * @param string $validationSchema Value to set
     * @return self
     */
    public function setValidationSchema($validationSchema)
    {
        $this->validationSchema = $validationSchema;
        return $this;
    }

    /**
     * Getter for merchantId
     *
     * @return string
     */
    public function getMerchantId()
    {
        return $this->merchantId;
    }

    /**
     * Setter for merchantId
     *
     * @param string $merchantId Value to set
     * @return self
     */
    public function setMerchantId($merchantId)
    {
        $this->merchantId = $merchantId;
        return $this;
    }

    /**
     * Getter for subId
     *
     * @return string
     */
    public function getSubId()
    {
        return $this->subId;
    }

    /**
     * Setter for subId
     *
     * @param string $subId Value to set
     * @return self
     */
    public function setSubId($subId)
    {
        $this->subId = $subId;
        return $this;
    }

}
