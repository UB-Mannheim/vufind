<?php

/**
 * Digitization requests trait (for subclasses of AbstractRecord).
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;

use function in_array;
use function is_array;

/**
 * Digitization requests trait (for subclasses of AbstractRecord).
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait DigitizationTrait
{
    /**
     * Action for dealing with digitization requests.
     *
     * @return mixed
     */
    public function digitizationRequestAction()
    {
        $driver = $this->loadRecord();

        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $catalog = $this->getILS();
        $id = $driver->getUniqueID();

        $checkRequests = $catalog->checkFunction(
            'Digitization',
            compact('id', 'patron')
        );
        if (!$checkRequests) {
            return $this->redirectToRecord();
        }

        $gatheredDetails = $this->digitizationRequests()->validateRequest(
            $checkRequests['HMACKeys']
        );
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        $validRequest = $catalog->checkDigitizationRequestIsValid(
            $id,
            $gatheredDetails,
            $patron
        );
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $this->getFlashMessenger()->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status']
                    : 'digitization_request_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        $extraFields = isset($checkRequests['extraFields'])
            ? explode(':', $checkRequests['extraFields']) : [];

        $pickup = $catalog->getPickUpLocations($patron, $gatheredDetails);
        if (in_array('pickUpLocation', $extraFields) && !$pickup) {
            $this->getFlashMessenger()
                ->addErrorMessage('No pickup locations available');
            return $this->redirectToRecord('#top');
        }

        if (null !== $this->params()->fromPost('placeDigitizationRequest')) {
            $validPickup = $this->digitizationRequests()->validatePickUpInput(
                $gatheredDetails['pickUpLocation'] ?? null,
                $extraFields,
                $pickup
            );
            if (!$validPickup) {
                $this->getFlashMessenger()
                    ->addErrorMessage('digitization_request_invalid_pickup');
            } else {
                $details = $gatheredDetails + ['patron' => $patron];

                $function = (string)$checkRequests['function'];
                $results = $catalog->$function($details);

                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'digitization_request_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()
                                ->fromRoute('myresearch-digitizationrequests'),
                        ],
                    ];
                    $this->getFlashMessenger()->addSuccessMessage($msg);
                    $this->getViewRenderer()->plugin('session')->put('reset_account_status', true);

                    $this->getAuditEventService()->addEvent(
                        AuditEventType::ILS,
                        AuditEventSubtype::PlaceDigitizationRequest,
                        $this->getUser(),
                        data: [
                            'username' => $patron['cat_username'],
                            'details' => $details,
                        ]
                    );

                    return $this->redirectToRecord($this->inLightbox() ? '?layout=lightbox' : '');
                } else {
                    if (isset($results['status'])) {
                        $this->getFlashMessenger()->addErrorMessage($results['status']);
                    }
                    if (isset($results['sysMessage'])) {
                        $this->getFlashMessenger()
                            ->addErrorMessage($results['sysMessage']);
                    }
                }
            }
        }

        $defaultRequiredDate = $this->digitizationRequests()
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequiredDate = $this->getService(\VuFind\Date\Converter::class)
            ->convertToDisplayDate('U', $defaultRequiredDate);

        try {
            $defaultPickup = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }

        $config = $this->getConfigArray();
        $homeLibrary = ($config['Account']['set_home_library'] ?? true)
            ? $this->getUser()->getHomeLibrary() : '';
        $helpText = $helpTextHtml = $checkRequests['helpText'] ?? '';

        $view = $this->createViewModel(
            compact(
                'gatheredDetails',
                'pickup',
                'defaultPickup',
                'homeLibrary',
                'extraFields',
                'defaultRequiredDate',
                'helpText',
                'helpTextHtml'
            )
        );
        $view->setTemplate('record/digitizationrequest');
        return $view;
    }
}