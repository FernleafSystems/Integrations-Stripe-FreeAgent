<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankTransactionVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BridgeConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\FreeagentConfigVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Invoices\ExplainBankTxnWithInvoices;
use FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Invoices\InvoicesVerify;

/**
 * Verifies all invoices associated with the payout are present and accurate within Freeagent
 * Then reconciles all local invoices/Stripe Charges with the exported invoices within Freeagent
 * Class StripeChargesWithFreeagentTransaction
 * @package iControlWP\Integration\FreeAgent\Reconciliation
 */
class ProcessInvoicesForStripePayout {

	use BankTransactionVoConsumer,
		BridgeConsumer,
		FreeagentConfigVoConsumer,
		ConnectionConsumer,
		StripePayoutConsumer;

	/**
	 * @throws \Exception
	 */
	public function process() {

		$aReconInvoiceData = ( new InvoicesVerify() )
			->setConnection( $this->getConnection() )
			->setStripePayout( $this->getStripePayout() )
			->setBridge( $this->getBridge() )
			->run();

		( new ExplainBankTxnWithInvoices() )
			->setConnection( $this->getConnection() )
			->setStripePayout( $this->getStripePayout() )
			->setBridge( $this->getBridge() )
			->setBankTransactionVo( $this->getBankTransactionVo() )
			->run( $aReconInvoiceData );
	}
}