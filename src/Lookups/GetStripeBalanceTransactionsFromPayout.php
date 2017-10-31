<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Lookups;

use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\DataWrapper\StripeEventPayoutPaidSummary;
use Stripe\BalanceTransaction;
use Stripe\Charge;
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
		$oPayout = $this->getStripePayout();
		$oPayoutSummary = ( new StripeEventPayoutPaidSummary() )
			->setRawData( $oPayout->summary );

		$nExpectedAmount = $oPayout->amount;
		if ( $oPayoutSummary->getAdjustmentFees() != 0 ) {
			$nExpectedAmount += $oPayoutSummary->getAdjustmentFees();
		}
		if ( $oPayoutSummary->getAdjustmentGross() != 0 ) {
			$nExpectedAmount += ( $oPayoutSummary->getAdjustmentGross()*-1 );
		}

		$bProcessRefunds = ( $oPayoutSummary->getRefundsGross() != 0 );

		$nTotalTally = 0;

		$aBalanceTxns = array();

		$oBalTxn_Collection = $this->sendRequest();
		/** @var BalanceTransaction $oBalTxn */
		foreach ( $oBalTxn_Collection->autoPagingIterator() as $oBalTxn ) {

			if ( $bProcessRefunds ) {
				usleep( 500 ); // Lookup the charge via the source
				$oStripeCharge = Charge::retrieve( $oBalTxn->source );
				if ( $oStripeCharge->refunded ) {
					continue;
				}
			}
			$nTotalTally += $oBalTxn->net;
			$aBalanceTxns[] = $oBalTxn;
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
		$oPayout = $this->getStripePayout();
		$aRequest = array_merge(
			array(
				'payout' => $oPayout->id,
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