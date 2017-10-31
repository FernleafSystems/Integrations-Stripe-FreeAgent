<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Invoices;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankAccounts\BankAccountVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankTransactionExplanation\Create;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankTransactionVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BridgeConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;

class ExplainBankTxnWithInvoices {

	use BankTransactionVoConsumer,
		BridgeConsumer,
		ConnectionConsumer,
		StripePayoutConsumer;

	/**
	 * @param InvoicesPartsToReconcileVO[] $aInvoicesToReconcile
	 */
	public function run( $aInvoicesToReconcile ) {

		$oBankTxn = $this->getBankTransactionVo();
		/** @var BankAccountVO $oBankAccount */
		foreach ( $aInvoicesToReconcile as $oInvoiceItem ) {

			$oInvoice = $oInvoiceItem->getFreeagentInvoice();
			$oBalTxn = $oInvoiceItem->getStripeBalanceTransaction();
			$oCharge = $oInvoiceItem->getStripeCharge();

			try {
				$oCreator = ( new Create() )
					->setConnection( $this->getConnection() )
					->setBankTxn( $oBankTxn )
					->setInvoicePaid( $oInvoice )
					->setDatedOn( $oInvoice->getDatedOn() )
					->setValue( (string)( $oBalTxn->amount/100 ) ); // native bank account currency amount

				// e.g. we're explaining a USD invoice using a transaction in GBP bank account
				if ( strcasecmp( $oCharge->currency, $oBalTxn->currency ) != 0 ) { //foreign currency converted by Stripe
					$oCreator->setForeignCurrencyValue( $oCharge->amount );
				}
				$oExplanation = $oCreator->create();
			}
			catch ( \Exception $oE ) {
				continue;
			}
			//Store some meta in Payment / Charge?
		}
	}
}