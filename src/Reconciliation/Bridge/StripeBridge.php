<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge;

use FernleafSystems\ApiWrappers\Freeagent\Entities;
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
					   ->setAmount_Gross( $oBalTxn->amount/100 )
					   ->setAmount_Fee( $oBalTxn->fee/100 )
					   ->setAmount_Net( $oBalTxn->net/100 )
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

	/**
	 * @param Freeagent\DataWrapper\PayoutVO $oPayoutVO
	 * @return int|null
	 */
	public function getExternalBankTxnId( $oPayoutVO ) {
		return Payout::retrieve( $oPayoutVO->getId() )->metadata[ 'ext_bank_txn_id' ];
	}

	/**
	 * @param Freeagent\DataWrapper\PayoutVO $oPayoutVO
	 * @return int|null
	 */
	public function getExternalBillId( $oPayoutVO ) {
		return Payout::retrieve( $oPayoutVO->getId() )->metadata[ 'ext_bill_id' ];
	}

	/**
	 * @param Freeagent\DataWrapper\PayoutVO              $oPayoutVO
	 * @param Entities\BankTransactions\BankTransactionVO $oBankTxn
	 * @return $this
	 */
	public function storeExternalBankTxnId( $oPayoutVO, $oBankTxn ) {
		$oStripePayout = Payout::retrieve( $oPayoutVO->getId() );
		$oStripePayout->metadata[ 'ext_bank_txn_id' ] = $oBankTxn->getId();
		$oStripePayout->save();
		return $this;
	}

	/**
	 * @param Freeagent\DataWrapper\PayoutVO $oPayoutVO
	 * @param Entities\Bills\BillVO          $oBill
	 * @return $this
	 */
	public function storeExternalBillId( $oPayoutVO, $oBill ) {
		$oStripePayout = Payout::retrieve( $oPayoutVO->getId() );
		$oStripePayout->metadata[ 'ext_bill_id' ] = $oBill->getId();
		$oStripePayout->save();
		return $this;
	}
}