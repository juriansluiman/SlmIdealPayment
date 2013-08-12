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

use SlmIdealPayment\Request\RequestInterface;
use SlmIdealPayment\Response\ResponseInterface;

use SlmIdealPayment\Request\DirectoryRequest;
use SlmIdealPayment\Response\DirectoryResponse;

use SlmIdealPayment\Request\TransactionRequest;
use SlmIdealPayment\Response\TransactionResponse;

use SlmIdealPayment\Request\StatusRequest;
use SlmIdealPayment\Response\StatusResponse;

interface ClientInterface
{
    /**
     * Proxy to different request methods
     *
     * @param  RequestInterface $request
     * @return ResponseInterface
     */
    public function send(RequestInterface $request);

    /**
     * Perform request to get directory of issuers
     *
     * The returned response contains a list of all issuers connected
     * to the iDeal service. If not successfull, the response contains
     * the error message from the request and the information about the
     * failure.
     *
     * @param  DirectoryRequest $request Request parameters
     * @return DirectoryResponse         Response from request
     */
    public function sendDirectoryRequest(DirectoryRequest $request);

    /**
     * Perform request to start a transaction
     *
     * The returned response contains information about the transaction
     * to start. If not successfull, the response contains the error
     * message from the request and the information about the failure.
     *
     * @param  TransactionRequest $request Request parameters
     * @return TransactionResponse         Response from request
     */
    public function sendTransactionRequest(TransactionRequest $request);

    /**
     * Perform request to check status of transaction
     *
     * The returned response contains the status of a previously started
     * transaction. If not successfull, the response contains the error
     * message from the request and the information about the failure.
     *
     * @param  TransactionRequest $request Request parameters
     * @return TransactionResponse         Response from request
     */
    public function sendStatusRequest(StatusRequest $request);
}
