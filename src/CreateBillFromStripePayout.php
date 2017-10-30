<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Bills\BillVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Bills\Create;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\ContactVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\Retrieve;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\ContactVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;
use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class CreateBillFromStripePayout
 * @property int stripe_bill_id
 * @property int stripe_contact_id
 * @package FernleafSystems\Integrations\Stripe_Freeagent
 */
class CreateBillFromStripePayout {

	use ConnectionConsumer,
		ContactVoConsumer,
		StripePayoutConsumer,
		StdClassAdapter;

	/**
	 * @return int
	 */
	public function getStripeBillCategoryId() {
		return $this->getNumericParam( 'stripe_bill_id' );
	}

	/**
	 * @return int
	 */
	public function getStripeContactId() {
		return $this->getNumericParam( 'stripe_contact_id' );
	}

	/**
	 * @return BillVO|null
	 * @throws \Exception
	 */
	public function createBill() {

		// Check to ensure the Stripe contact can be found
		if ( !( $this->getContactVo() instanceof ContactVO ) ) {
			$this->setContactVo( $this->verifyStripeContactExists() );
		}

		$oBill = ( new FindBillForStripePayout() )
			->setConnection( $this->getConnection() )
			->setStripePayout( $this->getStripePayout() )
			->setContactVo( $this->getContactVo() )
			->find();
		if ( empty( $oBill ) ) {
			$oBill = $this->create();
		}
		return $oBill;
	}

	/**
	 * Also store Payout meta data: ext_bill_id to reference the FreeAgent Bill ID (saves us searching
	 * for it later).
	 * @return BillVO|null
	 * @throws \Exception
	 */
	protected function create() {
		$oPayout = $this->getStripePayout();

		$sCurrency = strtoupper( $oPayout->currency );
		$aComments = array(
			sprintf( 'Bill for Stripe Payout: https://dashboard.stripe.com/payouts/%s', $oPayout->id ),
			sprintf( 'Total Charges Count: %s', $oPayout->summary->charge_count ),
			sprintf( 'Gross Amount: %s %s', $sCurrency, round( $oPayout->summary->charge_gross/100, 2 ) ),
			sprintf( 'Fees Total: %s %s', $sCurrency, round( $oPayout->summary->charge_fees/100, 2 ) ),
			sprintf( 'Net Amount: %s %s', $sCurrency, round( $oPayout->amount/100, 2 ) )
		);

		$oBill = ( new Create() )
			->setConnection( $this->getConnection() )
			->setContact( $this->getContactVo() )
			->setReference( $oPayout->id )
			->setDatedOn( $oPayout->arrival_date )
			->setDueOn( $oPayout->arrival_date )
			->setCategoryId( $this->getStripeBillCategoryId() )
			->setComment( implode( "\n", $aComments ) )
			->setTotalValue( $oPayout->summary->charge_fees/100 )
			->setSalesTaxRate( 0 )
			->setEcStatus( 'EC Services' )
			->create();

		if ( empty( $oBill ) || empty( $oBill->getId() ) ) {
			throw new \Exception( sprintf( 'Failed to create FreeAgent bill for Stripe Payout ID %s / ', $oPayout->id ) );
		}

		$oPayout->metadata[ 'ext_bill_id' ] = $oBill->getId();
		$oPayout->save();

		return $oBill;
	}

	/**
	 * @param int $nStripeBillCategoryId
	 * @return $this
	 */
	public function setStripeBillCategoryId( $nStripeBillCategoryId ) {
		return $this->setParam( 'stripe_bill_id', $nStripeBillCategoryId );
	}

	/**
	 * @param int $nStripeBillCategoryId
	 * @return $this
	 */
	public function setStripeContactId( $nStripeBillCategoryId ) {
		return $this->setParam( 'stripe_contact_id', $nStripeBillCategoryId );
	}

	/**
	 * @return ContactVO|null
	 * @throws \Exception
	 */
	protected function verifyStripeContactExists() {
		$oContact = ( new Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( $this->getStripeContactId() )
			->sendRequestWithVoResponse();
		if ( empty( $oContact ) ) {
			throw new \Exception( sprintf( 'Failed to find Stripe contact in FreeAgent: "%s"', $this->getStripeContactId() ) );
		}
		if ( !preg_match( '#stripe#i', $oContact->getOrganisationName() ) ) {
			throw new \Exception( 'The contact found in FreeAgent does not appear to be a Stripe contact' );
		}
		return $oContact;
	}
}