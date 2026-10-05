<?php
/**
 * Mombasa Mall Basement Parking - Thermal Receipt Printer Service
 * Generates thermal tickets via mike42/escpos-php.
 * Failures never crash or block session creation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/plate_helper.php';

// Polyfills for PHP environments without php_intl extension enabled
if (!class_exists('Normalizer', false)) {
    class Normalizer {
        public const FORM_C = 1;
        public static function normalize(string $string, int $form = self::FORM_C): string {
            return $string;
        }
    }
}

if (!class_exists('IntlBreakIterator', false)) {
    class IntlBreakIterator {
        public const DONE = -1;
        private array $chars = [];
        private int $index = -1;

        public static function createCodePointInstance(): self {
            return new self();
        }

        public function setText(string $text): void {
            $this->chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $this->index = -1;
        }

        public function next(): int {
            $this->index++;
            return ($this->index < count($this->chars)) ? $this->index : self::DONE;
        }

        public function getLastCodePoint(): int {
            if ($this->index >= 0 && $this->index < count($this->chars)) {
                return mb_ord($this->chars[$this->index], 'UTF-8') ?: 0;
            }
            return 0;
        }
    }
}

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;

class PrinterService
{
    /**
     * Print a standard parking entrance ticket
     *
     * @param array $session Session details (ticket_id, plate_number, driver_name, destination, entry_time)
     * @return array [bool 'success', string 'message']
     */
    public static function printTicket(array $session): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $pCfg = $config['printer'];

        $mode        = $pCfg['mode'] ?? 'windows';
        $printerName = $pCfg['printer_name'] ?? 'POS80';
        $networkIp   = $pCfg['network_ip'] ?? '192.168.0.200';
        $networkPort = (int)($pCfg['network_port'] ?? 9100);

        $connector = null;
        $printer = null;

        try {
            // 1. Establish printer connector based on mode
            if ($mode === 'windows') {
                try {
                    $connector = new WindowsPrintConnector($printerName);
                } catch (Throwable $e) {
                    $connector = new DummyPrintConnector();
                    self::updateHealth('WARNING', "Printer '{$printerName}' connector fallback: " . $e->getMessage());
                }
            } elseif ($mode === 'network') {
                $connector = new NetworkPrintConnector($networkIp, $networkPort, 2);
            } else {
                // Fallback / dummy test mode
                $connector = new DummyPrintConnector();
            }

            $printer = new Printer($connector);

            // 2. Initialize and layout ticket
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            // Header
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
            $printer->text("MOMBASA MALL\n");
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("BASEMENT PARKING (60 SLOTS)\n");
            $printer->selectPrintMode();
            $printer->text("Tel: +254 700 000 000 | 24/7 Security\n");
            $printer->text("--------------------------------\n");

            // Ticket Identifier
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("TICKET ID: " . $session['ticket_id'] . "\n\n");
            $printer->selectPrintMode();

            // QR Code for easy scanning at exit
            try {
                $printer->qrCode($session['ticket_id'], Printer::QR_ECLEVEL_M, 6);
                $printer->feed(1);
            } catch (Throwable $qrErr) {
                // If native QR isn't supported by model, print barcode or skip
                $printer->text("[" . $session['ticket_id'] . "]\n");
            }

            // Big License Plate Number
            $formattedPlate = PlateHelper::format($session['plate_number']);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH | Printer::MODE_EMPHASIZED);
            $printer->text($formattedPlate . "\n");
            $printer->selectPrintMode();
            $printer->text("--------------------------------\n");

            // Key Session Details
            $entryTs = !empty($session['entry_time']) ? strtotime($session['entry_time']) : time();
            $expectedOutTs = $entryTs + (150 * 60); // 2.5 hours = 150 minutes

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Driver       : " . $session['driver_name'] . "\n");
            $printer->text("Destination  : " . $session['destination'] . "\n");
            $printer->text("TIME IN      : " . date('d/m/Y H:i:s', $entryTs) . "\n");
            $printer->text("MAX STAY     : 2.5 Hours (150 Mins)\n");
            $printer->text("EXPECTED OUT : " . date('d/m/Y H:i:s', $expectedOutTs) . "\n");
            $printer->text("Gate Guard   : " . ($session['guard_name'] ?? 'Gate Officer') . "\n");
            $printer->text("Parking Fee  : FREE (Within 2.5 Hrs)\n");
            $printer->text("--------------------------------\n");

            // Footer instructions
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text("KEEP THIS TICKET SAFE\n");
            $printer->setEmphasis(false);
            $printer->text("Customer permitted 2.5 hrs within mall.\n");
            $printer->text("Present ticket to security upon exit.\n");
            $printer->text("Asante kwa Kutembelea Mombasa Mall!\n\n");

            // Cut paper
            $printer->cut();
            $printer->close();

            // Update printer health to OK
            self::updateHealth('OK', 'Thermal printer spooled ticket successfully');

            return ['success' => true, 'message' => 'Ticket printed successfully'];
        } catch (Throwable $e) {
            $errMsg = 'Thermal printer error: ' . $e->getMessage();
            error_log($errMsg);

            if ($printer !== null) {
                try {
                    $printer->close();
                } catch (Throwable $closeErr) {
                    // Ignore close failures
                }
            }

            // Update printer health to WARNING
            self::updateHealth('WARNING', 'Printer spool error: ' . $e->getMessage());

            return ['success' => false, 'message' => $errMsg];
        }
    }

    /**
     * Test print command for Admin troubleshooting
     */
    public static function testPrint(): array
    {
        return self::printTicket([
            'ticket_id'    => 'TEST-' . date('Ymd-His'),
            'plate_number' => 'KDA 000A',
            'driver_name'  => 'PRINTER HARDWARE TEST',
            'destination'  => 'Self Diagnostics',
            'entry_time'   => date('Y-m-d H:i:s'),
            'guard_name'   => 'Admin Diagnostics',
        ]);
    }

    private static function updateHealth(string $status, string $message): void
    {
        try {
            $db = get_db();
            $stmt = $db->prepare('
                INSERT INTO device_health (device, status, last_checked_at, message)
                VALUES ("printer", :status, NOW(), :message)
                ON DUPLICATE KEY UPDATE status = VALUES(status), last_checked_at = NOW(), message = VALUES(message)
            ');
            $stmt->execute([':status' => $status, ':message' => substr($message, 0, 255)]);
        } catch (Throwable) {
            // Ignore DB health log errors
        }
    }
}
