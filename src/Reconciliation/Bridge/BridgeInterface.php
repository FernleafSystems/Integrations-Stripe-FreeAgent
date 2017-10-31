<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\Edd;

use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\ContactVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\InvoiceVO;
use Stripe\BalanceTransaction;

interface BridgeInterface {

	/**
	 * @param BalanceTransaction $oBalTxn
	 * @param bool $bUpdateOnly
	 * @return ContactVO
	 */
	public function createFreeagentContact( $oBalTxn, $bUpdateOnly = false );

	/**
	 * @param $oBalTxn
	 * @return InvoiceVO
	 */
	public function createFreeagentInvoice( $oBalTxn );

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return \EDD_Payment
	 */
	public function getEddPaymentFromStripeBalanceTxn( $oStripeTxn );

	/**
	 * @param \EDD_Payment $oEddPayment
	 * @return int
	 */
	public function getFreeagentContactIdFromEddPayment( $oEddPayment );

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return int
	 */
	public function getFreeagentContactIdFromStripeBalTxn( $oStripeTxn );

	/**
	 * @param \EDD_Payment $oEddPayment
	 * @return \EDD_Customer
	 */
	public function getEddCustomerFromEddPayment( $oEddPayment );

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return \EDD_Customer
	 */
	public function getEddCustomerFromStripeTxn( $oStripeTxn );

	/**
	 * @param \EDD_Payment $oEddPayment
	 * @return int
	 */
	public function getFreeagentInvoiceIdFromEddPayment( $oEddPayment );

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return int
	 */
	public function getFreeagentInvoiceIdFromStripeBalanceTxn( $oStripeTxn );
}