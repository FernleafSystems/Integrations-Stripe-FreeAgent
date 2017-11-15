<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Lookups;

use Ultraleet\CurrencyRates\CurrencyRates;

/**
 * Class CurrencyExchangeRates
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Lookups
 */
class CurrencyExchangeRates {

	/**
	 * @var array
	 */
	protected $aLookupCache;

	/**
	 * @var \Ultraleet\CurrencyRates\Contracts\Provider
	 */
	protected $oCurrencyDriver;

	/**
	 * @param string $sBase
	 * @param string $sTarget
	 * @param string $sDate Format: YYYY-MM-DD
	 * @return float
	 * @throws \Exception
	 */
	public function lookup( $sBase, $sTarget, $sDate ) {
		$sBase = strtoupper( $sBase );
		$sTarget = strtoupper( $sTarget );

		$nRate = $this->cacheLookup( $sBase, $sTarget, $sDate );
		if ( is_null( $nRate ) ) {
			$nRate = $this->getCurrencyProvider()
						  ->base( $sBase )
						  ->target( $sTarget )
						  ->date( $sDate )
						  ->get()
						  ->getRate( $sTarget );

			$this->cacheStore( $nRate, $sBase, $sTarget, $sDate );
		}

		return $nRate;
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