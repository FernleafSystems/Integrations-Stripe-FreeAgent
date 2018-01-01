<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge;

use FernleafSystems\Integrations\Freeagent;
use Stripe\BalanceTransaction;
use Stripe\Charge;

abstract class StripeBridge implements Freeagent\Reconciliation\Bridge\BridgeInterface {

	/**
	 * @param string $sTxnID a stripe txn ID
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
}