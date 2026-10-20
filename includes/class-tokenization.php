<?php

namespace TKILA_SinergyPay;

defined( 'ABSPATH' ) || exit;

/**
 * Placeholder for future SinergyPay card tokenization.
 *
 * Never store CVV. Do not implement PAN handling or assume token API behavior
 * until the official SinergyPay documentation and PCI scope are confirmed.
 */
final class Tokenization {
    public const STATUS = 'locked_pending_pci';

    private function __construct() {}
}
