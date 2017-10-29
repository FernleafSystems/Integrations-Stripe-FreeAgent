<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;

/**
 * Class FindBankTransactionForStripePayout
 * @package iControlWP\Integration\FreeAgent
 */
class FindBankTransactionForStripePayout {

	use ConnectionConsumer,
		StripePayoutConsumer;

	/**
	 * @return Entities\BankTransactions\BankTransactionVO|null
	 * @throws \Exception
	 */
	public function find() {
		$oBankTxn = null;
		$oPayout = $this->getStripePayout();

		if ( !empty( $oPayout->metadata[ 'ext_bank_txn_id' ] ) ) {
			$oBankTxn = ( new Entities\BankTransactions\Retrieve() )
				->setConnection( $this->getConnection() )
				->setEntityId( $oPayout->metadata[ 'ext_bank_txn_id' ] )
				->sendRequestWithVoResponse();

			if ( empty( $oBankTxn ) ) {
				$oPayout->metadata[ 'ext_bank_txn_id' ] = null;
				$oPayout->save();
			}
		}

		if ( empty( $oBankTxn ) ) {

			foreach ( $this->getUnexplainedBankTxns() as $oTxn ) {
				if ( (string)( $oTxn->getAmountTotal()*100 ) == (string)$oPayout->amount ) {
					$oBankTxn = $oTxn;
					$oPayout->metadata[ 'ext_bank_txn_id' ] = $oBankTxn->getId();
					$oPayout->save();
					break;
				}
			}
		}

		return $oBankTxn;
	}

	/**
	 * @return BankTransactionVO[]
	 */
	protected function getUnexplainedBankTxns() {
		return ( new Find() )
			->setConnection( $this->getConnection() )
			->setDateRange( $this->getStripePayout()->arrival_date, 7 )
			->setBankAccount( $this->getBankAccount() )
			->setView( 'unexplained' )
			->find();
	}

	/**
	 * @return Entities\BankAccounts\BankAccountVO|null
	 */
	protected function getBankAccount() {
		return ( new GetBankAccountForPayout() )
			->setOAuthClient( $this->getOAuthClient() )
			->get( $this->getStripePayout() );
	}
}