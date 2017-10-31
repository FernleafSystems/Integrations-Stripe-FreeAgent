<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\Edd;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\ContactVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\InvoiceVO;
use Stripe\BalanceTransaction;

class EddBridge implements BridgeInterface {

	use ConnectionConsumer;

	/**
	 * @param BalanceTransaction $oBalTxn
	 * @param bool $bUpdateOnly
	 * @return ContactVO
	 */
	public function createFreeagentContact( $oBalTxn, $bUpdateOnly = false ) {
		$oContactCreator = ( new EddCustomerToFreeagentContact() )
			->setConnection( $this->getConnection() )
			->setCustomer( $this->getEddCustomerFromStripeTxn( $oBalTxn ) )
			->setPayment( $this->getEddPaymentFromStripeBalanceTxn( $oBalTxn ) );
		return $bUpdateOnly ? $oContactCreator->update() : $oContactCreator->create();
	}

	/**
	 * @param $oBalTxn
	 * @return InvoiceVO
	 */
	public function createFreeagentInvoice( $oBalTxn ) {
		$nContactId = $this->getFreeagentContactIdFromStripeBalTxn( $oBalTxn );
		$oContact = $this->createFreeagentContact( $oBalTxn, !empty( $nContactId ) );

		return ( new EddPaymentToFreeagentInvoice() )
			->setConnection( $this->getConnection() )
			->setContactVo( $oContact )
			->setPayment( $this->getEddPaymentFromStripeBalanceTxn( $oBalTxn ) )
			->createInvoice();
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return \EDD_Payment
	 */
	public function getEddPaymentFromStripeBalanceTxn( $oStripeTxn ) {
		/** @var \EDD_Subscription[] $aSubscriptions */
		$aSubscriptions = ( new \EDD_Subscriptions_DB() )
			->get_subscriptions( array( 'transaction_id' => $oStripeTxn->source ) );
		return new \EDD_Payment( $aSubscriptions[ 0 ]->get_original_payment_id() );
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
			$this->getEddPaymentFromStripeBalanceTxn( $oStripeTxn ) );
	}
}