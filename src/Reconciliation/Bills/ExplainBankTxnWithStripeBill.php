<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills;

use FernleafSystems\ApiWrappers\Base\ConnectionConsumer;
use FernleafSystems\ApiWrappers\Freeagent\Entities\BankTransactionExplanation\Create;
use FernleafSystems\ApiWrappers\Freeagent\Entities\Bills\BillVO;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankAccountVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\BankTransactionVoConsumer;
use FernleafSystems\Integrations\Stripe_Freeagent\Consumers\StripePayoutConsumer;

/**
 * Retrieve the Stripe Bill within FreeAgent, and the associated Bank Transaction
 * for the Payout and creates a FreeAgent Explanation for it.
 * Class ExplainBankTxnWithStripeBill
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bills
 */
class ExplainBankTxnWithStripeBill {

	use BankAccountVoConsumer,
		BankTransactionVoConsumer,
		StripePayoutConsumer,
		ConnectionConsumer;
	const DEFAULT_NATIVE_CURRENCY = 'GBP';

	/**
	 * The native Freeagent account currency - all Bills must be processed in this currency
	 * @var string
	 */
	protected $sNativeCurrency;

	/**
	 * Determine whether we're working in our native currency, or whether
	 * we have to explain the bill using our Foreign Bill handling.
	 * @param BillVO $oBill
	 * @throws \Exception
	 */
	public function process( $oBill ) {
		if ( $oBill->getAmountDue() > 0 ) {

			if ( strcasecmp( $this->getStripePayout()->currency, $this->getNativeCurrency() ) == 0 ) {
				$this->createSimpleExplanation( $oBill );
			}
			else {
				if ( is_null( $this->getBankAccountVo() ) ) {
					throw  new \Exception( 'Attempting to explain a foreign currency bill without a currency transfer account' );
				}
				( new ExplainBankTxnWithForeignBill() )
					->setStripePayout( $this->getStripePayout() )
					->setConnection( $this->getConnection() )
					->setBankTransactionVo( $this->getBankTransactionVo() )
					->setBankAccountVo( $this->getBankAccountVo() )
					->createExplanation( $oBill );
			}
		}
	}

	/**
	 * @param BillVO $oBill
	 * @throws \Exception
	 */
	public function createSimpleExplanation( $oBill ) {

		$oBankTxnExp = ( new Create() )
			->setConnection( $this->getConnection() )
			->setBankTxn( $this->getBankTransactionVo() )
			->setBillPaid( $oBill )
			->setValue( $oBill->getAmountTotal() )
			->create();

		if ( empty( $oBankTxnExp ) ) {
			throw new \Exception( 'Failed to explain bank transaction with a bill in FreeAgent.' );
		}
	}

	/**
	 * @return string
	 */
	public function getNativeCurrency() {
		return isset( $this->sNativeCurrency ) ? $this->sNativeCurrency : self::DEFAULT_NATIVE_CURRENCY;
	}

	/**
	 * @param string $sCurrency e.g. gbp, usd  (case insensitive)
	 * @return $this
	 */
	public function setNativeCurrency( $sCurrency ) {
		$this->sNativeCurrency = $sCurrency;
		return $this;
	}
}