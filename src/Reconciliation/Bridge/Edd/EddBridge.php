<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\Edd;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\ContactVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\InvoiceVO;
use FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\BridgeInterface;
use Stripe\BalanceTransaction;

class EddBridge implements BridgeInterface {

	use ConnectionConsumer;

	public function __construct() {
		EDD_Recurring(); // initializes anything that's required
	}

	/**
	 * @param \EDD_Payment $oPayment
	 * @param bool         $bUpdateOnly
	 * @return ContactVO
	 */
	public function createFreeagentContact( $oPayment, $bUpdateOnly = false ) {
		$oContactCreator = ( new EddCustomerToFreeagentContact() )
			->setConnection( $this->getConnection() )
			->setCustomer( $this->getEddCustomerFromEddPayment( $oPayment ) )
			->setPayment( $oPayment );
		return $bUpdateOnly ? $oContactCreator->update() : $oContactCreator->create();
	}

	/**
	 * @param BalanceTransaction $oBalTxn
	 * @return InvoiceVO
	 */
	public function createFreeagentInvoiceFromStripeBalanceTxn( $oBalTxn ) {
		return $this->createFreeagentInvoiceFromEddPayment(
			$this->getEddPaymentFromStripeBalanceTxn( $oBalTxn )
		);
	}

	/**
	 * @param \EDD_Payment $oPayment
	 * @return InvoiceVO
	 */
	public function createFreeagentInvoiceFromEddPayment( $oPayment ) {
		$nContactId = $this->getFreeagentContactIdFromEddPayment( $oPayment );
		$oContact = $this->createFreeagentContact( $oPayment, !empty( $nContactId ) );

		return ( new EddPaymentToFreeagentInvoice() )
			->setConnection( $this->getConnection() )
			->setContactVo( $oContact )
			->setPayment( $oPayment )
			->createInvoice();
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return \EDD_Payment
	 */
	public function getEddPaymentFromStripeBalanceTxn( $oStripeTxn ) {
		/** @var \EDD_Subscription[] $aSubscriptions */
		$aSubscriptions = $this->getInternalSubscriptionsForStripeTxn( $oStripeTxn );
		if ( empty( $aSubscriptions ) ) {
			return null;
		}
		return new \EDD_Payment( $aSubscriptions[ 0 ]->get_original_payment_id() );
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return array
	 */
	protected function getInternalSubscriptionsForStripeTxn( $oStripeTxn ) {
		return ( new \EDD_Subscriptions_DB() )
			->get_subscriptions( array( 'transaction_id' => $oStripeTxn->source ) );
	}

	/**
	 * @param \EDD_Payment $oEddPayment
	 * @return \EDD_Customer
	 */
	public function getEddCustomerFromEddPayment( $oEddPayment ) {
		return new \EDD_Customer( $oEddPayment->customer_id );
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return \EDD_Customer
	 */
	public function getEddCustomerFromStripeTxn( $oStripeTxn ) {
		return $this->getEddCustomerFromEddPayment(
			$this->getEddPaymentFromStripeBalanceTxn( $oStripeTxn )
		);
	}

	/**
	 * @param \EDD_Payment $oEddPayment
	 * @return int
	 */
	public function getFreeagentContactIdFromEddPayment( $oEddPayment ) {
		return $this->getEddCustomerFromEddPayment( $oEddPayment )
					->get_meta( 'freeagent_contact_id' );
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return int
	 */
	public function getFreeagentContactIdFromStripeBalTxn( $oStripeTxn ) {
		return $this->getFreeagentContactIdFromEddPayment(
			$this->getEddPaymentFromStripeBalanceTxn( $oStripeTxn )
		);
	}

	/**
	 * @param \EDD_Payment $oEddPayment
	 * @return int
	 */
	public function getFreeagentInvoiceIdFromEddPayment( $oEddPayment ) {
		return $oEddPayment->get_meta( 'freeagent_invoice_id' );
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return int
	 */
	public function getFreeagentInvoiceIdFromStripeBalanceTxn( $oStripeTxn ) {
		return $this->getFreeagentInvoiceIdFromEddPayment(
			$this->getEddPaymentFromStripeBalanceTxn( $oStripeTxn )
		);
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return bool
	 */
	public function verifyStripeToInternalPaymentLink( $oStripeTxn ) {
		$aSub = $this->getInternalSubscriptionsForStripeTxn( $oStripeTxn );
		return !empty( $aSub );
	}
}