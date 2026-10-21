<?php

namespace TKILA_SinergyPay;

defined( 'ABSPATH' ) || exit;

/** Atomic per-order mutex shared by checkout and webhook, including HPOS. */
final class Order_Lock {
    public static function acquire( int $order_id ): bool {
        // No automatic expiry: an expired worker could still be sending a remote POST.
        return add_option( 'tkila_sinergypay_lock_' . $order_id, time(), '', false );
    }

    public static function release( int $order_id ): void {
        delete_option( 'tkila_sinergypay_lock_' . $order_id );
    }
}
