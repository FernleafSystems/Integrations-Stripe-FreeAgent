<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Bills\BillVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Bills\Create;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\ContactVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\Retrieve;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;

/**
 * Class CreateBillFromStripePayout
 * @package iControlWP\Integration\FreeAgent
 */
class CreateBillFromStripePayout {

	use ConnectionConsumer,
		StripePayoutConsumer;

	/**
	 * @return BillVO|null
	 * @throws \Exception
	 */
	public function createBill() {
		// Check to ensure the Stripe contact can be found
		$this->verifyStripeContactExists();
		$oBill = ( new FindBillForStripePayout() )
			->setConnection( $this->getConnection() )
			->setStripePayout( $this->getStripePayout() )
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

		$oContact = ( new Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( $oWebApp->config( 'accounting.freeagent.contacts.stripe' ) )
			->sendRequestWithVoResponse();

		$oBill = ( new Create() )
			->setConnection( $this->getConnection() )
			->setContact( $oContact )
			->setReference( $oPayout->id )
			->setDatedOn( $oPayout->arrival_date )
			->setDueOn( $oPayout->arrival_date )
			->setCategoryId( $oWebApp->config( 'accounting.freeagent.codes.stripe_bill_category' ) )
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
	 * @return ContactVO|null
	 * @throws \Exception
	 */
	protected function verifyStripeContactExists() {
		$oAccounting = $this->getOAuthClient();
		$oContact = ( new Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( WebApp::instance()->config( 'accounting.freeagent.contacts.stripe' ) )
			->sendRequestWithVoResponse();
		if ( empty( $oContact ) ) {
			throw new \Exception( sprintf( 'Failed to find Stripe contact in FreeAgent: %s', $oAccounting->getLastRawResponse() ) );
		}
		if ( !preg_match( '#stripe#i', $oContact->getOrganisationName() ) ) {
			throw new \Exception( 'The contact found in FreeAgent does not appear to be a Stripe contact' );
		}
		return $oContact;
	}
}