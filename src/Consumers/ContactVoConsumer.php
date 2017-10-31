<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Consumers;

use FernleafSystems\ApiWrappers\Freeagent\Entities\Contacts\ContactVO;

/**
 * Trait ContactVoConsumer
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Consumers
 */
trait ContactVoConsumer {

	/**
	 * @var ContactVO
	 */
	private $oFreeagentContactVO;

	/**
	 * @return ContactVO
	 */
	public function getContactVo() {
		return $this->oFreeagentContactVO;
	}

	/**
	 * @param ContactVO $oVo
	 * @return $this
	 */
	public function setContactVo( $oVo ) {
		$this->oFreeagentContactVO = $oVo;
		return $this;
	}
}