<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankAccounts\BankAccountVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankTransactions\Retrieve;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankAccountVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankTransactionVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BridgeConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\ContactVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills\CreateForStripePayout;
use FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills\ExplainBankTxnWithStripeBill;

/**
 * Class ProcessBillForStripePayout
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation
 */
class ProcessBillForStripePayout {

	use BankAccountVoConsumer,
		BankTransactionVoConsumer,
		BridgeConsumer,
		ContactVoConsumer,
		ConnectionConsumer,
		StripePayoutConsumer;

	/**
	 * @throws \Exception
	 */
	public function process() {

		$this->refreshBankTxn(); // We do this to ensure we have the latest working BankTxn;

		$oBill = ( new CreateForStripePayout() )
			->setConnection( $this->getConnection() )
			->setStripePayout( $this->getStripePayout() )
			->setContactVo( $this->getContactVo() )
			->createBill();

		( new ExplainBankTxnWithStripeBill() )
			->setConnection( $this->getConnection() )
			->setBankAccountVo( $this->getForeignCurrencyTransferAccount() )
			->setStripePayout( $this->getStripePayout() )
			->setBankTransactionVo( $this->getBankTransactionVo() )
			->process( $oBill );
	}

	/**
	 * @return BankAccountVO|null
	 */
	public function getForeignCurrencyTransferAccount() {
		return $this->getBankAccountVo();
	}

	/**
	 * @param BankAccountVO $oVo
	 * @return $this
	 */
	public function setForeignCurrencyTransferAccount( $oVo ) {
		return $this->setBankAccountVo( $oVo );
	}

	protected function refreshBankTxn() {
		return $this->setBankTransactionVo(
			( new Retrieve() )
				->setConnection( $this->getConnection() )
				->setEntityId( $this->getBankTransactionVo()->getId() )
				->sendRequestWithVoResponse()
		);
	}
}