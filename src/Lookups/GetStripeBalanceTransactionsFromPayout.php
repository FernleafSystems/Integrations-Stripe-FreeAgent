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
		/** @var BalanceTransaction[] $aBalanceTxns */
		$aBalanceTxns = array();
		/** @var BalanceTransaction[] $aRefundedCharges */
		$aRefundedCharges = array();

		$nTotalTally = 0;
		$nExpectedAmount = $this->getStripePayout()->amount;

		$oBalTxn_Charges = $this->getPayoutCharges();
		foreach ( $oBalTxn_Charges->autoPagingIterator() as $oTxn ) {
			$nTotalTally += $oTxn->net;
			$aBalanceTxns[] = $oTxn;
		}

		$oBalTxn_Refunds = $this->getPayoutRefunds();
		foreach ( $oBalTxn_Refunds->autoPagingIterator() as $oTxn ) {
			$nTotalTally += $oTxn->net;
			$aRefundedCharges[] = $oTxn;
		}

		if ( $nTotalTally != $nExpectedAmount ) {
			throw new \Exception( sprintf( 'Total tally %s does not match transfer amount %s', $nTotalTally, $nExpectedAmount ) );
		}

		// Now we remove any refunded charges TODO: assumes WHOLE charge refunds
		foreach ( $aRefundedCharges as $oRefundTxn ) {

			// with an older stripe API, we get CH_ instead of RE_ so we must load
			// up the Charge and get the Refund objects from within it.
			if ( strpos( $oRefundTxn->source, 'ch_' ) === 0 ) {
				$oCH = Charge::retrieve( $oRefundTxn->source );
				$aRefunds = $oCH->refunds;
			}
			else {
				$aRefunds = array( Refund::retrieve( $oRefundTxn->source ) );
			}

			/** @var Refund[] $aRefunds */
			foreach ( $aBalanceTxns as $nKey => $oBalTxn ) {
				foreach ( $aRefunds as $oRef ) {
					if ( $oRef->charge == $oBalTxn->source ) {
						unset( $aBalanceTxns[ $nKey ] );
					}
				}
			}
		}

		return array_values( $aBalanceTxns );
	}

	/**
	 * @param array $aParams
	 * @return Collection
	 */
	protected function sendRequest( $aParams = array() ) {
		$aRequest = array_merge(
			array(
				'payout' => $this->getStripePayout()->id,
				//				'type'   => $this->getTransactionType(),
				'limit'  => 20
			),
			$aParams
		);
		return BalanceTransaction::all( $aRequest );
	}

	/**
	 * @param array $aParams
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