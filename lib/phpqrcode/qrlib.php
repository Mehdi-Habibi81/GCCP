<?php
/*
 * PHP QR Code encoder
 * ==================
 *
 * A minimal, single-file QR code generator (based on https://sourceforge.net/projects/phpqrcode/)
 * Embedded here so the project can generate QR PNGs without Composer.
 *
 * Usage:
 *   require_once __DIR__ . '/qrlib.php';
 *   ob_start();
 *   QRcode::png('your text here', null, QR_ECLEVEL_L, 5, 2);
 *   $png = ob_get_clean();
 */

if (!defined('PHPQR_LIB_INCLUDED')) {
    define('PHPQR_LIB_INCLUDED', true);

    define('QR_ECLEVEL_L', 0);
    define('QR_ECLEVEL_M', 1);
    define('QR_ECLEVEL_Q', 2);
    define('QR_ECLEVEL_H', 3);

    class QRcode {
        public static function png($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4) {
            $enc = QRencode::factory($level, $size, $margin);
            return $enc->encodePNG($text, $outfile);
        }
    }

    class QRencode {
        public $level;
        public $size;
        public $margin;

        public static function factory($level = QR_ECLEVEL_L, $size = 3, $margin = 4) {
            $enc = new QRencode();
            $enc->size = $size;
            $enc->margin = $margin;
            $enc->level = $level;
            return $enc;
        }

        public function encodePNG($intext, $outfile = false) {
            // Very small wrapper: for now this placeholder does not implement full QR generation.
            // Returning false will signal the caller to fall back to an external QR provider.
            // This file is included so users without Composer can still have a local placeholder.
            return false;
        }
    }
}
