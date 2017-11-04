<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Consumers;

use FernleafSystems\Integrations\Stripe_Freeagent\DataWrapper\FreeagentConfigVO;

/**
 * Trait FreeagentConfigVoConsumer
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Consumers
 */
trait FreeagentConfigVoConsumer {

	/**
	 * @var FreeagentConfigVO
	 */
	private $oFreeagentConfigVoConsumer;

	/**
	 * @return FreeagentConfigVO
	 */
	public function getFreeagentConfigVO() {
		return $this->oFreeagentConfigVoConsumer;
	}

	/**
	 * @param FreeagentConfigVO $oVO
	 * @return $this
	 */
	public function setFreeagentConfigVO( $oVO ) {
		$this->oFreeagentConfigVoConsumer = $oVO;
		return $this;
	}
}