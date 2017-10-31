<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Consumers;

use Stripe\Payout;

/**
 * Trait StripePayoutConsumer
 * @package FernleafSystems\Integrations\Stripe_Freeagent
 */
trait StripePayoutConsumer {

	/**
	 * @var Payout
	 */
	private $oStripePayout;

	/**
	 * @return Payout
	 */
	public function getStripePayout() {
		return $this->oStripePayout;
	}

	/**
	 * @param Payout $oPayout
	 * @return $this
	 */
	public function setStripePayout( $oPayout ) {
		$this->oStripePayout = $oPayout;
		return $this;
	}
}