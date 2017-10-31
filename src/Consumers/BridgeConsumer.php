<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Consumers;

use FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\Edd\BridgeInterface;

/**
 * Trait BridgeConsumer
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Consumers
 */
trait BridgeConsumer {

	/**
	 * @var BridgeInterface
	 */
	private $oMiddleManShopBridge;

	/**
	 * @return BridgeInterface
	 */
	public function getBridge() {
		return $this->oMiddleManShopBridge;
	}

	/**
	 * @param BridgeInterface $oBridge
	 * @return $this
	 */
	public function setBridge( $oBridge ) {
		$this->oMiddleManShopBridge = $oBridge;
		return $this;
	}
}