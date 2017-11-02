<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankTransactionExplanation;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankTransactions\BankTransactionVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankTransactions\Retrieve;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Bills\BillVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Bills\Update;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankAccountVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankTransactionVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;

/**
 * Class ExplainBankTxnWithForeignBill
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills
 */
class ExplainBankTxnWithForeignBill {

	use BankAccountVoConsumer,
		BankTransactionVoConsumer,
		StripePayoutConsumer,
		ConnectionConsumer;

	/**
	 * @param BillVO $oBill
	 * @return bool
	 * @throws \Exception
	 */
	public function createExplanation( $oBill ) {
		$oBankTransferExplTxn = $this->createAccountTransferExplanation( $oBill );
		$oLinkedTxn = $this->getNewLinkedBankTransferTransaction( $oBankTransferExplTxn );
		$oUpdatedBill = $this->updateBillWithNewValue( $oBill, $oLinkedTxn->getAmountTotal() );
		$this->createBillExplanation( $oUpdatedBill );
		return true;
	}

	/**
	 * @param BillVO $oBill
	 * @return BankTransactionExplanation\BankTransactionExplanationVO
	 * @throws \Exception
	 */
	protected function createBillExplanation( $oBill ) {
		$oExplanation = ( new BankTransactionExplanation\CreateManual() )
			->setConnection( $this->getConnection() )
			->setBankAccount( $this->getBankAccountVo() )
			->setBillPaid( $oBill )
			->setValue( $oBill->getAmountTotal() )
			->setDatedOn( $oBill->getDatedOn() )
			->create();
		if ( empty( $oExplanation ) ) {
			throw new \Exception( 'Creation of final foreign bill for Stripe failed' );
		}
		return $oExplanation;
	}

	/**
	 * @param BillVO $oBill
	 * @return BankTransactionExplanation\BankTransactionExplanationVO|null
	 * @throws \Exception
	 */
	protected function createAccountTransferExplanation( $oBill ) {

		$oBankTransferExplanationTxn = ( new BankTransactionExplanation\CreateTransferToAnotherAccount() )
			->setConnection( $this->getConnection() )
			->setBankTxn( $this->getBankTransactionVo() )
			->setTargetBankAccount( $this->getBankAccountVo() )
			->setValue( -1 * $oBill->getAmountTotal() ) // -1 as it's leaving the account
			->create();
		if ( empty( $oBankTransferExplanationTxn ) ) {
			throw new \Exception( 'Failed to explain bank transfer transaction in FreeAgent.' );
		}

		return $oBankTransferExplanationTxn;
	}

	/**
	 * @param BankTransactionExplanation\BankTransactionExplanationVO $oBankTransferExplTxn
	 * @return BankTransactionVO|null
	 */
	protected function getNewLinkedBankTransferTransaction( $oBankTransferExplTxn ) {
		$oLinkedBankTxnExpl = ( new BankTransactionExplanation\RetrieveLinked() )
			->setConnection( $this->getConnection() )
			->setExplanation( $oBankTransferExplTxn )
			->sendRequestWithVoResponse();
		return ( new Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( $oLinkedBankTxnExpl->getBankTransactionId() )
			->sendRequestWithVoResponse();
	}

	/**
	 * @param BillVO $oBill
	 * @param float  $nNewValue
	 * @return BillVO|null
	 * @throws \Exception
	 */
	protected function updateBillWithNewValue( $oBill, $nNewValue ) {
		$oBill = ( new Update() )
			->setConnection( $this->getConnection() )
			->setTotalValue( $nNewValue )
			->setEntityId( $oBill->getId() )
			->update();
		if ( empty( $oBill ) ) {
			throw new \Exception( 'Failed to update Bill with new total value' );
		}
		return ( new \FernleafSystems\ApiWrappers\Freeagent\Entities\Bills\Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( $oBill->getId() )
			->sendRequestWithVoResponse();
	}
}