<?php

/**
 * VuFind Action Helper - Digitization Requests Support Methods.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Plugin;

/**
 * Action helper to perform digitization request-related actions.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DigitizationRequests extends AbstractRequestBase
{
    /**
     * Getting a default required date based on digitization request settings.
     *
     * @param array $checkRequests Digitization request settings returned by the ILS driver's
     * checkFunction method.
     *
     * @return int A timestamp representing the default required date
     */
    public function getDefaultRequiredDate($checkRequests)
    {
        $dateArray = isset($checkRequests['defaultRequiredDate'])
             ? explode(':', $checkRequests['defaultRequiredDate'])
             : [0, 1, 0];

        if ($dateArray[0] == 'driver') {
            return 0;
        }

        return $this->getDateFromArray($dateArray);
    }
}