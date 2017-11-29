<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge;

use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\ContactVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\InvoiceVO;
use Stripe\BalanceTransaction;

interface BridgeInterface {

	/**
	 * @param BalanceTransaction $oBalTxn
	 * @param bool               $bUpdateOnly
	 * @return ContactVO
	 */
	public function createFreeagentContact( $oBalTxn, $bUpdateOnly = false );

	/**
	 * @param string $sChargeTxnId
	 * @return InvoiceVO
	 */
	public function createFreeagentInvoiceFromStripeBalanceTxn( $sChargeTxnId );

	/**
	 * @param BalanceTransaction $oBalTxn
	 * @return int
	 */
	public function getFreeagentContactIdFromStripeBalTxn( $oBalTxn );

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return int
	 */
	public function getFreeagentInvoiceIdFromStripeBalanceTxn( $oStripeTxn );

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return bool
	 */
	public function verifyStripeToInternalPaymentLink( $oStripeTxn );
}