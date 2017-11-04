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
	 * - verify we can load the bank account
	 * - verify we can load the bank transaction (maybe create it automatically if not)
	 * - reconcile stripe charges with freeagent invoices
	 * - reconcile stripe fees with freeagent bill
	 * @param string $sStripePayoutId
	 * @throws \Exception
	 */
	public function process( $sStripePayoutId ) {

		$oCon = $this->getConnection();
		$oPayout = Payout::retrieve( $sStripePayoutId );
		$oFreeagentConfig = $this->getFreeagentConfigVO();

		$sBankId = $oFreeagentConfig->getBankAccountIdForCurrency( $oPayout->currency );
		if ( empty( $sBankId ) ) {
			throw new \Exception( sprintf( 'No bank account specified for currency "%s".', $oPayout->currency ) );
		}

		/** @var Entities\BankAccounts\BankAccountVO $oBankAccount */
		$oBankAccount = ( new Entities\BankAccounts\Retrieve() )
			->setConnection( $oCon )
			->setEntityId( $sBankId )
			->sendRequestWithVoResponse();
		if ( empty( $oBankAccount ) ) {
			throw new \Exception( sprintf( 'Could not retrieve bank account with ID "%s".', $sBankId ) );
		}

		// Find/Create the Freeagent Bank Transaction
		$oBankTxn = ( new Reconciliation\BankTransactions\FindForStripePayout() )
			->setConnection( $oCon )
			->setStripePayout( $oPayout )
			->setBankAccountVo( $oBankAccount )
			->find();
		if ( empty( $oBankTxn ) ) {
			if ( $oFreeagentConfig->isAutoCreateBankTransactions() ) {
				$oBankTxn = ( new Reconciliation\BankTransactions\CreateForStripePayout() )
					->setConnection( $oCon )
					->setStripePayout( $oPayout )
					->setBankAccountVo( $oBankAccount )
					->create();
			}
		}
		if ( empty( $oBankTxn ) ) {
			throw new \Exception( sprintf( 'Bank Transaction does not exist for this Payout "%s".', $oPayout->id ) );
		}

		// 1) Reconcile all the Invoices
		( new Reconciliation\ProcessInvoicesForStripePayout() )
			->setConnection( $oCon )
			->setStripePayout( $oPayout )
			->setBankTransactionVo( $oBankTxn )
			->setBridge( $this->getBridge() )
			->run();

		// 2) Reconcile the Stripe Bill
		( new Reconciliation\ProcessBillForStripePayout() )
			->setConnection( $oCon )
			->setStripePayout( $oPayout )
			->setFreeagentConfigVO( $oFreeagentConfig )
			->setBankTransactionVo( $oBankTxn )
			->run();
	}
}