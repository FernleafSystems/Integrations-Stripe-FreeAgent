<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\DataWrapper;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class StripeEventPayoutPaidSummary
 * @deprecated for use in older API versions
 * @package FernleafSystems\Integrations\Stripe_Freeagent\DataWrapper
 */
class StripeEventPayoutPaidSummary {

	use StdClassAdapter;

	/**
	 * @return int
	 */
	public function getAdjustmentFees() {
		return $this->getNumericParam( 'adjustment_fees' );
	}

	/**
	 * @return int
	 */
	public function getAdjustmentGross() {
		return $this->getNumericParam( 'adjustment_gross' );
	}

	/**
	 * @return int
	 */
	public function getCountCharges() {
		return $this->getNumericParam( 'charge_count' );
	}

	/**
	 * @return int
	 */
	public function getCountRefunds() {
		return $this->getNumericParam( 'refund_count' );
	}

	/**
	 * @return int timestamp
	 */
	public function getDate() {
		return $this->getNumericParam( 'date' );
	}

	/**
	 * @return string e.g. po_1AKGzn2ndUbEajgMWp6
	 */
	public function getId() {
		return $this->getStringParam( 'id' );
	}

	/**
	 * @return int
	 */
	public function getChargesGross() {
		return $this->getNumericParam( 'charge_gross' );
	}
	/**
	 * @return int
	 */
	public function getChargesNet() {
		return $this->getNumericParam( 'net' );
	}


	/**
	 * @return int
	 */
	public function getRefundsGross() {
		return $this->getNumericParam( 'refund_gross' );
	}

	/**
	 * @return int
	 */
	public function getTotalFees() {
		return $this->getNumericParam( 'charge_fees' );
	}
}