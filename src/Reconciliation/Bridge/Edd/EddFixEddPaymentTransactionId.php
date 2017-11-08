<?php

namespace FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\Edd;

/**
 * Class EddFixEddPaymentTransactionId
 * @package FernleafSystems\Integrations\Stripe_Freeagent\Reconciliation\Bridge\Edd
 */
class EddFixEddPaymentTransactionId {

	/**
	 * @param \EDD_Payment $oPayment
	 */
	public function fix( $oPayment ) {
		EDD_Recurring();

		if ( $oPayment->gateway == 'stripe' && ( strpos( $oPayment->transaction_id, 'ch_' ) !== 0 ) ) {

			/** @var \EDD_Subscription[] $aSubs */
			$aSubs = ( new \EDD_Subscriptions_DB() )
				->get_subscriptions( array( 'parent_payment_id' => $oPayment->ID ) );

			if ( count( $aSubs ) > 0 ) {
				$sNewTxnId = $aSubs[ 0 ]->get_transaction_id();
				if ( strpos( $sNewTxnId, 'ch_' ) === 0 ) {
					$oPayment->transaction_id = $sNewTxnId;
					$oPayment->save();

					$sNote = sprintf( 'Manually fixed non-population of transaction ID on Payment '.
									  'using 1st Subscription payment transaction ID. '.
									  'Sub ID: "%s"; Payment ID: "%s"; Stripe Txn ID: "%s"',
						$aSubs[ 0 ]->id, $oPayment->ID, $oPayment->transaction_id
					);
					$oPayment->add_note( $sNote );
				}
			}
		}
	}
}