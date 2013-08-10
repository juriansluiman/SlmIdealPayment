<?php
/**
 * Copyright (c) 2013 Johan van der Heide
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
 * @author      Johan van der Heide <info@japaveh.nl>
 * @copyright   2013 Johan van der Heide.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://japaveh.nl
 */

use SlmIdealPayment\Client\StandardClient;
use Zend\Http\Client as HttpClient;

return array(
    'factories' => array(
        'SlmIdealPayment\Client\StandardClient' => function ($sm) {
            $config = $sm->get('config');
            $config = $config['ideal'];

            $client = new StandardClient;
            $client->setPrivateCertificate($config['certificate']);
            $client->setKeyFile($config['key_file']);
            $client->setKeyPassword($config['key_password']);
            $client->setMerchantId($config['merchant_id']);
            $client->setSubId($config['sub_id']);

            $httpClient = new HttpClient;
            $httpClient->setAdapter('Zend\Http\Client\Adapter\Socket');
            $httpClient->getAdapter()->setOptions($config['ssl_options']);
            $client->setHttpClient($httpClient);

            return $client;
        },
        'ideal-abn'                             => function ($sm) {
            $config = $sm->get('config');
            $config = $config['ideal'];
            $client = $sm->get('SlmIdealPayment\Client\StandardClient');

            $url  = ($config['production']) ? $config['abn']['live'] : $config['abn']['test'];
            $cert = $config['abn']['certificate'];

            $client->setRequestUrl($url);
            $client->setPublicCertificate($cert);

            return $client;
        },
        'ideal-ing'                             => function ($sm) {
            $config = $sm->get('config');
            $config = $config['ideal'];
            $client = $sm->get('SlmIdealPayment\Client\StandardClient');

            $url  = ($config['production']) ? $config['ing']['live'] : $config['ing']['test'];
            $cert = $config['ing']['certificate'];

            $client->setRequestUrl($url);
            $client->setPublicCertificate($cert);

            return $client;
        },
        'ideal-rabo'                            => function ($sm) {
            $config = $sm->get('config');
            $config = $config['ideal'];
            $client = $sm->get('SlmIdealPayment\Client\StandardClient');

            $url  = ($config['production']) ? $config['rabo']['live'] : $config['rabo']['test'];
            $cert = $config['rabo']['certificate'];

            $client->setRequestUrl($url);
            $client->setPublicCertificate($cert);

            return $client;
        },
    ),
);

