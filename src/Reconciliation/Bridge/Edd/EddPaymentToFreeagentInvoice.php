<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\Edd;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\ContactVoConsumer;

/**
 * Class EddPaymentToFreeagentInvoice
 * @package FernleafSystems\Wordpress\Plugin\Edd\Freeagent\Adaptation
 */
class EddPaymentToFreeagentInvoice {

	use ConnectionConsumer,
		ContactVoConsumer;
	const KEY_FREEAGENT_INVOICE_ID = 'freeagent_invoice_id';

	/**
	 * @var \EDD_Payment
	 */
	private $oPayment;

	/**
	 * @return int
	 */
	protected function getLinkedFreeagentInvoiceId() {
		return $this->getPayment()
					->get_meta( self::KEY_FREEAGENT_INVOICE_ID );
	}

	/**
	 * @return Entities\Invoices\InvoiceVO|null
	 */
	public function createInvoice() {

		$oContact = $this->getContactVo();
		$oPayment = $this->getPayment();

		$oCreateInvoice = ( new Entities\Invoices\Create() )
			->setConnection( $this->getConnection() )
			->setContact( $oContact )
			->setDatedOn( strtotime( $oPayment->completed_date ) )
			->setExchangeRate( 1.0 )// TODO: get the balance transaction from Stripe to be sure.
			->setPaymentTerms( 14 )
			->setCurrency( $oPayment->currency )
			->setComments(
				serialize(
					array(
						'payment_id'        => $oPayment->ID,
						'stripe_charge_ids' => $this->getTransactionIdsFromPayment()
					)
				)
			)
			->addInvoiceItemVOs( $this->buildLineItemsFromCart() );

		if ( $this->isPaymentEuVatMossRegion() ) {
			$oCreateInvoice->setEcPlaceOfSupply( $oContact->getCountry() )
						   ->setEcStatusVatMoss();
		}
		else {
			$oCreateInvoice->setEcStatusNonEc();
		}

		$oInvoice = $oCreateInvoice->create();
		$oPayment->update_meta( self::KEY_FREEAGENT_INVOICE_ID, $oInvoice->getId() );
		sleep( 2 );
		return $this->markInvoiceAsSent( $oInvoice );
	}

	/**
	 * @param Entities\Invoices\InvoiceVO $oInvoice
	 * @return Entities\Invoices\InvoiceVO
	 */
	protected function markInvoiceAsSent( $oInvoice ) {
		( new Entities\Invoices\MarkAs() )
			->setConnection( $this->getConnection() )
			->setEntityId( $oInvoice->getId() )
			->sent();
		return ( new Entities\Invoices\Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( $oInvoice->getId() )
			->sendRequestWithVoResponse();
	}

	/**
	 * @return string[]
	 */
	protected function getTransactionIdsFromPayment() {
		/** @var \EDD_Subscription[] $aSubscriptions */
		$aSubscriptions = ( new \EDD_Subscriptions_DB() )
			->get_subscriptions( array( 'parent_payment_id' => $this->getPayment()->ID ) );

		return array_map(
			function ( $oSub ) {
				/** @var \EDD_Subscription $oSub */
				return $oSub->get_transaction_id();
			},
			$aSubscriptions
		);
	}

	/**
	 * @return Entities\Invoices\Items\InvoiceItemVO[]
	 */
	protected function buildLineItemsFromCart() {
		$aInvoiceItems = array();

		// Add purchased items to invoice
		foreach ( edd_get_payment_meta_cart_details( $this->getPayment()->ID ) as $aLineItem ) {

			$aInvoiceItems[] = ( new Entities\Invoices\Items\InvoiceItemVO() )
				->setDescription( $aLineItem[ 'name' ] )
				->setQuantity( $aLineItem[ 'quantity' ] )
				->setPrice( $aLineItem[ 'subtotal' ] )
				->setSalesTaxRate( $this->getPayment()->tax_rate )
				->setType( 'Years' ); //TODO: Hard coded, need to adapt to purchase

		}
		return $aInvoiceItems;
	}

	/**
	 * @return \EDD_Payment
	 */
	public function getPayment() {
		return $this->oPayment;
	}

	/**
	 * @return array
	 */
	protected function getTaxCountriesRates() {
		$aCountriesToRates = array();
		foreach ( edd_get_tax_rates() as $aCountryRate ) {
			if ( !empty( $aCountryRate[ 'country' ] ) ) {
				$aCountriesToRates[ $aCountryRate[ 'country' ] ] = $aCountryRate[ 'rate' ];
			}
		}
		return $aCountriesToRates;
	}

	/**
	 * @return bool
	 */
	protected function isPaymentEuVatMossRegion() {
		$sPaymentCountry = $this->getPayment()->address[ 'country' ];
		return ( $sPaymentCountry != 'GB' &&
				 array_key_exists( $sPaymentCountry, $this->getTaxCountriesRates() ) );
	}

	/**
	 * @param \EDD_Payment $oPayment
	 * @return $this
	 */
	public function setPayment( $oPayment ) {
		$this->oPayment = $oPayment;
		return $this;
	}
}