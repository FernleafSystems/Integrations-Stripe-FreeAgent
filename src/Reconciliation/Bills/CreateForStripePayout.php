<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Bills\BillVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Bills\Create;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\ContactVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\Retrieve;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\FreeagentConfigVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;

/**
 * Class CreateForStripePayout
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills
 */
class CreateForStripePayout {

	use ConnectionConsumer,
		FreeagentConfigVoConsumer,
		StripePayoutConsumer;

	/**
	 * @return BillVO|null
	 * @throws \Exception
	 */
	public function createBill() {
		$oBill = ( new FindForStripePayout() )
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
		$oFaConfig = $this->getFreeagentConfigVO();
		$oPayout = $this->getStripePayout();

		$nTotalFees = ( new SumTotalFeesForStripePayout() )
			->setStripePayout( $oPayout )
			->count();

		/** @var ContactVO $oStripeContact */
		$oStripeContact = ( new Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( $oFaConfig->getStripeContactId() )
			->retrieve();
		if ( empty( $oStripeContact ) ) {
			throw new \Exception( sprintf( 'Failed to load FreeAgent Contact bill for Stripe with ID "%s" ', $oFaConfig->getStripeContactId() ) );
		}

		$aComments = array(
			sprintf( 'Bill for Stripe Payout: https://dashboard.stripe.com/payouts/%s', $oPayout->id ),
			sprintf( 'Gross Amount: %s %s', $oPayout->currency, $oPayout->amount ),
			sprintf( 'Fees Total: %s %s', $oPayout->currency, $nTotalFees/100 ),
			sprintf( 'Net Amount: %s %s', $oPayout->currency, round( $oPayout->amount/100, 2 ) )
		);

		$oBill = ( new Create() )
			->setConnection( $this->getConnection() )
			->setContact( $oStripeContact )
			->setReference( $oPayout->id )
			->setDatedOn( $oPayout->arrival_date )
			->setDueOn( $oPayout->arrival_date )
			->setCategoryId( $this->getFreeagentConfigVO()->getStripeBillCategoryId() )
			->setComment( implode( "\n", $aComments ) )
			->setTotalValue( $nTotalFees/100 )
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
}