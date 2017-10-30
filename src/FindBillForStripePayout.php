<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\ContactVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;

/**
 * Class FindBillForStripePayout
 * @package iControlWP\Integration\FreeAgent
 */
class FindBillForStripePayout {

	use ConnectionConsumer,
		ContactVoConsumer,
		StripePayoutConsumer;

	/**
	 * @return bool
	 */
	public function hasBill() {
		return !empty( $this->find() );
	}

	/**
	 * @return Entities\Bills\BillVO|null
	 */
	public function find() {
		$oBill = null;
		$oPayout = $this->getStripePayout();

		if ( !empty( $oPayout->metadata[ 'ext_bill_id' ] ) ) {
			$oBill = ( new Entities\Bills\Retrieve() )
				->setConnection( $this->getConnection() )
				->setEntityId( $oPayout->metadata[ 'ext_bill_id' ] )
				->sendRequestWithVoResponse();
			if ( empty( $oBill ) ) {
				$oPayout->metadata[ 'ext_bill_id' ] = null;
				$oPayout->save();
			}
		}

		if ( empty( $oBill ) ) {

			try {
				$oBill = $this->findBillForStripePayout();
				$oPayout->metadata[ 'ext_bill_id' ] = $oBill->getId();
				$oPayout->save();
			}
			catch ( \Exception $oE ) {
				trigger_error( $oE->getMessage() );
			}
		}

		return $oBill;
	}

	/**
	 * @return Entities\Bills\BillVO|null
	 * @throws \Exception
	 */
	protected function findBillForStripePayout() {
		$oPayout = $this->getStripePayout();

		$oBill = ( new Entities\Bills\Find() )
			->setConnection( $this->getConnection() )
			->setContact( $this->getContactVo() )
			->setDateRange( $oPayout->arrival_date, 5 )
			->findByReference( $oPayout->id );
		if ( empty( $oBill ) ) {
			throw new \Exception( sprintf( 'Failed to find bill in FreeAgent for Payout transfer ID %s', $oPayout->id ) );
		}
		return $oBill;
	}
}