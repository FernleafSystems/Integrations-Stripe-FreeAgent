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
	public function retrieve( $bCollectRefunds = false ) {
		$aBalanceTxns = array();

		$nExpectedAmount = $this->getStripePayout()->amount;

		$nTotalTally = 0;
		$oBalTxn_Collection = $this->sendRequest();
		/** @var BalanceTransaction $oBalTxn */
		foreach ( $oBalTxn_Collection->autoPagingIterator() as $oBalTxn ) {

			if ( $oBalTxn->type == 'charge' ) {
				$nTotalTally += $oBalTxn->net;
			}
			else {
				$nTotalTally -= $oBalTxn->net;
			}
			
			if ( $oBalTxn->type == 'charge' || $bCollectRefunds ) {
				$aBalanceTxns[] = $oBalTxn;
			}
		}

		if ( $nTotalTally != $nExpectedAmount ) {
			throw new \Exception( sprintf( 'Total tally %s does not match transfer amount %s', $nTotalTally, $nExpectedAmount ) );
		}

		return $aBalanceTxns;
	}

	/**
	 * @param array $aParams
	 * @return Collection
	 */
	protected function sendRequest( $aParams = array() ) {
		$aRequest = array_merge(
			array(
				'payout' => $this->getStripePayout()->id,
				'type'   => $this->getTransactionType(),
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