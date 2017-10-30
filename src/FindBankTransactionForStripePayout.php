<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;
use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class FindBankTransactionForStripePayout
 * @property Entities\BankAccounts\BankAccountVO $bank_account
 * @package iControlWP\Integration\FreeAgent
 */
class FindBankTransactionForStripePayout {

	use ConnectionConsumer,
		StdClassAdapter,
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
	 * @return Entities\BankTransactions\BankTransactionVO[]
	 */
	protected function getUnexplainedBankTxns() {
		/** @var Entities\BankTransactions\BankTransactionVO[] $aTxn */
		$aTxn = ( new Entities\BankTransactions\Find() )
			->setConnection( $this->getConnection() )
			->filterByDateRange( $this->getStripePayout()->arrival_date, 7 )
			->setBankAccount( $this->getBankAccount() )
			->filterByUnexplained()
			->all();
		return $aTxn;
	}

	/**
	 * @return Entities\BankAccounts\BankAccountVO|null
	 */
	public function getBankAccount() {
		return $this->getParam( 'bank_account' );
	}

	/**
	 * @param Entities\BankAccounts\BankAccountVO $oBankAccount
	 * @return $this
	 */
	public function setBankAccount( $oBankAccount ) {
		return $this->setParam( 'bank_account', $oBankAccount );
	}
}