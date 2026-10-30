<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PosDevice;
use App\Models\PosPayment;
use App\Models\PosPrintJob;
use App\Models\PosShift;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReceiptPrinterService
{
    // ESC/POS Command Constants
    public const ESC = "\x1B";

    public const GS = "\x1D";

    public const FS = "\x1C";

    public const CMD_INIT = "\x1B\x40";

    public const CMD_CUT_PARTIAL = "\x1D\x56\x01";

    public const CMD_CUT_FULL = "\x1D\x56\x00";

    public const CMD_DRAWER_PULSE = "\x1B\x70\x00\x19\xFA"; // Open cash drawer pin 2

    public const ALIGN_LEFT = "\x1B\x61\x00";

    public const ALIGN_CENTER = "\x1B\x61\x01";

    public const ALIGN_RIGHT = "\x1B\x61\x02";

    public const TXT_NORMAL = "\x1D\x21\x00";

    public const TXT_2HEIGHT = "\x1D\x21\x01";

    public const TXT_2WIDTH = "\x1D\x21\x10";

    public const TXT_DOUBLE = "\x1D\x21\x11";

    public const TXT_BOLD_ON = "\x1B\x45\x01";

    public const TXT_BOLD_OFF = "\x1B\x45\x00";

    /**
     * Column widths
     */
    public const WIDTH_80MM = 48;

    public const WIDTH_58MM = 32;

    /**
     * Build customer receipt in ESC/POS byte sequence.
     */
    public function buildCustomerReceiptEscpos(Order $order, ?PosPayment $payment = null, ?Vendor $vendor = null, string $paperWidth = '80mm'): string
    {
        $cols = ($paperWidth === '58mm') ? self::WIDTH_58MM : self::WIDTH_80MM;
        $vendor = $vendor ?? $order->vendor;
        $currency = $order->currency ?? 'AMD';

        $out = self::CMD_INIT;

        // Header
        $out .= self::ALIGN_CENTER;
        $out .= self::TXT_BOLD_ON.self::TXT_2HEIGHT;
        $out .= ($vendor?->name ?? 'elam.menu')."\n";
        $out .= self::TXT_NORMAL.self::TXT_BOLD_OFF;

        if ($vendor?->address) {
            $out .= $vendor->address."\n";
        }
        if ($vendor?->phone) {
            $out .= 'Tel: '.$vendor->phone."\n";
        }
        $out .= self::divider('-', $cols)."\n";

        // Order Info
        $out .= self::ALIGN_LEFT;
        $out .= self::TXT_BOLD_ON;
        $out .= self::padBetween('ORDER: #'.($order->order_number ?? $order->id), Carbon::parse($order->created_at)->format('Y-m-d H:i'), $cols)."\n";
        $out .= self::TXT_BOLD_OFF;

        if ($order->restaurantTable) {
            $tableInfo = 'Table: '.$order->restaurantTable->name;
            if ($order->guest_count) {
                $tableInfo .= ' ('.$order->guest_count.' guests)';
            }
            $out .= $tableInfo."\n";
        }

        if ($order->waiter) {
            $out .= 'Server: '.$order->waiter->name."\n";
        }

        $out .= self::divider('=', $cols)."\n";

        // Column Titles
        $out .= self::TXT_BOLD_ON;
        if ($cols >= 48) {
            $out .= sprintf("%-4s %-24s %8s %10s\n", 'QTY', 'ITEM', 'PRICE', 'TOTAL');
        } else {
            $out .= sprintf("%-3s %-15s %6s %6s\n", 'QTY', 'ITEM', 'PRC', 'TOT');
        }
        $out .= self::TXT_BOLD_OFF;
        $out .= self::divider('-', $cols)."\n";

        // Items
        $items = $order->items ?? [];
        foreach ($items as $item) {
            $qty = (int) $item->quantity;
            $name = $item->product_name;
            if (! empty($item->variation_name)) {
                $name .= ' ('.$item->variation_name.')';
            }
            $price = number_format((float) $item->unit_price, 2);
            $total = number_format((float) $item->subtotal, 2);

            if ($cols >= 48) {
                $shortName = mb_substr($name, 0, 24);
                $out .= sprintf("%-4d %-24s %8s %10s\n", $qty, $shortName, $price, $total);
            } else {
                $shortName = mb_substr($name, 0, 15);
                $out .= sprintf("%-3d %-15s %6s %6s\n", $qty, $shortName, $price, $total);
            }

            // Modifiers
            if (! empty($item->modifiers) && is_array($item->modifiers)) {
                foreach ($item->modifiers as $mod) {
                    $modName = is_array($mod) ? ($mod['name'] ?? '') : (string) $mod;
                    if ($modName !== '') {
                        $out .= '   + '.$modName."\n";
                    }
                }
            }

            // Item Note
            if (! empty($item->notes)) {
                $out .= '   * '.$item->notes."\n";
            }
        }

        $out .= self::divider('-', $cols)."\n";

        // Totals
        $subtotal = number_format((float) $order->subtotal, 2);
        $tax = number_format((float) ($order->tax_amount ?? 0), 2);
        $discount = number_format((float) ($order->discount_amount ?? 0), 2);
        $tip = number_format((float) ($order->tip_amount ?? 0), 2);
        $total = number_format((float) $order->total_amount, 2);

        $out .= self::padBetween('Subtotal:', $subtotal.' '.$currency, $cols)."\n";

        if ((float) ($order->discount_amount ?? 0) > 0) {
            $out .= self::padBetween('Discount:', '-'.$discount.' '.$currency, $cols)."\n";
        }

        if ((float) ($order->tax_amount ?? 0) > 0) {
            $out .= self::padBetween('Tax (Included):', $tax.' '.$currency, $cols)."\n";
        }

        if ((float) ($order->tip_amount ?? 0) > 0) {
            $out .= self::padBetween('Tip:', '+'.$tip.' '.$currency, $cols)."\n";
        }

        $out .= self::divider('=', $cols)."\n";
        $out .= self::TXT_BOLD_ON.self::TXT_2HEIGHT;
        $out .= self::padBetween('TOTAL:', $total.' '.$currency, $cols)."\n";
        $out .= self::TXT_NORMAL.self::TXT_BOLD_OFF;
        $out .= self::divider('=', $cols)."\n";

        // Payment Info
        if ($payment) {
            $out .= self::padBetween('Method:', strtoupper($payment->payment_method), $cols)."\n";
            $out .= self::padBetween('Tendered:', number_format((float) $payment->tendered_amount, 2).' '.$currency, $cols)."\n";
            if ((float) $payment->change_amount > 0) {
                $out .= self::padBetween('Change:', number_format((float) $payment->change_amount, 2).' '.$currency, $cols)."\n";
            }
            if ($payment->transaction_reference) {
                $out .= self::padBetween('Ref #:', $payment->transaction_reference, $cols)."\n";
            }
        } elseif ((float) ($order->paid_amount ?? 0) > 0) {
            $out .= self::padBetween('Paid Amount:', number_format((float) $order->paid_amount, 2).' '.$currency, $cols)."\n";
        }

        // Footer
        $out .= "\n".self::ALIGN_CENTER;
        $out .= "Thank you for dining with us!\n";
        $out .= "Powered by elam.menu\n\n\n\n";

        // Partial Cut
        $out .= self::CMD_CUT_PARTIAL;

        return $out;
    }

    /**
     * Build plain text customer receipt.
     */
    public function buildCustomerReceiptText(Order $order, ?PosPayment $payment = null, ?Vendor $vendor = null, string $paperWidth = '80mm'): string
    {
        $cols = ($paperWidth === '58mm') ? self::WIDTH_58MM : self::WIDTH_80MM;
        $vendor = $vendor ?? $order->vendor;
        $currency = $order->currency ?? 'AMD';

        $lines = [];
        $lines[] = self::centerText($vendor?->name ?? 'elam.menu', $cols);
        if ($vendor?->address) {
            $lines[] = self::centerText($vendor->address, $cols);
        }
        if ($vendor?->phone) {
            $lines[] = self::centerText('Tel: '.$vendor->phone, $cols);
        }
        $lines[] = str_repeat('-', $cols);

        $lines[] = self::padBetween('ORDER: #'.($order->order_number ?? $order->id), Carbon::parse($order->created_at)->format('Y-m-d H:i'), $cols);

        if ($order->restaurantTable) {
            $lines[] = 'Table: '.$order->restaurantTable->name.($order->guest_count ? " ({$order->guest_count} guests)" : '');
        }
        if ($order->waiter) {
            $lines[] = 'Server: '.$order->waiter->name;
        }

        $lines[] = str_repeat('=', $cols);
        if ($cols >= 48) {
            $lines[] = sprintf('%-4s %-24s %8s %10s', 'QTY', 'ITEM', 'PRICE', 'TOTAL');
        } else {
            $lines[] = sprintf('%-3s %-15s %6s %6s', 'QTY', 'ITEM', 'PRC', 'TOT');
        }
        $lines[] = str_repeat('-', $cols);

        foreach ($order->items ?? [] as $item) {
            $qty = (int) $item->quantity;
            $name = $item->product_name.(! empty($item->variation_name) ? " ({$item->variation_name})" : '');
            $price = number_format((float) $item->unit_price, 2);
            $total = number_format((float) $item->subtotal, 2);

            if ($cols >= 48) {
                $lines[] = sprintf('%-4d %-24s %8s %10s', $qty, mb_substr($name, 0, 24), $price, $total);
            } else {
                $lines[] = sprintf('%-3d %-15s %6s %6s', $qty, mb_substr($name, 0, 15), $price, $total);
            }

            if (! empty($item->modifiers) && is_array($item->modifiers)) {
                foreach ($item->modifiers as $mod) {
                    $modName = is_array($mod) ? ($mod['name'] ?? '') : (string) $mod;
                    if ($modName !== '') {
                        $lines[] = '   + '.$modName;
                    }
                }
            }
            if (! empty($item->notes)) {
                $lines[] = '   * '.$item->notes;
            }
        }

        $lines[] = str_repeat('-', $cols);
        $lines[] = self::padBetween('Subtotal:', number_format((float) $order->subtotal, 2).' '.$currency, $cols);

        if ((float) ($order->discount_amount ?? 0) > 0) {
            $lines[] = self::padBetween('Discount:', '-'.number_format((float) $order->discount_amount, 2).' '.$currency, $cols);
        }
        if ((float) ($order->tip_amount ?? 0) > 0) {
            $lines[] = self::padBetween('Tip:', '+'.number_format((float) $order->tip_amount, 2).' '.$currency, $cols);
        }

        $lines[] = str_repeat('=', $cols);
        $lines[] = self::padBetween('TOTAL:', number_format((float) $order->total_amount, 2).' '.$currency, $cols);
        $lines[] = str_repeat('=', $cols);

        if ($payment) {
            $lines[] = self::padBetween('Method:', strtoupper($payment->payment_method), $cols);
            $lines[] = self::padBetween('Tendered:', number_format((float) $payment->tendered_amount, 2).' '.$currency, $cols);
            if ((float) $payment->change_amount > 0) {
                $lines[] = self::padBetween('Change:', number_format((float) $payment->change_amount, 2).' '.$currency, $cols);
            }
        }

        $lines[] = '';
        $lines[] = self::centerText('Thank you for dining with us!', $cols);
        $lines[] = self::centerText('Powered by elam.menu', $cols);

        return implode("\n", $lines);
    }

    /**
     * Build ESC/POS kitchen ticket for a specific station (or all stations).
     */
    public function buildKitchenTicketEscpos(Order $order, string $station = 'kitchen', array $items = [], string $paperWidth = '80mm'): string
    {
        $cols = ($paperWidth === '58mm') ? self::WIDTH_58MM : self::WIDTH_80MM;

        $out = self::CMD_INIT;

        // Station Header
        $out .= self::ALIGN_CENTER;
        $out .= self::TXT_BOLD_ON.self::TXT_DOUBLE;
        $out .= '*** '.strtoupper($station)." TICKET ***\n";
        $out .= self::TXT_NORMAL.self::TXT_BOLD_OFF;

        $out .= self::divider('=', $cols)."\n";

        // Order Meta
        $out .= self::ALIGN_LEFT;
        $out .= self::TXT_BOLD_ON.self::TXT_2HEIGHT;
        $tableLabel = $order->restaurantTable ? ('TABLE '.$order->restaurantTable->name) : 'TAKEAWAY';
        $out .= self::padBetween('ORDER #'.($order->order_number ?? $order->id), $tableLabel, $cols)."\n";
        $out .= self::TXT_NORMAL.self::TXT_BOLD_OFF;

        $out .= self::padBetween(
            'Server: '.($order->waiter?->name ?? 'POS'),
            Carbon::parse($order->created_at)->format('H:i:s'),
            $cols
        )."\n";

        if ($order->notes) {
            $out .= self::divider('-', $cols)."\n";
            $out .= self::TXT_BOLD_ON;
            $out .= 'SPECIAL INSTRUCTIONS: '.$order->notes."\n";
            $out .= self::TXT_BOLD_OFF;
        }

        $out .= self::divider('=', $cols)."\n";

        // Filter items for this station if items not explicitly passed
        if (empty($items)) {
            $allItems = $order->items ?? [];
            $items = collect($allItems)->filter(function ($item) use ($station) {
                if (strtolower($station) === 'all') {
                    return true;
                }
                $st = strtolower((string) ($item->kitchen_station ?? 'kitchen'));

                return $st === strtolower($station);
            })->all();
        }

        // Station Items
        foreach ($items as $item) {
            $qty = (int) ($item->quantity ?? 1);
            $name = $item->product_name ?? 'Item';
            if (! empty($item->variation_name)) {
                $name .= ' ['.$item->variation_name.']';
            }

            $out .= self::TXT_BOLD_ON.self::TXT_2HEIGHT;
            $out .= sprintf("[ %-2d ] %s\n", $qty, $name);
            $out .= self::TXT_NORMAL.self::TXT_BOLD_OFF;

            // Modifiers
            $modifiers = is_array($item->modifiers ?? null) ? $item->modifiers : [];
            if (! empty($modifiers)) {
                foreach ($modifiers as $mod) {
                    $modText = is_array($mod) ? ($mod['name'] ?? '') : (string) $mod;
                    if ($modText !== '') {
                        $out .= '     >>> '.strtoupper($modText)."\n";
                    }
                }
            }

            // Item Notes
            if (! empty($item->notes)) {
                $out .= '     ** NOTE: '.$item->notes."\n";
            }

            $out .= self::divider('-', $cols)."\n";
        }

        $out .= "\n\n\n";
        $out .= self::CMD_CUT_PARTIAL;

        return $out;
    }

    /**
     * Build plain text kitchen ticket.
     */
    public function buildKitchenTicketText(Order $order, string $station = 'kitchen', array $items = [], string $paperWidth = '80mm'): string
    {
        $cols = ($paperWidth === '58mm') ? self::WIDTH_58MM : self::WIDTH_80MM;

        $lines = [];
        $lines[] = self::centerText('*** '.strtoupper($station).' TICKET ***', $cols);
        $lines[] = str_repeat('=', $cols);

        $tableLabel = $order->restaurantTable ? ('TABLE '.$order->restaurantTable->name) : 'TAKEAWAY';
        $lines[] = self::padBetween('ORDER #'.($order->order_number ?? $order->id), $tableLabel, $cols);
        $lines[] = self::padBetween('Server: '.($order->waiter?->name ?? 'POS'), Carbon::parse($order->created_at)->format('H:i:s'), $cols);

        if ($order->notes) {
            $lines[] = str_repeat('-', $cols);
            $lines[] = 'SPECIAL INSTRUCTIONS: '.$order->notes;
        }

        $lines[] = str_repeat('=', $cols);

        if (empty($items)) {
            $allItems = $order->items ?? [];
            $items = collect($allItems)->filter(function ($item) use ($station) {
                if (strtolower($station) === 'all') {
                    return true;
                }

                return strtolower((string) ($item->kitchen_station ?? 'kitchen')) === strtolower($station);
            })->all();
        }

        foreach ($items as $item) {
            $qty = (int) ($item->quantity ?? 1);
            $name = $item->product_name ?? 'Item';
            if (! empty($item->variation_name)) {
                $name .= ' ['.$item->variation_name.']';
            }

            $lines[] = sprintf('[ %-2d ] %s', $qty, $name);

            $modifiers = is_array($item->modifiers ?? null) ? $item->modifiers : [];
            foreach ($modifiers as $mod) {
                $modText = is_array($mod) ? ($mod['name'] ?? '') : (string) $mod;
                if ($modText !== '') {
                    $lines[] = '     >>> '.strtoupper($modText);
                }
            }

            if (! empty($item->notes)) {
                $lines[] = '     ** NOTE: '.$item->notes;
            }

            $lines[] = str_repeat('-', $cols);
        }

        return implode("\n", $lines);
    }

    /**
     * Build ESC/POS Shift Report (Z-Report or X-Report).
     */
    public function buildShiftReportEscpos(PosShift $shift, string $type = 'Z', string $paperWidth = '80mm'): string
    {
        $cols = ($paperWidth === '58mm') ? self::WIDTH_58MM : self::WIDTH_80MM;
        $currency = 'AMD';

        $out = self::CMD_INIT;
        $out .= self::ALIGN_CENTER;
        $out .= self::TXT_BOLD_ON.self::TXT_2HEIGHT;
        $out .= ($type === 'Z' ? 'END OF SHIFT REPORT (Z-REPORT)' : 'MID-SHIFT AUDIT (X-REPORT)')."\n";
        $out .= self::TXT_NORMAL.self::TXT_BOLD_OFF;
        $out .= self::divider('=', $cols)."\n";

        $out .= self::ALIGN_LEFT;
        $out .= self::padBetween('Shift ID:', '#'.$shift->id, $cols)."\n";
        $out .= self::padBetween('Cashier:', $shift->user?->name ?? 'N/A', $cols)."\n";
        $out .= self::padBetween('Device:', $shift->posDevice?->name ?? 'POS Terminal', $cols)."\n";
        $out .= self::padBetween('Opened:', Carbon::parse($shift->opened_at)->format('Y-m-d H:i'), $cols)."\n";
        if ($shift->closed_at) {
            $out .= self::padBetween('Closed:', Carbon::parse($shift->closed_at)->format('Y-m-d H:i'), $cols)."\n";
        }
        $out .= self::divider('-', $cols)."\n";

        $cashSales = (float) ($shift->total_cash_sales ?? $shift->cash_sales ?? 0);
        $cardSales = (float) ($shift->total_card_sales ?? $shift->card_sales ?? 0);
        $cashIn = (float) ($shift->total_cash_in ?? $shift->cash_paid_in ?? 0);
        $cashOut = (float) ($shift->total_cash_out ?? $shift->cash_paid_out ?? 0);
        $totalTips = (float) ($shift->total_tips ?? 0);
        $totalRefunds = (float) ($shift->total_refunds ?? 0);
        $expectedCash = (float) ($shift->expected_cash ?? 0);
        $countedCash = $shift->counted_cash ?? $shift->actual_cash;
        $diff = (float) ($shift->discrepancy ?? $shift->cash_difference ?? 0);
        $totalOrders = $shift->orders_count ?? $shift->total_orders ?? 0;

        $out .= self::padBetween('Opening Float:', number_format((float) $shift->opening_float, 2).' '.$currency, $cols)."\n";
        $out .= self::padBetween('Cash Sales:', number_format($cashSales, 2).' '.$currency, $cols)."\n";
        $out .= self::padBetween('Card Sales:', number_format($cardSales, 2).' '.$currency, $cols)."\n";
        $out .= self::padBetween('Cash Paid In:', '+'.number_format($cashIn, 2).' '.$currency, $cols)."\n";
        $out .= self::padBetween('Cash Paid Out:', '-'.number_format($cashOut, 2).' '.$currency, $cols)."\n";
        $out .= self::padBetween('Total Tips:', number_format($totalTips, 2).' '.$currency, $cols)."\n";
        $out .= self::padBetween('Total Refunds:', '-'.number_format($totalRefunds, 2).' '.$currency, $cols)."\n";

        $out .= self::divider('=', $cols)."\n";
        $out .= self::TXT_BOLD_ON;
        $out .= self::padBetween('Expected Cash:', number_format($expectedCash, 2).' '.$currency, $cols)."\n";

        if ($countedCash !== null) {
            $out .= self::padBetween('Counted Cash:', number_format((float) $countedCash, 2).' '.$currency, $cols)."\n";
            $diffStr = ($diff >= 0 ? '+' : '').number_format($diff, 2).' '.$currency;
            $out .= self::padBetween('Difference:', $diffStr, $cols)."\n";
        }
        $out .= self::TXT_BOLD_OFF;
        $out .= self::divider('=', $cols)."\n";

        $out .= self::padBetween('Total Orders:', (string) $totalOrders, $cols)."\n";
        $out .= "\n\n\n";
        $out .= self::CMD_CUT_PARTIAL;

        return $out;
    }

    /**
     * Build plain text shift report.
     */
    public function buildShiftReportText(PosShift $shift, string $type = 'Z', string $paperWidth = '80mm'): string
    {
        $cols = ($paperWidth === '58mm') ? self::WIDTH_58MM : self::WIDTH_80MM;
        $currency = 'AMD';

        $cashSales = (float) ($shift->total_cash_sales ?? $shift->cash_sales ?? 0);
        $cardSales = (float) ($shift->total_card_sales ?? $shift->card_sales ?? 0);
        $cashIn = (float) ($shift->total_cash_in ?? $shift->cash_paid_in ?? 0);
        $cashOut = (float) ($shift->total_cash_out ?? $shift->cash_paid_out ?? 0);
        $totalTips = (float) ($shift->total_tips ?? 0);
        $expectedCash = (float) ($shift->expected_cash ?? 0);
        $countedCash = $shift->counted_cash ?? $shift->actual_cash;
        $diff = (float) ($shift->discrepancy ?? $shift->cash_difference ?? 0);
        $totalOrders = $shift->orders_count ?? $shift->total_orders ?? 0;

        $lines = [];
        $lines[] = self::centerText($type === 'Z' ? 'END OF SHIFT REPORT (Z-REPORT)' : 'MID-SHIFT AUDIT (X-REPORT)', $cols);
        $lines[] = str_repeat('=', $cols);
        $lines[] = self::padBetween('Shift ID:', '#'.$shift->id, $cols);
        $lines[] = self::padBetween('Cashier:', $shift->user?->name ?? 'N/A', $cols);
        $lines[] = self::padBetween('Opened:', Carbon::parse($shift->opened_at)->format('Y-m-d H:i'), $cols);
        if ($shift->closed_at) {
            $lines[] = self::padBetween('Closed:', Carbon::parse($shift->closed_at)->format('Y-m-d H:i'), $cols);
        }
        $lines[] = str_repeat('-', $cols);
        $lines[] = self::padBetween('Opening Float:', number_format((float) $shift->opening_float, 2).' '.$currency, $cols);
        $lines[] = self::padBetween('Cash Sales:', number_format($cashSales, 2).' '.$currency, $cols);
        $lines[] = self::padBetween('Card Sales:', number_format($cardSales, 2).' '.$currency, $cols);
        $lines[] = self::padBetween('Cash In:', '+'.number_format($cashIn, 2).' '.$currency, $cols);
        $lines[] = self::padBetween('Cash Out:', '-'.number_format($cashOut, 2).' '.$currency, $cols);
        $lines[] = self::padBetween('Total Tips:', number_format($totalTips, 2).' '.$currency, $cols);
        $lines[] = str_repeat('=', $cols);
        $lines[] = self::padBetween('Expected Cash:', number_format($expectedCash, 2).' '.$currency, $cols);
        if ($countedCash !== null) {
            $lines[] = self::padBetween('Counted Cash:', number_format((float) $countedCash, 2).' '.$currency, $cols);
            $lines[] = self::padBetween('Difference:', ($diff >= 0 ? '+' : '').number_format($diff, 2).' '.$currency, $cols);
        }
        $lines[] = str_repeat('=', $cols);
        $lines[] = self::padBetween('Total Orders:', (string) $totalOrders, $cols);

        return implode("\n", $lines);
    }

    /**
     * Enqueue a print job for physical printer polling or background spooler.
     */
    public function queuePrintJob(
        Vendor $vendor,
        string $ticketType,
        string $escpos,
        string $text,
        ?Order $order = null,
        ?PosDevice $device = null,
        ?string $stationSlug = null,
        ?string $title = null
    ): PosPrintJob {
        return PosPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $vendor->id,
            'order_id' => $order?->id,
            'pos_device_id' => $device?->id,
            'station_slug' => $stationSlug,
            'ticket_type' => $ticketType,
            'title' => $title ?? ucfirst(str_replace('_', ' ', $ticketType)),
            'content_escpos' => base64_encode($escpos),
            'content_text' => $text,
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }

    /**
     * Safely dispatch print job to a network raw socket (IP:Port), catching network issues gracefully.
     */
    public function sendToNetworkPrinter(string $ip, int $port, string $rawBytes, int $timeoutSec = 3): bool
    {
        try {
            $fp = @fsockopen($ip, $port, $errno, $errstr, $timeoutSec);
            if (! $fp) {
                Log::warning("POS Printer connection failed to {$ip}:{$port} - Error: {$errstr} ({$errno})");

                return false;
            }

            stream_set_timeout($fp, $timeoutSec);
            fwrite($fp, $rawBytes);
            fflush($fp);
            fclose($fp);

            return true;
        } catch (\Throwable $e) {
            Log::error("POS Printer exception: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Build web-printable HTML customer receipt for browser window.print().
     */
    public function buildHtmlReceipt(Order $order, ?PosPayment $payment = null, ?Vendor $vendor = null): string
    {
        $textReceipt = $this->buildCustomerReceiptText($order, $payment, $vendor, '80mm');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{$order->order_number}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 1.25; margin: 0; padding: 12px; background: #fff; color: #000; }
        pre { white-space: pre-wrap; word-wrap: break-word; font-family: inherit; margin: 0; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 12px; text-align: center;">
        <button onclick="window.print()" style="padding: 6px 14px; font-weight: bold; cursor: pointer;">Print Receipt</button>
    </div>
    <pre>{$textReceipt}</pre>
</body>
</html>
HTML;
    }

    // --- Formatting Helpers ---

    protected static function divider(string $char, int $width): string
    {
        return str_repeat($char, $width);
    }

    protected static function centerText(string $text, int $width): string
    {
        $len = mb_strlen($text);
        if ($len >= $width) {
            return mb_substr($text, 0, $width);
        }
        $pad = (int) floor(($width - $len) / 2);

        return str_repeat(' ', $pad).$text;
    }

    protected static function padBetween(string $left, string $right, int $width): string
    {
        $leftLen = mb_strlen($left);
        $rightLen = mb_strlen($right);
        $spaces = $width - ($leftLen + $rightLen);

        if ($spaces < 1) {
            $spaces = 1;
        }

        return $left.str_repeat(' ', $spaces).$right;
    }
}
