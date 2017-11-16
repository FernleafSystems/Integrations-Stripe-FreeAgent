<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Lookups;

use Stripe\Charge;
use Stripe\Payout;

/**
 * Class FindPayoutFromStripeCharge
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Lookups
 */
class FindPayoutFromStripeCharge {

	/**
	 * @param string $sStripeCharge
	 * @return Payout
	 * @throws \Exception
	 */
	public function lookup( $sStripeCharge ) {

		$oCharge = Charge::retrieve( $sStripeCharge );

		$oPayoutCollection = Payout::all(
			array(
				'created' => array(
					'gt' => $oCharge->created,
					'lt' => $oCharge->created + 2592000 // ~month
				)
			)
		);

		$oTxnLoader = new GetStripeBalanceTransactionsFromPayout();

		$oThePayout = null;
		/** @var Payout $oPayout */
		foreach ( $oPayoutCollection->autoPagingIterator() as $oPayout ) {
			$oTxnLoader->setStripePayout( $oPayout );

			foreach ( $oTxnLoader->retrieve() as $oTxn ) {
				if ( $oTxn->source == $oCharge->id ) {
					$oThePayout = $oPayout;
					break( 2 );
				}
			}
		}

		return $oThePayout;
	}

	/**
	 * @param string $sBase
	 * @param string $sTarget
	 * @param string $sDate
	 * @return float
	 */
	protected function cacheLookup( $sBase, $sTarget, $sDate ) {
		$nRate = null;
		$aCache = $this->getLookupCache();
		if ( isset( $aCache[ $sBase ] ) && isset( $aCache[ $sBase ][ $sTarget ] )
			 && isset( $aCache[ $sBase ][ $sTarget ][ $sDate ] ) ) {
			$nRate = $aCache[ $sBase ][ $sTarget ][ $sDate ];
		}
		return $nRate;
	}

	/**
	 * @param float  $nRate
	 * @param string $sBase
	 * @param string $sTarget
	 * @param string $sDate
	 * @return $this
	 */
	protected function cacheStore( $nRate, $sBase, $sTarget, $sDate ) {

		$aCache = $this->getLookupCache();

		if ( !isset( $aCache[ $sBase ] ) ) {
			$aCache[ $sBase ] = array();
		}
		if ( !isset( $aCache[ $sBase ][ $sTarget ] ) ) {
			$aCache[ $sBase ][ $sTarget ] = array();
		}
		$aCache[ $sBase ][ $sTarget ][ $sDate ] = $nRate;

		return $this->setLookupCache( $aCache );
	}

	/**
	 * @return \Ultraleet\CurrencyRates\Contracts\Provider
	 */
	public function getCurrencyProvider() {
		if ( !isset( $this->oCurrencyDriver ) ) {
			$this->oCurrencyDriver = ( new CurrencyRates() )->driver( 'fixer' );
		}
		return $this->oCurrencyDriver;
	}

	/**
	 * @return array
	 */
	public function getLookupCache() {
		if ( !is_array( $this->aLookupCache ) ) {
			$this->aLookupCache = array();
		}
		return $this->aLookupCache;
	}

	/**
	 * @param \Ultraleet\CurrencyRates\Contracts\Provider $oCurrencyDriver
	 * @return $this
	 */
	public function setCurrencyDriver( $oCurrencyDriver ) {
		$this->oCurrencyDriver = $oCurrencyDriver;
		return $this;
	}

	/**
	 * @param array $aLookupCache
	 * @return CurrencyExchangeRates
	 */
	public function setLookupCache( $aLookupCache ) {
		$this->aLookupCache = $aLookupCache;
		return $this;
	}
}