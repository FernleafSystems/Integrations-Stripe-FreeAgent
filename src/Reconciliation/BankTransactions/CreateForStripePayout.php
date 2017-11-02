<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\BankTransactions;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankTransactions\BankTransactionVO;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankTransactions\Create;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankAccountVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;

/**
 * Class CreateBankTransactionForStripePayout
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\BankTransactions
 */
class CreateForStripePayout {

	use BankAccountVoConsumer,
		ConnectionConsumer,
		StripePayoutConsumer;

	/**
	 * @return BankTransactionVO|null
	 */
	public function create() {
		$oPayout = $this->getStripePayout();
		/** @var BankTransactionVO $oBankTxn */
		$oBankTxn = ( new Create() )
			->setConnection( $this->getConnection() )
			->create(
				$this->getBankAccountVo(),
				$oPayout->arrival_date,
				$oPayout->amount / 100,
				sprintf( 'Automatically create bank transaction for Stripe Payout %s', $oPayout->id )
			);
		return $oBankTxn;
	}
}