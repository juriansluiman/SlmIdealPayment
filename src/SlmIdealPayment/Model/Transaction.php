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
 * @subpackage  Model
 * @author      Jurian Sluiman <jurian@juriansluiman.nl>
 * @copyright   2012 Jurian Sluiman.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://juriansluiman.nl
 */

namespace SlmIdealPayment\Model;

class Transaction
{
    const STATUS_UNKNOWN   = 'Unknown';
    const STATUS_OPEN      = 'Open';
    const STATUS_SUCCESS   = 'Success';
    const STATUS_FAILURE   = 'Failure';
    const STATUS_CANCELLED = 'Cancelled';
    const STATUS_EXPIRED   = 'Expired';

    protected $purchaseId;
    protected $amount;
    protected $expirationPeriod;
    protected $description;
    protected $entranceCode;
    protected $transactionId;

    protected $status = self::STATUS_UNKNOWN;
    /**
     * @var Consumer;
     */
    protected $consumer;

    protected $language = 'nl';
    protected $currency = 'EUR';

    public function getPurchaseId()
    {
        return $this->purchaseId;
    }

    public function setPurchaseId($purchaseId)
    {
        $this->purchaseId = $purchaseId;
        return $this;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    public function getExpirationPeriod()
    {
        return $this->expirationPeriod;
    }

    public function setExpirationPeriod($expirationPeriod)
    {
        $this->expirationPeriod = $expirationPeriod;
        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    public function getEntranceCode()
    {
        return $this->entranceCode;
    }

    public function setEntranceCode($entranceCode)
    {
        $this->entranceCode = $entranceCode;
        return $this;
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }

    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        if (!in_array(
            $status,
            array(
                self::STATUS_UNKNOWN,
                self::STATUS_OPEN,
                self::STATUS_SUCCESS,
                self::STATUS_FAILURE,
                self::STATUS_CANCELLED,
                self::STATUS_EXPIRED
            )
        )
        ) {
            throw new Exception(
                'Cannot set status, "%s" is an invalid status', $status
            );
        }

        $this->status = $status;
        return $this;
    }

    /**
     * @return Consumer
     * @throws RuntimeException
     */
    public function getConsumer()
    {
        if (self::STATUS_UNKNOWN === $this->getStatus()) {
            throw new RuntimeException(
                'Cannot get consumer, status of transaction is unkown'
            );
        }

        return $this->consumer;
    }

    public function setConsumer(Consumer $consumer)
    {
        $this->consumer = $consumer;
        return $this;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }
}