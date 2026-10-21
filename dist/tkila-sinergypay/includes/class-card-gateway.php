<?php

namespace TKILA_SinergyPay;

defined( 'ABSPATH' ) || exit;

/**
 * Placeholder for future card payments.
 *
 * Do not register or expose this gateway until PCI DSS requirements and the
 * official SinergyPay card integration flow have been confirmed.
 */
final class Card_Gateway {
    public const STATUS = 'locked_pending_pci';

    private function __construct() {}
}
