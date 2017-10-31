<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankAccounts\BankAccountVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankAccounts\Retrieve;
use Stripe\Payout;

/**
 * Class GetBankAccountForPayout
 * @package iControlWP\Integration\FreeAgent
 */
class GetBankAccountForPayout {

	use ConnectionConsumer;

	/**
	 * @param Payout $oPayout
	 * @return BankAccountVO|null
	 */
	public function get( $oPayout, $nFreeAgentBankAccountId ) {

		$sCurrency = strtolower( $oPayout->currency );

		try {
			$nBankId = WebApp::instance()->config( 'accounting.freeagent.bank_accounts.bank_id_'.$sCurrency );
		}
		catch ( \Exception $oE ) {
		}

		if ( empty( $nBankId ) ) {
			$nBankId = WebApp::instance()->config( 'accounting.freeagent.bank_accounts.bank_id_gbp' );
		}

		return ( new Retrieve() )
			->setConnection( $this->getConnection() )
			->setEntityId( $nBankId )
			->sendRequestWithVoResponse();
	}
}