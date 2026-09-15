<?php

/**
 * Title digitization logic test.
 *
 * PHP version 8
 *
 * Copyright (C) Mannheim University Library 2026.
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
 * @package  Tests
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\ILS\Logic;

use VuFind\Auth\ILSAuthenticator;
use VuFind\Crypt\HMAC;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\TitleDigitization;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Title digitization logic test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TitleDigitizationTest extends \PHPUnit\Framework\TestCase
{
    use ReflectionTrait;

    /**
     * Get a TitleDigitization object for testing.
     *
     * @param ?\VuFind\Auth\ILSAuthenticator $ilsAuth ILS authenticator (null for mock)
     * @param ?ILSConnection                 $catalog A catalog connection (null for mock)
     * @param ?\VuFind\Crypt\HMAC            $hmac    HMAC generator (null for mock)
     * @param ?array                         $config  Configuration array (empty by default)
     *
     * @return TitleDigitization
     */
    protected function getTitleDigitizationLogic(
        ?ILSAuthenticator $ilsAuth = null,
        ?Connection $catalog = null,
        ?HMAC $hmac = null,
        array $config = []
    ): TitleDigitization {
        return new TitleDigitization(
            $ilsAuth ?? $this->createStub(ILSAuthenticator::class),
            $catalog ?? $this->createStub(Connection::class),
            $hmac ?? $this->createStub(HMAC::class),
            $config
        );
    }

    /**
     * Create an availability status for testing.
     *
     * @param bool   $available   Whether the item is available
     * @param string $description Description text
     *
     * @return \VuFind\ILS\Logic\AvailabilityStatus
     */
    protected function createAvailabilityStatus(
        bool $available = true,
        string $description = 'Available'
    ): \VuFind\ILS\Logic\AvailabilityStatus {
        $status = $available
            ? \VuFind\ILS\Logic\AvailabilityStatusInterface::STATUS_AVAILABLE
            : \VuFind\ILS\Logic\AvailabilityStatusInterface::STATUS_UNAVAILABLE;
        return new \VuFind\ILS\Logic\AvailabilityStatus($status, $description);
    }

    /**
     * Data provider for testHideHoldingsBehavior().
     *
     * @return \Iterator
     */
    public static function suppressedLocationsProvider(): \Iterator
    {
        yield 'default' => [[], []];
        yield 'non-empty list' => [['Record' => ['hide_holdings' => ['a', 'b', 'c']]], ['a', 'b', 'c']];
    }

    /**
     * Test that the hide_holdings setting is processed correctly.
     *
     * @param array $configArray  Configuration array
     * @param array $expectedList Expected suppressed locations list
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('suppressedLocationsProvider')]
    public function testHideHoldingsBehavior(array $configArray, array $expectedList): void
    {
        $logic = $this->getTitleDigitizationLogic(config: $configArray);
        $this->assertEquals($expectedList, $this->getProperty($logic, 'hideHoldings'));
    }

    /**
     * Test that digitization requests can be disabled.
     *
     * @return void
     */
    public function testDisabledMode(): void
    {
        $catalog = $this->createMock(Connection::class);
        $catalog->expects($this->once())->method('getTitleDigitizationMode')->willReturn('disabled');
        $logic = $this->getTitleDigitizationLogic(catalog: $catalog);
        $this->assertFalse($logic->getDigitizationRequest('foo'));
    }

    /**
     * Test a failed catalog login in driver mode.
     *
     * @return void
     */
    public function testFailedCatalogLogin(): void
    {
        $catalog = $this->createMock(Connection::class);
        $catalog->expects($this->once())->method('getTitleDigitizationMode')->willReturn('driver');
        $ilsAuth = $this->createMock(ILSAuthenticator::class);
        $ilsAuth->expects($this->once())->method('storedCatalogLogin')->willReturn(false);
        $logic = $this->getTitleDigitizationLogic(catalog: $catalog, ilsAuth: $ilsAuth);
        $this->assertFalse($logic->getDigitizationRequest('foo'));
    }

    /**
     * Test that no link is generated in availability mode when an item is
     * available for loan.
     *
     * @return void
     */
    public function testAvailabilityModeNoLinkWhenAvailable(): void
    {
        $catalog = $this->createMock(Connection::class);
        $catalog->method('getTitleDigitizationMode')->willReturn('availability');
        $catalog->method('checkFunction')
            ->willReturn(['function' => 'someFunction', 'HMACKeys' => ['id']]);
        $catalog->method('getHolding')->willReturn([
            'holdings' => [
                ['availability' => $this->createAvailabilityStatus(true), 'location' => 'general'],
            ],
        ]);
        $ilsAuth = $this->createMock(ILSAuthenticator::class);
        $ilsAuth->method('storedCatalogLogin')->willReturn(['id' => 'patron']);
        $logic = $this->getTitleDigitizationLogic(catalog: $catalog, ilsAuth: $ilsAuth);
        $this->assertFalse($logic->getDigitizationRequest('avail-1'));
    }

    /**
     * Test that a link is generated in availability mode when no item is
     * available for loan.
     *
     * @return void
     */
    public function testAvailabilityModeLinkWhenUnavailable(): void
    {
        $catalog = $this->createMock(Connection::class);
        $catalog->method('getTitleDigitizationMode')->willReturn('availability');
        $catalog->method('checkFunction')
            ->willReturn(['function' => 'someFunction', 'HMACKeys' => ['id']]);
        $catalog->method('getHolding')->willReturn([
            'holdings' => [
                ['availability' => $this->createAvailabilityStatus(false), 'location' => 'general'],
            ],
        ]);
        $ilsAuth = $this->createMock(ILSAuthenticator::class);
        $ilsAuth->method('storedCatalogLogin')->willReturn(['id' => 'patron']);
        $hmac = $this->createMock(HMAC::class);
        $hmac->method('generate')->willReturn('mockhash');
        $logic = $this->getTitleDigitizationLogic(catalog: $catalog, ilsAuth: $ilsAuth, hmac: $hmac);
        $this->assertEquals(
            [
                'action' => 'DigitizationRequest',
                'record' => 'unavail-1',
                'source' => DEFAULT_SEARCH_BACKEND,
                'query'  => 'id=unavail-1&hashKey=mockhash',
                'anchor' => '#tabnav',
            ],
            $logic->getDigitizationRequest('unavail-1')
        );
    }

    /**
     * Test that a suppressed location does not count toward availability in
     * availability mode.
     *
     * @return void
     */
    public function testAvailabilityModeHiddenLocationIsIgnored(): void
    {
        $catalog = $this->createMock(Connection::class);
        $catalog->method('getTitleDigitizationMode')->willReturn('availability');
        $catalog->method('checkFunction')
            ->willReturn(['function' => 'someFunction', 'HMACKeys' => ['id']]);
        $catalog->method('getHolding')->willReturn([
            'holdings' => [
                ['availability' => $this->createAvailabilityStatus(true), 'location' => 'hidden'],
            ],
        ]);
        $ilsAuth = $this->createMock(ILSAuthenticator::class);
        $ilsAuth->method('storedCatalogLogin')->willReturn(['id' => 'patron']);
        $hmac = $this->createMock(HMAC::class);
        $hmac->method('generate')->willReturn('mockhash');
        $config = ['Record' => ['hide_holdings' => ['hidden']]];
        $logic = $this->getTitleDigitizationLogic(
            catalog: $catalog,
            ilsAuth: $ilsAuth,
            hmac: $hmac,
            config: $config
        );
        $this->assertEquals(
            [
                'action' => 'DigitizationRequest',
                'record' => 'hidden-1',
                'source' => DEFAULT_SEARCH_BACKEND,
                'query'  => 'id=hidden-1&hashKey=mockhash',
                'anchor' => '#tabnav',
            ],
            $logic->getDigitizationRequest('hidden-1')
        );
    }
}
