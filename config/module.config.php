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
 * @package     SlmIDealPayment
 * @author      Jurian Sluiman <jurian@juriansluiman.nl>
 * @copyright   2012 Jurian Sluiman.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://juriansluiman.nl
 */

return array(
    'slm_ideal_payment' => array(
        'production'   => true,
        'merchant_id'  => '',
        'sub_id'       => '',
        'certificate'  => '',
        'key_file'     => '',
        'key_password' => '',

        'enable_validation' => true,
        'validation_scheme' => __DIR__ . '/../data/xsd/AcceptantAcquirer.xsd',

        'ssl_options' => array(
            'sslcapath' => '/etc/ssl/certs',
        ),
        'abnamro' => array(
            'test'        => 'abnamro-test.ideal-payment.de/ideal/iDEALv3',
            'live'        => 'abnamro.ideal-payment.de/ideal/iDEALv3',
            'certificate' => __DIR__ . '/../data/abnamro.cer',
        ),
        'frieslandbank' => array(
            'test'        => 'https://testidealkassa.frieslandbank.nl/ideal/iDEALv3',
            'live'        => 'https://idealkassa.frieslandbank.nl/ideal/iDEALv3',
            'certificate' => __DIR__ . '/../data/certificate/frieslandbank.cer',

        ),
        'ing' => array(
            'test'        => 'https://idealtest.secure-ing.com/ideal/iDEALv3',
            'live'        => 'https://ideal.secure-ing.com/ideal/iDEALv3',
            'certificate' => __DIR__ . '/../data/certificate/ingbank.cer',
        ),
        'rabobank' => array(
            'test'        => 'https://idealtest.rabobank.nl/ideal/iDEALv3',
            'live'        => 'https://ideal.rabobank.nl/ideal/iDEALv3',
            'certificate' => __DIR__ . '/../data/certificate/rabobank.cer',
        ),
    ),

    'service_manager' => array(
        'factories' => array(
            'SlmIdealPayment\Client\StandardClient' => 'SlmIdealPayment\Service\StandardClientFactory',

            'SlmIdealPayment\Client\Standard\Rabobank' => 'SlmIdealPayment\Service\StandardClientFactory',
            'SlmIdealPayment\Client\Standard\Ing'      => 'SlmIdealPayment\Service\StandardClientFactory',
            'SlmIdealPayment\Client\Standard\AbnAmro'  => 'SlmIdealPayment\Service\StandardClientFactory',
        ),
    ),
);
