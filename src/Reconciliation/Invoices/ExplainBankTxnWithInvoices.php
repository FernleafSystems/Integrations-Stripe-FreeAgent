<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Invoices;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankAccounts\BankAccountVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankTransactionExplanation\Create;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Company\Retrieve;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\MarkAs;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\Update;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankTransactionVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BridgeConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Lookups\CurrencyExchangeRates;

class ExplainBankTxnWithInvoices {

	use BankTransactionVoConsumer,
		BridgeConsumer,
		ConnectionConsumer,
		StripePayoutConsumer;

	/**
	 * @param InvoicesPartsToReconcileVO[] $aInvoicesToReconcile
	 */
	public function run( $aInvoicesToReconcile ) {
		$oConn = $this->getConnection();

		$sBaseCurrency = $this->getBaseCurrency();
		$sPayoutDatedOn = date( 'YYYY-MM-DD', $this->getStripePayout()->arrival_date );
		$oCurrencyEx = new CurrencyExchangeRates();

		$oBankTxn = $this->getBankTransactionVo();
		/** @var BankAccountVO $oBankAccount */
		foreach ( $aInvoicesToReconcile as $oInvoiceItem ) {

			$oInvoice = $oInvoiceItem->getFreeagentInvoice();
			$oBalTxn = $oInvoiceItem->getStripeBalanceTransaction();
			$oCharge = $oInvoiceItem->getStripeCharge();

			if ( (int)$oInvoice->getValueDue() == 0 ) {
				continue;
			}

			try {
				$oCreator = ( new Create() )
					->setConnection( $oConn )
					->setBankTxn( $oBankTxn )
					->setInvoicePaid( $oInvoice )
					->setDatedOn( $sPayoutDatedOn )
					->setValue( (string)( $oBalTxn->amount/100 ) ); // native bank account currency amount

				// e.g. we're explaining a USD invoice using a transaction in GBP bank account
				if ( strcasecmp( $oCharge->currency, $oBalTxn->currency ) != 0 ) { //foreign currency converted by Stripe
					$oCreator->setForeignCurrencyValue( $oCharge->amount/100 );
				}
				else {
					// We do some optimisation with unrealised currency gains/losses.
					try {
						$nInvoiceDateRate = $oCurrencyEx->lookup( $sBaseCurrency, $oCharge->currency, $oInvoice->getDatedOn() );
						$nPayoutDateRate = $oCurrencyEx->lookup( $sBaseCurrency, $oCharge->currency, $sPayoutDatedOn );

						// if the target currency got stronger we'd have unrealised gains, so we negate
						// them by changing the invoice creation date to be when we received the payout.
						if ( $nInvoiceDateRate > $nPayoutDateRate ) {
							( new MarkAs() )
								->setConnection( $oConn )
								->setEntityId( $oInvoice->getId() )
								->draft();
							sleep( 1 );
							$oInvoice = ( new Update() )
								->setConnection( $oConn )
								->setEntityId( $oInvoice->getId() )
								->setDatedOn( $sPayoutDatedOn )
								->update();
							sleep( 1 );
							( new MarkAs() )
								->setConnection( $oConn )
								->setEntityId( $oInvoice->getId() )
								->sent();
						}
					}
					catch ( \Exception $oE ) {
					}
				}

				$oCreator->create();
			}
			catch ( \Exception $oE ) {
				continue;
			}
			//Store some meta in Payment / Charge?
		}
	}

	/**
	 * @return string
	 */
	protected function getBaseCurrency() {
		return ( new Retrieve() )
			->setConnection( $this->getConnection() )
			->retrieve()
			->getCurrency();
	}
}