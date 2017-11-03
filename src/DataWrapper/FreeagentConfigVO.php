<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\DataWrapper;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class FreeagentConfigVO
 * @package FernleafSystems\Integrations\Stripe_Freeagent\DataWrapper
 */
class FreeagentConfigVO {

	use StdClassAdapter;

	/**
	 * @return int
	 */
	public function getBankAccountIdEur() {
		return $this->getBankAccountIdForCurrency( 'eur' );
	}

	/**
	 * @return int
	 */
	public function getBankAccountIdGbp() {
		return $this->getBankAccountIdForCurrency( 'gbp' );
	}

	/**
	 * @return int
	 */
	public function getBankAccountIdUsd() {
		return $this->getBankAccountIdForCurrency( 'usd' );
	}

	/**
	 * @param string $sCurrency
	 * @return int
	 */
	public function getBankAccountIdForCurrency( $sCurrency ) {
		return $this->getNumericParam( 'bank_account_id_'.strtolower( $sCurrency ) );
	}

	/**
	 * @return int
	 */
	public function getBankAccountIdForeignCurrencyTransfer() {
		return $this->getNumericParam( 'bank_account_id_foreign' );
	}

	/**
	 * @return int
	 */
	public function getInvoiceItemCategoryId() {
		return $this->getNumericParam( 'invoice_item_cat_id' );
	}

	/**
	 * @return int
	 */
	public function getStripeBillCategoryId() {
		return $this->getNumericParam( 'stripe_bill_cat_id' );
	}

	/**
	 * @return int
	 */
	public function getStripeContactId() {
		return $this->getNumericParam( 'stripe_contact_id' );
	}

	/**
	 * @return bool
	 */
	public function isAutoCreateBankTransactions() {
		return (bool)$this->getParam( 'auto_create_bank_txn' );
	}

	/**
	 * @param int $nVal
	 * @return $this
	 */
	public function setBankAccountIdEur( $nVal ) {
		return $this->setParam( 'bank_account_id_eur', $nVal );
	}

	/**
	 * @param int $nVal
	 * @return $this
	 */
	public function setBankAccountIdGbp( $nVal ) {
		return $this->setParam( 'bank_account_id_gbp', $nVal );
	}

	/**
	 * @param int $nVal
	 * @return $this
	 */
	public function setBankAccountIdUsd( $nVal ) {
		return $this->setParam( 'bank_account_id_usd', $nVal );
	}

	/**
	 * @param string $sCurrency
	 * @param int $nVal
	 * @return $this
	 */
	public function setBankAccountIdForCurrency( $sCurrency, $nVal ) {
		return $this->setParam( 'bank_account_id_'.strtolower( $sCurrency ), $nVal );
	}

	/**
	 * @param int $nVal
	 * @return $this
	 */
	public function setBankAccountIdForeignCurrencyTransfer( $nVal ) {
		return $this->setParam( 'bank_account_id_foreign', $nVal );
	}

	/**
	 * @param int $nVal
	 * @return $this
	 */
	public function setInvoiceItemCategoryId( $nVal ) {
		return $this->setParam( 'invoice_item_cat_id', $nVal );
	}

	/**
	 * @param bool $bAutoCreate
	 * @return $this
	 */
	public function setIsAutoCreateBankTransactions( $bAutoCreate = true ) {
		return $this->setParam( 'auto_create_bank_txn', $bAutoCreate );
	}

	/**
	 * @param int $nVal
	 * @return $this
	 */
	public function setStripeBillCategoryId( $nVal ) {
		return $this->setParam( 'stripe_bill_cat_id', $nVal );
	}

	/**
	 * @param int $nVal
	 * @return $this
	 */
	public function setStripeContactId( $nVal ) {
		return $this->setParam( 'stripe_contact_id', $nVal );
	}
}