<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\Edd;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\ContactVoConsumer;
use FernleafSystems\WordPress\Integrations\Edd\Utilities\Entities\CartItemVo;
use FernleafSystems\WordPress\Integrations\Edd\Utilities\GetTransactionIdFromCartItem;

/**
 * Class EddPaymentToFreeagentInvoice
 * @package FernleafSystems\Wordpress\Plugin\Edd\Freeagent\Adaptation
 */
class EddPaymentToFreeagentInvoice {

	use ConnectionConsumer,
		ContactVoConsumer;

	/**
	 * @var \EDD_Payment
	 */
	private $oPayment;

	/**
	 * @param CartItemVo $oCartItem
	 * @return Entities\Invoices\InvoiceVO|null
	 */
	public function createInvoiceForItem( $oCartItem ) {

		$oContact = $this->getContactVo();
		$oPayment = $this->getPayment();

		$nDatedOn = empty( $oPayment->date ) ? time() : strtotime( $oPayment->date );

		$oCreateInvoice = ( new Entities\Invoices\Create() )
			->setConnection( $this->getConnection() )
			->setContact( $oContact )
			->setDatedOn( $nDatedOn )
			->setExchangeRate( 1.0 )// TODO: Verify this perhaps with Stripe Txn
			->setPaymentTerms( 14 )
			->setCurrency( $oPayment->currency )
			->setComments(
				serialize(
					array(
						'payment_id'       => $oPayment->ID,
						'stripe_charge_id' => ( new GetTransactionIdFromCartItem() )->retrieve( $oCartItem )
					)
				)
			)
			->addInvoiceItemVOs( $this->buildLineItemsFromCartItem( $oCartItem ) );

		if ( $this->isPaymentEuVatMossRegion() ) {
			$oCreateInvoice->setEcPlaceOfSupply( $oContact->getCountry() )
						   ->setEcStatusVatMoss();
		}
		else {
			$oCreateInvoice->setEcStatusNonEc();
		}

		$oInvoice = $oCreateInvoice->create();

		if ( !is_null( $oInvoice ) ) {
			sleep( 2 );
			$oInvoice = $this->markInvoiceAsSent( $oInvoice );
		}
		return $oInvoice;
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
	 * @param CartItemVo $oCartItem
	 * @return Entities\Invoices\Items\InvoiceItemVO[]
	 */
	protected function buildLineItemsFromCartItem( $oCartItem ) {
		$aInvoiceItems = array();

		$aInvoiceItems[] = ( new Entities\Invoices\Items\InvoiceItemVO() )
			->setDescription( $oCartItem->getName() )
			->setQuantity( $oCartItem->getQuantity() )
			->setPrice( $oCartItem->getSubtotal() )
			->setSalesTaxRate( $oCartItem->getTaxRate()*100 )
			->setType( 'Years' ); //TODO: Hard coded, need to adapt to purchase

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