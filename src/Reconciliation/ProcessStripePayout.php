<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers;
use FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation;
use Stripe\Payout;

/**
 * Class ProcessStripePayout
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation
 */
class ProcessStripePayout {

	use Consumers\BridgeConsumer,
		ConnectionConsumer,
		Consumers\FreeagentConfigVoConsumer;

	/**
	 * @param string $sStripePayoutId
	 * @throws \Exception
	 */
	public function process( $sStripePayoutId ) {

		$oPayout = Payout::retrieve( $sStripePayoutId );
		$sCurrency = strtoupper( $oPayout->currency );
		$oFreeagentConfig = $this->getFreeagentConfigVO();

		$sBankId = $oFreeagentConfig->getBankAccountIdForCurrency( $sCurrency );
		if ( empty( $sBankId ) ) {
			throw new \Exception( sprintf( 'No bank account specified for currency "%s".', $sCurrency ) );
		}

		/** @var Entities\BankAccounts\BankAccountVO $oBankAccount */
		$oBankAccount = ( new Entities\BankAccounts\Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( $sBankId )
			->sendRequestWithVoResponse();
		if ( empty( $oBankAccount ) ) {
			throw new \Exception( sprintf( 'Could not retrieve bank account with ID "%s".', $sBankId ) );
		}

		// Find/Create the Freeagent Bank Transaction
		$oBankTxn = ( new Reconciliation\BankTransactions\FindForStripePayout() )
			->setConnection( $this->getConnection() )
			->setStripePayout( $oPayout )
			->setBankAccountVo( $oBankAccount )
			->find();

		if ( empty( $oBankTxn ) ) {
			if ( $oFreeagentConfig->isAutoCreateBankTransactions() ) {
				$oBankTxn = ( new Reconciliation\BankTransactions\CreateForStripePayout() )
					->setConnection( $this->getConnection() )
					->setStripePayout( $oPayout )
					->setBankAccountVo( $oBankAccount )
					->create();
			}
			if ( empty( $oBankTxn ) ) {
				throw new \Exception( sprintf( 'Bank Transaction does not exist for this Payout "%s".', $oPayout->id ) );
			}
		}

		// 1) Reconcile all the Invoices
		( new Reconciliation\ProcessInvoicesForStripePayout() )
			->setConnection( $this->getConnection() )
			->setStripePayout( $oPayout )
			->setBankTransactionVo( $oBankTxn )
			->setBridge( $this->getBridge() )
			->process();

		// 2) Reconcile the Stripe Fees with a Bill in Freeagent.
		$oStripeContact = ( new Entities\Contacts\Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( $oFreeagentConfig->getStripeContactId() )
			->sendRequestWithVoResponse();

		$oForeignBankAccount = null;
		$nForeignBankAccountId = $oFreeagentConfig->getBankAccountIdForeignCurrencyTransfer();
		if ( !empty( $nForeignBankAccountId ) ) { // we retrieve it even though it may not be needed
			$oForeignBankAccount = ( new Entities\BankAccounts\Retrieve() )
				->setConnection( $this->getConnection() )
				->setEntityId( $nForeignBankAccountId )
				->sendRequestWithVoResponse();
			if ( empty( $oBankTxn ) ) {
				throw new \Exception( sprintf( 'A bank account for foreign currency transfers was
				provided but could not be loaded with ID "%s".', $nForeignBankAccountId ) );
			}
		}

		( new Reconciliation\ProcessBillForStripePayout() )
			->setConnection( $this->getConnection() )
			->setStripePayout( $oPayout )
			->setFreeagentConfigVO( $oFreeagentConfig )
			->setContactVo( $oStripeContact )
			->setBankTransactionVo( $oBankTxn )
			->setForeignCurrencyTransferAccount( $oForeignBankAccount )
			->process();
	}
}