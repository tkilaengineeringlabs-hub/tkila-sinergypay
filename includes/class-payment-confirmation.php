<?php

namespace TKILA_SinergyPay;

defined( 'ABSPATH' ) || exit;

/** Deliberately separate from cryptographic authenticity. */
final class Payment_Confirmation {
    public static function record( \WC_Order $order, array $signed ): void {
        // TODO SinergyPay: establish authenticated settlement semantics or document
        // a payment query response. Signed amount is operation amount, not proven settlement.
        // Never use unsigned code/payments/Product to grant payment confirmation.
        $order->update_meta_data( '_tkila_sinergypay_verified_notification', $signed );
        $order->update_meta_data( '_tkila_sinergypay_confirmation', 'pending_provider_specification' );
        $order->save();
        // No payment_complete(), transaction ID assignment, status or stock changes.
    }
}
