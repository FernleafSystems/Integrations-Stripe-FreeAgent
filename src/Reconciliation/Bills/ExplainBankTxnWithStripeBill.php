<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankTransactionVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\FreeagentConfigVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;

/**
 * Retrieve the Stripe Bill within FreeAgent, and the associated Bank Transaction
 * for the Payout and creates a FreeAgent Explanation for it.
 * Class ExplainBankTxnWithStripeBill
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills
 */
class ExplainBankTxnWithStripeBill {

	use BankTransactionVoConsumer,
		FreeagentConfigVoConsumer,
		StripePayoutConsumer,
		ConnectionConsumer;

	/**
	 * Determine whether we're working in our native currency, or whether
	 * we have to explain the bill using our Foreign Bill handling.
	 * @param Entities\Bills\BillVO $oBill
	 * @throws \Exception
	 */
	public function process( $oBill ) {
		if ( $oBill->getAmountDue() > 0 ) {

			if ( strcasecmp( $this->getStripePayout()->currency, $this->getBaseCurrency() ) == 0 ) {
				$this->createSimpleExplanation( $oBill );
			}
			else {
				$oForeignBankAccount = $this->getForeignCurrencyBankAccount();
				if ( is_null( $oForeignBankAccount ) ) {
					throw  new \Exception( 'Attempting to explain a foreign currency bill without a currency transfer account.' );
				}

				( new ExplainBankTxnWithForeignBill() )
					->setStripePayout( $this->getStripePayout() )
					->setConnection( $this->getConnection() )
					->setBankTransactionVo( $this->getBankTransactionVo() )
					->setBankAccountVo( $oForeignBankAccount )
					->createExplanation( $oBill );
			}
		}
	}

	/**
	 * @param Entities\Bills\BillVO $oBill
	 * @throws \Exception
	 */
	public function createSimpleExplanation( $oBill ) {

		$oBankTxnExp = ( new Entities\BankTransactionExplanation\Create() )
			->setConnection( $this->getConnection() )
			->setBankTxn( $this->getBankTransactionVo() )
			->setBillPaid( $oBill )
			->setValue( $oBill->getAmountTotal() )
			->create();

		if ( empty( $oBankTxnExp ) ) {
			throw new \Exception( 'Failed to explain bank transaction with a bill in FreeAgent.' );
		}
	}

	/**
	 * @return string
	 */
	protected function getBaseCurrency() {
		/** @var Entities\Company\CompanyVO $oCompany */
		$oCompany = ( new Entities\Company\Retrieve() )
			->setConnection( $this->getConnection() )
			->sendRequestWithVoResponse();
		return $oCompany->getCurrency();
	}

	/**
	 * @return Entities\BankAccounts\BankAccountVO
	 */
	protected function getForeignCurrencyBankAccount() {
		$oForeignBankAccount = null;

		$nForeignBankAccountId = $this->getFreeagentConfigVO()
									  ->getBankAccountIdForeignCurrencyTransfer();
		if ( !empty( $nForeignBankAccountId ) ) { // we retrieve it even though it may not be needed
			$oForeignBankAccount = ( new Entities\BankAccounts\Retrieve() )
				->setConnection( $this->getConnection() )
				->setEntityId( $nForeignBankAccountId )
				->sendRequestWithVoResponse();
		}
		return $oForeignBankAccount;
	}
}