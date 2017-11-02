<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills;

use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;
use Stripe\BalanceTransaction;

/**
 * Class CountTotalFeesForStripePayout
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills
 */
class CountTotalFeesForStripePayout {

	use StripePayoutConsumer;

	public function count() {

		$oFeeCollection = BalanceTransaction::all(
			array(
				'payout' => $this->getStripePayout()->id,
				'type'   => 'charge',
				'limit'  => 20
			)
		);

		$nTotalFees = 0;
		foreach ( $oFeeCollection->autoPagingIterator() as $oStripeFee ) {
			$nTotalFees += $oStripeFee->fee;
		}

		return $nTotalFees;
	}
}