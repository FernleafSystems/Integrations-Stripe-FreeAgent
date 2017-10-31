<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\Edd;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities;

/**
 * Class EddCustomerToFreeagentContact
 * @package FernleafSystems\Wordpress\Plugin\Edd\Freeagent\Adaptation
 */
class EddCustomerToFreeagentContact {

	use ConnectionConsumer;
	const KEY_FREEAGENT_CONTACT_ID = 'freeagent_contact_id';
	/**
	 * @var \EDD_Customer
	 */
	private $oCustomer;
	/**
	 * @var \EDD_Payment
	 */
	private $oPayment;
	/**
	 * @var Entities\Contacts\ContactVO
	 */
	private $oContact;
	/**
	 * @var string
	 */
	private $sMetaKeyPrefix;

	/**
	 * @return Entities\Contacts\ContactVO
	 */
	public function create() {

		// If there is no link between Customer and Contact, create it.
		$nFreeagentContactId = $this->getCustomer()
									->get_meta( $this->getFreeagentContactIdMetaKey() );
		if ( empty( $nFreeagentContactId ) ) {
			$this->createNewFreeagentContact();
		}

		return $this->update();
	}

	/**
	 * @return Entities\Contacts\ContactVO
	 */
	public function update() {
		// Now update the Contact with any business information from the Payment.
		return $this->updateContactUsingPaymentInfo()
					->getContact();
	}

	/**
	 * @return string
	 */
	public function createNewFreeagentContact() {
		$oCustomer = $this->getCustomer();
		$aNames = explode( ' ', $oCustomer->name, 2 );
		if ( !isset( $aNames[ 1 ] ) ) {
			$aNames[ 1 ] = '';
		}

		$oContact = ( new Entities\Contacts\Create() )
			->setConnection( $this->getConnection() )
			->setFirstName( $aNames[ 0 ] )
			->setLastName( $aNames[ 1 ] )
			->sendRequestWithVoResponse();

		$oCustomer->update_meta( $this->getFreeagentContactIdMetaKey(), $oContact->getId() );

		return $oContact->getId();
	}

	/**
	 * @return $this
	 */
	protected function updateContactUsingPaymentInfo() {
		$oPayment = $this->getPayment();

		// Freeagent uses full country names; EDD uses ISO2 codes
		$sPaymentCountry = $this->getCountryNameFromIso2Code( $oPayment->address[ 'country' ] );
		$aUserInfo = edd_get_payment_meta_user_info( $oPayment->ID );

		$oContact = ( new Entities\Contacts\Update() )
			->setConnection( $this->getConnection() )
			->setEntityId( $this->getContact()->getId() )
			->setAddress_Line( $aUserInfo[ 'line1' ], 1 )
			->setAddress_Line( $aUserInfo[ 'line2' ], 2 )
			->setAddress_Town( $aUserInfo[ 'city' ] )
			->setAddress_Region( $aUserInfo[ 'state' ] )
			->setAddress_PostalCode( $aUserInfo[ 'zip' ] )
			->setAddress_Country( $sPaymentCountry )
			->setSalesTaxNumber( $aUserInfo[ 'vat_number' ] )
			->setOrganisationName( $aUserInfo[ 'company' ] )
			->update();

		return $this->setContact( $oContact );
	}

	/**
	 * @param string $sCode
	 * @return string
	 */
	protected function getCountryNameFromIso2Code( $sCode ) {
		$aCountries = edd_get_country_list();
		return isset( $aCountries[ $sCode ] ) ? $aCountries[ $sCode ] : $sCode;
	}

	/**
	 * @return Entities\Contacts\ContactVO
	 */
	protected function retrieveFreeagentContact() {
		return ( new Entities\Contacts\Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( $this->getCustomer()->get_meta( $this->getFreeagentContactIdMetaKey() ) )
			->sendRequestWithVoResponse();
	}

	/**
	 * @return Entities\Contacts\ContactVO
	 */
	public function getContact() {
		if ( !isset( $this->oContact ) ) {
			$this->oContact = $this->retrieveFreeagentContact();
		}
		return $this->oContact;
	}

	/**
	 * @return \EDD_Customer
	 */
	public function getCustomer() {
		return $this->oCustomer;
	}

	/**
	 * @return string
	 */
	public function getFreeagentContactIdMetaKey() {
		return $this->getMetaKeyPrefix().self::KEY_FREEAGENT_CONTACT_ID;
	}

	/**
	 * @return string
	 */
	public function getMetaKeyPrefix() {
		return $this->sMetaKeyPrefix;
	}

	/**
	 * @return \EDD_Payment
	 */
	public function getPayment() {
		return $this->oPayment;
	}

	/**
	 * @param Entities\Contacts\ContactVO $oContact
	 * @return $this
	 */
	public function setContact( $oContact ) {
		$this->oContact = $oContact;
		return $this;
	}

	/**
	 * @param \EDD_Customer $oCustomer
	 * @return $this
	 */
	public function setCustomer( $oCustomer ) {
		$this->oCustomer = $oCustomer;
		return $this;
	}

	/**
	 * @param string $sMetaKeyPrefix
	 * @return $this
	 */
	public function setMetaKeyPrefix( $sMetaKeyPrefix ) {
		$this->sMetaKeyPrefix = $sMetaKeyPrefix;
		return $this;
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