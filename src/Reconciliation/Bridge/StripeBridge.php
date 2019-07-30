<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge;

use FernleafSystems\ApiWrappers\Freeagent\Entities;
use FernleafSystems\Integrations\Freeagent;
use FernleafSystems\Integrations\Stripe_Freeagent\Lookups\GetStripeBalanceTransactionsFromPayout;
use Stripe\BalanceTransaction;
use Stripe\Charge;
use Stripe\Payout;
use Stripe\Refund;

abstract class StripeBridge implements Freeagent\Reconciliation\Bridge\BridgeInterface {

	/**
	 * This needs to be extended to add the Invoice Item details.
	 * @param string $sChargeId a Stripe Charge ID
	 * @return Freeagent\DataWrapper\ChargeVO
	 * @throws \Exception
	 */
	public function buildChargeFromTransaction( $sChargeId ) {
		$oCharge = new Freeagent\DataWrapper\ChargeVO();

		$oStripeCharge = Charge::retrieve( $sChargeId );
		$oBalTxn = BalanceTransaction::retrieve( $oStripeCharge->balance_transaction );

		$oCharge->id = $sChargeId;
		$oCharge->gateway = 'stripe';
		return $oCharge->setPaymentTerms( 14 )
					   ->setAmount_Gross( bcdiv( $oBalTxn->amount, 100, 2 ) )
					   ->setAmount_Fee( bcdiv( $oBalTxn->fee, 100, 2 ) )
					   ->setAmount_Net( bcdiv( $oBalTxn->net, 100, 2 ) )
					   ->setDate( $oStripeCharge->created )
					   ->setCurrency( $oStripeCharge->currency );
	}

	/**
	 * This needs to be extended to add the Invoice Item details.
	 * @param string $sRefundId a Stripe Refund ID
	 * @return Freeagent\DataWrapper\RefundVO
	 * @throws \Exception
	 */
	public function buildRefundFromId( $sRefundId ) {
		$oRefund = new Freeagent\DataWrapper\RefundVO();

		$oStrRefund = Refund::retrieve( $sRefundId );
		$oBalTxn = BalanceTransaction::retrieve( $oStrRefund->balance_transaction );

		return $oRefund->setId( $sRefundId )
					   ->setGateway( 'stripe' )
					   ->setAmount_Gross( bcdiv( $oBalTxn->amount, 100, 2 ) )
					   ->setAmount_Fee( bcdiv( $oBalTxn->fee, 100, 2 ) )
					   ->setAmount_Net( bcdiv( $oBalTxn->net, 100, 2 ) )
					   ->setDate( $oStrRefund->created )
					   ->setCurrency( $oStrRefund->currency );
	}

	/**
	 * @param string $sPayoutId
	 * @return Freeagent\DataWrapper\PayoutVO
	 * @throws \Exception
	 */
	public function buildPayoutFromId( $sPayoutId ) {
		$oPayout = new Freeagent\DataWrapper\PayoutVO();
		$oPayout->setId( $sPayoutId );

		$oStripePayout = Payout::retrieve( $sPayoutId );
		try {
			foreach ( $this->getStripeBalanceTransactions( $oStripePayout ) as $oBalTxn ) {
				if ( $oBalTxn->type == 'charge' ) {
					$oPayout->addCharge( $this->buildChargeFromTransaction( $oBalTxn->source ) );
				}
				else if ( $oBalTxn->type == 'refund' ) {
					$oPayout->addRefund( $this->buildRefundFromId( $oBalTxn->source ) );
				}
			}
		}
		catch ( \Exception $oE ) {
		}

		$nCompareTotal = bcmul( $oPayout->getTotalNet(), 100, 0 );
		if ( bccomp( $oStripePayout->amount, $nCompareTotal ) ) {
			throw new \Exception( sprintf( 'PayoutVO total (%s) differs from Stripe total (%s)',
				$nCompareTotal, $oStripePayout->amount ) );
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
			$aBalTxns = [];
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