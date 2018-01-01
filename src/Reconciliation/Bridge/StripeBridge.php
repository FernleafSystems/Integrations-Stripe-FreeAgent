<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge;

use FernleafSystems\Integrations\Freeagent;
use FernleafSystems\Integrations\Stripe_Freeagent\Lookups\GetStripeBalanceTransactionsFromPayout;
use Stripe\BalanceTransaction;
use Stripe\Charge;
use Stripe\Payout;

abstract class StripeBridge implements Freeagent\Reconciliation\Bridge\BridgeInterface {

	/**
	 * This needs to be extended to add the Invoice Item details.
	 * @param string $sTxnID a Stripe Charge ID
	 * @return Freeagent\DataWrapper\ChargeVO
	 * @throws \Exception
	 */
	public function buildChargeFromTransaction( $sTxnID ) {
		$oCharge = new Freeagent\DataWrapper\ChargeVO();

		$oStripeCharge = Charge::retrieve( $sTxnID );
		$oBalTxn = BalanceTransaction::retrieve( $oStripeCharge->balance_transaction );

		return $oCharge->setId( $sTxnID )
					   ->setGateway( 'stripe' )
					   ->setPaymentTerms( 14 )
					   ->setAmount_Gross( $oBalTxn->amount )
					   ->setAmount_Fee( $oBalTxn->fee )
					   ->setAmount_Net( $oBalTxn->net )
					   ->setDate( $oStripeCharge->created )
					   ->setCurrency( $oStripeCharge->currency );
	}

	/**
	 * @param string $sPayoutId
	 * @return Freeagent\DataWrapper\PayoutVO
	 */
	public function buildPayoutFromId( $sPayoutId ) {
		$oPayout = new Freeagent\DataWrapper\PayoutVO();
		$oPayout->setId( $sPayoutId );

		$oStripePayout = Payout::retrieve( $sPayoutId );
		try {
			foreach ( $this->getStripeBalanceTransactions( $oStripePayout ) as $oBalTxn ) {
				$oPayout->addCharge( $this->buildChargeFromTransaction( $oBalTxn->source ) );
			}
		}
		catch ( \Exception $oE ) {
		}

		$oPayout->setDateArrival( $oStripePayout->arrival_date )
				->setCurrency( $oStripePayout->currency );

		return $oPayout;
	}

	/**
	 * @param Payout $oStripePayout
	 * @return BalanceTransaction[]
	 */
	protected function getStripeBalanceTransactions( $oStripePayout ) {
		try {
			$aBalTxns = ( new GetStripeBalanceTransactionsFromPayout() )
				->setStripePayout( $oStripePayout )
				->retrieve();
		}
		catch ( \Exception $oE ) {
			$aBalTxns = array();
		}
		return $aBalTxns;
	}
}