<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Lookups;

use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;
use Stripe\BalanceTransaction;
use Stripe\Charge;
use Stripe\Collection;
use Stripe\Refund;

/**
 * Class GetBalanceTransactionsFromPayout
 * @package BaseModule\Integration\Stripe
 */
class GetStripeBalanceTransactionsFromPayout {

	use StripePayoutConsumer;

	/**
	 * charge, refund, adjustment, application_fee, application_fee_refund,
	 * transfer, payment, payout, or payout_failure
	 * @var string
	 */
	protected $sTransactionType;

	/**
	 * @return BalanceTransaction[]
	 * @throws \Exception
	 */
	public function retrieve() {
		/** @var BalanceTransaction[] $aChargeTxns */
		$aChargeTxns = [];
		/** @var BalanceTransaction[] $aRefundedCharges */
		$aRefundedCharges = [];
		/** @var BalanceTransaction $oBalTxn */

		$nTotalTally = 0;
		$nExpectedAmount = $this->getStripePayout()->amount;

		$oBalTxn_Charges = $this->getPayoutCharges();
		foreach ( $oBalTxn_Charges->autoPagingIterator() as $oBalTxn ) {
			$nTotalTally += $oBalTxn->net;
			$aChargeTxns[] = $oBalTxn;
		}

		$oBalTxn_Refunds = $this->getPayoutRefunds();
		foreach ( $oBalTxn_Refunds->autoPagingIterator() as $oBalTxn ) {
			$nTotalTally += $oBalTxn->net;
			$aRefundedCharges[] = $oBalTxn;
		}

		if ( $nTotalTally != $nExpectedAmount ) {
			throw new \Exception( sprintf( 'Total tally %s does not match transfer amount %s', $nTotalTally, $nExpectedAmount ) );
		}

		/**
		 * So handling refunds can take 1 of 2 approaches:
		 * #1 if refund and its associated charge is within the SAME payout,
		 * we can simply just have 1 cancel the other and ignore it.
		 * #2 treat all refunds explicitly. We tally up all charges and all refunds
		 * and process them together, creating freeagent entries for all of them. This
		 * approach covers both cases.
		 */

		{// This approach only handles the case (#1) where the refund+charge are in the same payout.
			// Now we remove any refunded charges TODO: assumes WHOLE charge refunds
			foreach ( $aRefundedCharges as $nRefundKey => $oRefundTxn ) {

				// with an older stripe API, we get CH_ instead of RE_ so we must load
				// up the Charge and get the Refund objects from within it.
				if ( strpos( $oRefundTxn->source, 'ch_' ) === 0 ) {
					$oCH = Charge::retrieve( $oRefundTxn->source );
					$aRefunds = $oCH->refunds;
				}
				else {
					$aRefunds = [ Refund::retrieve( $oRefundTxn->source ) ];
				}

				/** @var Refund[] $aRefunds */
				foreach ( $aChargeTxns as $nChargeKey => $oBalTxn ) {
					foreach ( $aRefunds as $oRef ) {
						if ( $oRef->charge == $oBalTxn->source ) {
							unset( $aChargeTxns[ $nChargeKey ] );
							unset( $aRefundedCharges[ $nRefundKey ] );
						}
					}
				}
			}
		}

		return array_values( array_merge( $aChargeTxns, $aRefundedCharges ) );
	}

	/**
	 * @param array $aParams
	 * @return Collection
	 */
	protected function sendRequest( $aParams = [] ) {
		$aRequest = array_merge(
			[
				'payout' => $this->getStripePayout()->id,
				//				'type'   => $this->getTransactionType(),
				'limit'  => 20
			],
			$aParams
		);
		return BalanceTransaction::all( $aRequest );
	}

	/**
	 * @return Collection
	 */
	protected function getPayoutCharges() {
		return $this->sendRequest( [ 'type' => 'charge' ] );
	}

	/**
	 * @return Collection
	 */
	protected function getPayoutRefunds() {
		return $this->sendRequest( [ 'type' => 'refund' ] );
	}

	/**
	 * @return string
	 */
	public function getTransactionType() {
		return empty( $this->sTransactionType ) ? 'charge' : $this->sTransactionType;
	}

	/**
	 * @param string $sTransactionType
	 * @return $this
	 */
	public function setTransactionType( $sTransactionType ) {
		$this->sTransactionType = $sTransactionType;
		return $this;
	}
}