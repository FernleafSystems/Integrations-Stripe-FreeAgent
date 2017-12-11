<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Lookups;

use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;
use Stripe\BalanceTransaction;
use Stripe\Collection;

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

		$nExpectedAmount = $this->getStripePayout()->amount;

		/** @var BalanceTransaction[] $aRefundedCharges */
		$aRefundedCharges = array();

		$nTotalTally = 0;
		$oBalTxn_Collection = $this->sendRequest();
		/** @var BalanceTransaction $oTxn */
		foreach ( $oBalTxn_Collection->autoPagingIterator() as $oTxn ) {

			// do not do 'payout' / 'transfer'
			if ( in_array( $oTxn->type, array( 'charge', 'refund' ) ) ) {
				$nTotalTally += $oTxn->net;

				if ( $oTxn->type == 'refund' ) {
					$aRefundedCharges[] = $oTxn;
				}
				else if ( $oTxn->type == 'charge' ) {
					$aBalanceTxns[] = $oTxn;
				}
			}
		}

		if ( $nTotalTally != $nExpectedAmount ) {
			throw new \Exception( sprintf( 'Total tally %s does not match transfer amount %s', $nTotalTally, $nExpectedAmount ) );
		}

		// Now we remove any refunded charges TODO: assumes WHOLE charge refunds
		foreach ( $aRefundedCharges as $oRefundTxn ) {
			foreach ( $aBalanceTxns as $nKey => $oBalTxn ) {
				if ( $oRefundTxn->source == $oBalTxn->source ) {
					unset( $aBalanceTxns[ $nKey ] );
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