<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Verify;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities;
use FernleafSystems\Integrations\Stripe_Freeagent\DataWrapper\FreeagentConfigVO;

/**
 * Class VerifyFreeagentConfig
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Verify
 */
class VerifyFreeagentConfig {

	use ConnectionConsumer;

	/**
	 * @param FreeagentConfigVO $oFreeAgentConfig
	 * @return bool
	 */
	public function verify( $oFreeAgentConfig ) {
		$oCon = $this->getConnection();

		$nStripeContactID = $oFreeAgentConfig->getStripeContactId();
		$bValid = $nStripeContactID > 0 &&
				  ( new Entities\Contacts\Retrieve() )
					  ->setConnection( $this->getConnection() )
					  ->setEntityId( $oFreeAgentConfig->getStripeContactId() )
					  ->exists();

		$nApiUserPermissionLevel = ( new Entities\Users\RetrieveMe() )
			->setConnection( $oCon )
			->retrieve()
			->getPermissionLevel();
		$bValid = $bValid && ( $nApiUserPermissionLevel >= 6 ); // at least Banking level

		$bValid = $bValid && ( new Entities\Categories\Retrieve() )
				->setConnection( $oCon )
				->setEntityId( $oFreeAgentConfig->getInvoiceItemCategoryId() )
				->exists();

		$bValid = $bValid && ( new Entities\Categories\Retrieve() )
				->setConnection( $oCon )
				->setEntityId( $oFreeAgentConfig->getStripeBillCategoryId() )
				->exists();

		$oBankAccountRetriever = ( new Entities\BankAccounts\Retrieve() )
			->setConnection( $oCon );
		if ( $bValid ) {
			foreach ( $oFreeAgentConfig->getAllBankAccounts() as $sCurrency => $nId ) {
				$oBankAccount = $oBankAccountRetriever
					->setEntityId( $nId )
					->retrieve();
				$bValid = $bValid && !is_null( $oBankAccount ) &&
						  strcasecmp( $sCurrency, $oBankAccount->getCurrency() ) == 0;
			}
		}

		$nForeignCurrencyId = $oFreeAgentConfig->getBankAccountIdForeignCurrencyTransfer();
		if ( $bValid && $nForeignCurrencyId > 0 ) {
			$bValid = $oBankAccountRetriever
				->setEntityId( $nForeignCurrencyId )
				->exists();
		}

		return $bValid;
	}
}