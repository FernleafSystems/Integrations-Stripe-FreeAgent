<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Invoices;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\Find;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\InvoiceVO;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Lookups\GetStripeBalanceTransactionsFromPayout;
use FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\BridgeInterface;
use Stripe\Charge;

class InvoicesVerify {

	use ConnectionConsumer,
		StripePayoutConsumer;

	/**
	 * @var InvoiceVO[]
	 */
	private $aFreeagentInvoices;

	/**
	 * @var BridgeInterface
	 */
	private $oBridge;

	/**
	 * Will return a collection of all invoices to be reconciled, or null if there
	 * was a problem during the verification process.
	 * @return InvoicesPartsToReconcileVO[]
	 * @throws \Exception
	 */
	public function run() {

		$oBridge = $this->getBridge();

		$aFreeagentInvoicesPool = $this->getFreeagentInvoicesPool();

		// Verify FreeAgent Invoice exists for each Stripe Balance Transaction
		// that is represented in the Payout.
		$nTxnCount = 0;
		$aInvoicesToReconcile = array();
		foreach ( $this->getStripeBalanceTxns() as $oBalTxn ) {

			$oInvoiceToReconcile = null;

			// We first check that we can build the link reliably between this ($oBalTxn)
			// Stripe Balance Transaction, and the internal Payment (which links us to Freeagent)
			$bValidLink = $oBridge->verifyStripeToInternalPaymentLink( $oBalTxn );
			if ( !$bValidLink ) {
				continue;
			}

			$nFreeagentInvoiceId = $oBridge->getFreeagentInvoiceIdFromStripeBalanceTxn( $oBalTxn );
			if ( !empty( $nFreeagentInvoiceId ) ) {
				// Verify we've been able to load it.
				foreach ( $aFreeagentInvoicesPool as $oInvoice ) {
					if ( $nFreeagentInvoiceId == $oInvoice->getId() ) {
						$oInvoiceToReconcile = $oInvoice;
						break;
					}
				}
			}

			if ( is_null( $oInvoiceToReconcile ) ) { // No Invoice, so we create it.
				$oNewInvoice = $oBridge->createFreeagentInvoice( $oBalTxn );
				if ( !empty( $oNewInvoice ) ) {
					$oInvoiceToReconcile = $oNewInvoice;
				}
			}

			if ( !is_null( $oInvoiceToReconcile ) ) {
				$aInvoicesToReconcile[] = ( new InvoicesPartsToReconcileVO() )
					->setFreeagentInvoice( $oInvoiceToReconcile )
					->setStripeBalanceTransaction( $oBalTxn )
					->setStripeCharge( Charge::retrieve( $oBalTxn->source ) );
			}

			$nTxnCount++;
		}

		if ( count( $aInvoicesToReconcile ) != $nTxnCount ) {
			throw new \Exception( 'The number of invoices to reconcile does not equal the Stripe TXN count.' );
		}

		return $aInvoicesToReconcile;
	}

	/**
	 * @return BridgeInterface
	 */
	public function getBridge() {
		return $this->oBridge;
	}

	/**
	 * These are the collection of invoices which we'll use to find the
	 * corresponding invoice to Stripe Transaction
	 * @return InvoiceVO[]
	 */
	protected function getFreeagentInvoicesPool() {
		if ( !isset( $this->aFreeagentInvoices ) ) {
			$this->aFreeagentInvoices = ( new Find() )
				->setConnection( $this->getConnection() )
				->filterByOpenOverdue()
				->filterByLastXMonths( 1 )
				->all();
		}
		return $this->aFreeagentInvoices;
	}

	/**
	 * @return \Stripe\BalanceTransaction[]
	 */
	protected function getStripeBalanceTxns() {
		return ( new GetStripeBalanceTransactionsFromPayout() )
			->setStripePayout( $this->getStripePayout() )
			->setTransactionType( 'charge' )
			->retrieve();
	}

	/**
	 * @param BridgeInterface $oBridge
	 * @return $this
	 */
	public function setBridge( $oBridge ) {
		$this->oBridge = $oBridge;
		return $this;
	}
}