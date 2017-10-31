<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Base;

use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\InvoiceVO;
use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use Stripe\BalanceTransaction;
use Stripe\Charge;

class ItemToReconcile {

	use StdClassAdapter;

	/**
	 * @return InvoiceVO
	 */
	public function getFreeagentInvoice() {
		return $this->getParam( 'external_invoice' );
	}

	/**
	 * @return BalanceTransaction
	 */
	public function getStripeBalanceTransaction() {
		return $this->getParam( 'stripe_balance_txn' );
	}

	/**
	 * @return Charge
	 */
	public function getStripeCharge() {
		return $this->getParam( 'stripe_charge' );
	}

	/**
	 * @param InvoiceVO $oInvoice
	 * @return $this
	 */
	public function setFreeagentInvoice( $oInvoice ) {
		return $this->setParam( 'external_invoice', $oInvoice );
	}

	/**
	 * @param BalanceTransaction $oBalTxn
	 * @return $this
	 */
	public function setStripeBalanceTransaction( $oBalTxn ) {
		return $this->setParam( 'stripe_balance_txn', $oBalTxn );
	}

	/**
	 * @param Charge $oCharge
	 * @return $this
	 */
	public function setStripeCharge( $oCharge ) {
		return $this->setParam( 'stripe_charge', $oCharge );
	}
}