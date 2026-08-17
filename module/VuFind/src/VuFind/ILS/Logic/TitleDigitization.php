<?php

/**
 * Title Digitization Logic Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ILS\Logic;

use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Connection as ILSConnection;

use function in_array;
use function is_array;
use function is_bool;

/**
 * Title Digitization Logic Class.
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TitleDigitization
{
    /**
     * Constructor.
     *
     * @param \VuFind\Auth\ILSAuthenticator $ilsAuth ILS authenticator
     * @param ILSConnection                 $catalog A catalog connection
     * @param \VuFind\Crypt\HMAC            $hmac    HMAC generator
     * @param \VuFind\Config\Config         $config  VuFind configuration
     */
    public function __construct(
        protected \VuFind\Auth\ILSAuthenticator $ilsAuth,
        protected ILSConnection $catalog,
        protected \VuFind\Crypt\HMAC $hmac,
        protected \VuFind\Config\Config $config
    ) {
    }

    /**
     * Public method for getting title/item level digitization requests.
     *
     * @param string $id            A Bib ID
     * @param string $level         Request level ('title' or 'item')
     * @param array  $linkOverrides Optional id and source to override standard record driver
     *
     * @return string|bool URL to place request, or false if option unavailable
     */
    public function getRequest($id, $level = 'title', array $linkOverrides = [])
    {
        $mode = $this->catalog->getTitleDigitizationMode();
        if ($mode == 'disabled') {
            return false;
        }

        try {
            $patron = $this->ilsAuth->storedCatalogLogin();
            if (!$patron) {
                return false;
            }
        } catch (ILSException $e) {
            return false;
        }

        return $this->generateRequest($id, $level, $patron, $linkOverrides);
    }

    /**
     * Get holdings for a particular record.
     *
     * @param string $id ID to retrieve
     *
     * @return array
     */
    protected function getHoldings($id)
    {
        static $holdings = [];

        if (!isset($holdings[$id])) {
            $holdings[$id] = $this->catalog->getHolding($id)['holdings'];
        }
        return $holdings[$id];
    }

    /**
     * Protected method for generating the request URL.
     *
     * @param string $id            A Bib ID
     * @param string $level         The request level ('title' or 'item')
     * @param array  $patron        Patron
     * @param array  $linkOverrides Optional id and source to override standard record driver
     *
     * @return mixed A url on success, boolean false on failure
     */
    protected function generateRequest($id, $level, $patron, array $linkOverrides = [])
    {
        $data = [
            'id' => $id,
            'level' => $level,
        ];

        $checkRequests = $this->catalog->checkFunction(
            'Digitization',
            compact('id', 'patron')
        );

        if ($checkRequests) {
            return $this->getRequestDetails($data, $checkRequests['HMACKeys'], $linkOverrides);
        }
        return false;
    }

    /**
     * Get Request Details.
     *
     * Supplies the form details required to place a request
     *
     * @param array $data          An array of item data
     * @param array $HMACKeys      An array of keys to hash
     * @param array $linkOverrides Optional id and source to override standard record driver
     *
     * @return array          Details for generating URL
     */
    protected function getRequestDetails($data, $HMACKeys, array $linkOverrides)
    {
        $HMACkey = $this->hmac->generate($HMACKeys, $data);

        $queryString = [];
        foreach ($data as $key => $param) {
            if (in_array($key, $HMACKeys)) {
                $queryString[] = $key . '=' . urlencode($param);
            }
        }

        $queryString[] = 'hashKey=' . urlencode($HMACkey);
        $queryString = implode('&', $queryString);

        return [
            'action' => 'DigitizationRequest',
            'record' => $linkOverrides['id'] ?? $data['id'],
            'source' => $linkOverrides['source'] ?? DEFAULT_SEARCH_BACKEND,
            'query' => $queryString,
            'anchor' => '#tabnav',
        ];
    }
}