<?php
/**
 * Copyright (c) 2012-2013 Jurian Sluiman http://juriansluiman.nl.
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
 * @author      Jurian Sluiman <jurian@juriansluiman.nl>
 * @copyright   2012-2013 Jurian Sluiman http://juriansluiman.nl.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://juriansluiman.nl
 */

namespace SlmIdealPayment\Service;

use SlmIdealPayment\Client\StandardClient;
use SlmIdealPayment\Options\StandardClientOptions;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class StandardClientFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return StandardClient
     */
    public function createService(ServiceLocatorInterface $serviceLocator, $canonical = null, $name = null)
    {
        $config = $serviceLocator->get('Config');
        $config = $config['slm_ideal_payment'];

        $options = new StandardClientOptions;
        $options->setPrivateCertificate($config['certificate']);
        $options->setKeyFile($config['key_file']);
        $options->setKeyPassword($config['key_password']);
        $options->setMerchantId($config['merchant_id']);
        $options->setSubId($config['sub_id']);

        if (true === $config['enable_validation']) {
            $options->setValidationSchema($config['validation_scheme']);
        }

        $client = new StandardClient($options);

        $httpClient = $client->getHttpClient();
        $httpClient->setAdapter('Zend\Http\Client\Adapter\Socket');
        $httpClient->getAdapter()->setOptions($config['ssl_options']);
        $client->setHttpClient($httpClient);

        if ('SlmIdealPayment\Client\StandardClient' !== $name) {
            $this->configureAcquirer($options, $config, $name);
        }

        return $client;
    }

    protected function configureAcquirer(StandardClientOptions $options, array $config, $name)
    {
        /**
         * Canonicalize name
         *
         * "SlmIdealPayment\Client\Standard\Rabobank" => "rabobank"
         */
        $name = strtolower(substr($name, strrpos($name, '\\') + 1));

        $url  = ($config['production']) ? $config[$name]['live'] : $config[$name]['test'];
        $cert = $config[$name]['certificate'];

        $options->setRequestUrl($url);
        $options->setPublicCertificate($cert);
    }
}