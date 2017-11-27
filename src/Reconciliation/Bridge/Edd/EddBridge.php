<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\Edd;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\ContactVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\InvoiceVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Invoices\Retrieve;
use FernleafSystems\Integrations\Edd\Entities\CartItemVo;
use FernleafSystems\Integrations\Edd\Utilities\GetCartItemsFromTransactionId;
use FernleafSystems\Integrations\Edd\Utilities\GetEddPaymentFromTransactionId;
use FernleafSystems\Integrations\Edd\Utilities\GetTransactionIdFromCartItem;
use FernleafSystems\Integrations\Edd\Utilities\GetTransactionIdsFromPayment;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\FreeagentConfigVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\BridgeInterface;
use Stripe\BalanceTransaction;

class EddBridge implements BridgeInterface {

	use ConnectionConsumer,
		FreeagentConfigVoConsumer;

	const KEY_FREEAGENT_INVOICE_IDS = 'freeagent_invoice_ids';

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
	 * @param string $sChargeTxnId
	 * @return InvoiceVO
	 */
	public function createFreeagentInvoiceFromStripeBalanceTxn( $sChargeTxnId ) {
		return $this->createFreeagentInvoiceFromEddPaymentCartItem(
			$this->getCartItemDetailsFromStripeBalanceTxn( $sChargeTxnId )
		);
	}

	/**
	 * @param string $sStripeChargeTxnId
	 * @return CartItemVo
	 * @throws \Exception
	 */
	protected function getCartItemDetailsFromStripeBalanceTxn( $sStripeChargeTxnId ) {
		$aCartItems = ( new GetCartItemsFromTransactionId() )->retrieve( $sStripeChargeTxnId );
		if ( count( $aCartItems ) != 1 ) { // TODO - if we offer non-subscription items!
			throw new \Exception( sprintf( 'Found more than 1 cart item for a Stripe Txn "%s"', $sStripeChargeTxnId ) );
		}
		return array_pop( $aCartItems );
	}

	/**
	 * First attempts to locate a previously created invoice for this Payment.
	 * @param CartItemVo $oCartItem
	 * @return InvoiceVO
	 */
	public function createFreeagentInvoiceFromEddPaymentCartItem( $oCartItem ) {
		$oInvoice = null;

		$oEddPayment = new \EDD_Payment( $oCartItem->getParentPaymentId() );

		// 1st: Create/update the FreeAgent Contact.
		$nContactId = $this->getFreeagentContactIdFromEddPayment( $oEddPayment );
		$oContact = $this->createFreeagentContact( $oEddPayment, !empty( $nContactId ) );

		// 2nd: Retrieve/Create FreeAgent Invoice
		$sTxnId = ( new GetTransactionIdFromCartItem() )->retrieve( $oCartItem );
		$aInvoiceIds = $this->getFreeagentInvoiceIdsFromEddPayment( $oEddPayment );

		$nInvoiceId = isset( $aInvoiceIds[ $sTxnId ] ) ? $aInvoiceIds[ $sTxnId ] : null;
		if ( !empty( $nInvoiceId ) ) {
			$oInvoice = ( new Retrieve() )
				->setConnection( $this->getConnection() )
				->setEntityId( $nInvoiceId )
				->retrieve();
		}

		if ( empty( $oInvoice ) ) {
			$oInvoice = ( new EddPaymentItemToFreeagentInvoice() )
				->setFreeagentConfigVO( $this->getFreeagentConfigVO() )
				->setConnection( $this->getConnection() )
				->setContactVo( $oContact )
				->setPayment( $oEddPayment )
				->createInvoice( $oCartItem );

			if ( !is_null( $oInvoice ) ) {
				$aInvoiceIds[ $sTxnId ] = $oInvoice->getId();
				$oEddPayment->update_meta( self::KEY_FREEAGENT_INVOICE_IDS, $aInvoiceIds );
			}
		}
		return $oInvoice;
	}

	/**
	 * First attempts to locate a previously created invoice for this Payment.
	 * @param \EDD_Payment $oPayment
	 * @return InvoiceVO[]
	 */
	public function createFreeagentInvoicesFromEddPayment( $oPayment ) {
		return array_filter( array_map(
			function ( $sTxnId ) { /** @var string $sTxnId */
				return $this->createFreeagentInvoiceFromStripeBalanceTxn( $sTxnId );
			},
			( new GetTransactionIdsFromPayment() )->retrieve( $oPayment )
		) );
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return \EDD_Subscription[]
	 */
	protected function getInternalSubscriptionsForStripeTxn( $oStripeTxn ) {
		return ( new \EDD_Subscriptions_DB() )
			->get_subscriptions( array( 'transaction_id' => $oStripeTxn->source ) );
	}

	/**
	 * @param \EDD_Payment $oEddPayment
	 * @return \EDD_Customer
	 */
	private function getEddCustomerFromEddPayment( $oEddPayment ) {
		return new \EDD_Customer( $oEddPayment->customer_id );
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return \EDD_Payment|null
	 */
	private function getEddPaymentFromStripeBalanceTxn( $oStripeTxn ) {
		return ( new GetEddPaymentFromTransactionId() )->retrieve( $oStripeTxn->source );
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
	 * @return array
	 */
	public function getFreeagentInvoiceIdsFromEddPayment( $oEddPayment ) {
		$aIds = $oEddPayment->get_meta( self::KEY_FREEAGENT_INVOICE_IDS );
		return is_array( $aIds ) ? $aIds : array();
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return int
	 */
	public function getFreeagentInvoiceIdFromStripeBalanceTxn( $oStripeTxn ) {
		$aIds = $this->getFreeagentInvoiceIdsFromEddPayment(
			$this->getEddPaymentFromStripeBalanceTxn( $oStripeTxn )
		);
		return isset( $aIds[ $oStripeTxn->source ] ) ? $aIds[ $oStripeTxn->source ] : null;
	}

	/**
	 * @param BalanceTransaction $oStripeTxn
	 * @return bool
	 */
	public function verifyStripeToInternalPaymentLink( $oStripeTxn ) {
		return !is_null( $this->getEddPaymentFromStripeBalanceTxn( $oStripeTxn ) );
	}
}