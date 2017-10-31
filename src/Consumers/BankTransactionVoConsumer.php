<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Consumers;

use FernleafSystems\ApiWrappers\Freeagent\Entities\BankTransactions\BankTransactionVO;

/**
 * Trait BankTransactionVoConsumer
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Consumers
 */
trait BankTransactionVoConsumer {

	/**
	 * @var BankTransactionVO
	 */
	private $oFreeagentBankTransactionVO;

	/**
	 * @return BankTransactionVO
	 */
	public function getBankTransactionVo() {
		return $this->oFreeagentBankTransactionVO;
	}

	/**
	 * @param BankTransactionVO $oVo
	 * @return $this
	 */
	public function setBankTransactionVo( $oVo ) {
		$this->oFreeagentBankTransactionVO = $oVo;
		return $this;
	}
}