<?php
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="receipt.pdf"');

require_once '../_init.php';

// Get order_id from POST or GET
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : (isset($_GET['order_id']) ? intval($_GET['order_id']) : 0);

if (!$order_id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing order_id']);
    exit;
}

try {
    global $connection;

    // Fetch order (including payment details)
    $stmt = $connection->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    // Fetch order items with product names
    $stmt = $connection->prepare('
        SELECT 
            order_items.id,
            order_items.order_id,
            order_items.product_id,
            order_items.quantity,
            order_items.price,
            order_items.size,
            products.name as product_name
        FROM order_items
        INNER JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = ?
    ');
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate receipt as formatted lines
    $lines = [];
    $lines[] = '=========================================';
    $lines[] = '           ALEX ESSENTIAL';
    $lines[] = '';
    $lines[] = '        Bansalan Public Market';
    $lines[] = '         Tel: (012) 345-6789';
    $lines[] = '=========================================';
    $lines[] = '';
    $lines[] = 'Receipt #: ' . str_pad($order_id, 8, '0', STR_PAD_LEFT);
    $lines[] = 'Date: ' . date('m/d/Y h:i A', strtotime($order['created_at']));
    // Determine cashier name if available
    $cashierName = 'User';
    if (!empty($order['cashier_name'])) {
        $cashierName = $order['cashier_name'];
    } elseif (!empty($order['user_id'])) {
        try {
            $uStmt = $connection->prepare('SELECT name FROM users WHERE id = ?');
            $uStmt->execute([intval($order['user_id'])]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow && !empty($uRow['name'])) $cashierName = $uRow['name'];
        } catch (Exception $e) {
            // ignore, keep default
        }
    }
    $lines[] = 'Cashier: ' . $cashierName;
    $lines[] = '';
    $lines[] = '-----------------------------------------';
    // Build header with same column widths used for items
    $hdrNameCol = str_pad('ITEM', 22);
    $hdr = $hdrNameCol . str_pad('QTY', 3, ' ', STR_PAD_LEFT) . '  ' . str_pad('PRICE', 7, ' ', STR_PAD_LEFT) . ' ' . str_pad('AMT', 8, ' ', STR_PAD_LEFT);
    $lines[] = $hdr;
    $lines[] = '-----------------------------------------';

    $subtotal = 0;
    // Characters reserved for product name column
    // Keep name column small enough to fit the receipt width (approx 46 chars total)
    $nameColumnWidth = 22; // characters
    foreach ($items as $item) {
        // Build full product name (including size)
        $name = $item['product_name'];
        if (!empty($item['size'])) {
            $name .= ' (Size: ' . $item['size'] . ')';
        }
        $quantity = intval($item['quantity']);
        $price = floatval($item['price']);
        $lineTotal = $quantity * $price;
        $subtotal += $lineTotal;

        // Wrap long product names into chunks for the name column using word boundaries
        // Normalize whitespace
        $fullName = preg_replace('/\\s+/', ' ', trim($name));
        // Use wordwrap then explode so we avoid splitting words mid-way where possible
        $nameChunks = explode("\n", wordwrap($fullName, $nameColumnWidth, "\n", true));

        // Prepare right-side fields (fixed widths)
        $qtyStr = str_pad(strval($quantity), 3, ' ', STR_PAD_LEFT); // 3 chars
        $priceStr = str_pad(number_format($price, 2), 7, ' ', STR_PAD_LEFT); // 7 chars
        $amtStr = str_pad(number_format($lineTotal, 2), 8, ' ', STR_PAD_LEFT); // 8 chars

        // First line includes qty/price/amt
        $firstChunk = array_shift($nameChunks);
        $firstChunkPadded = str_pad($firstChunk, $nameColumnWidth);
        $lines[] = $firstChunkPadded . $qtyStr . '  ' . $priceStr . ' ' . $amtStr;

        // Remaining chunks are printed on their own lines (name continuation)
        foreach ($nameChunks as $chunk) {
            $lines[] = str_pad($chunk, $nameColumnWidth);
        }
    }

    $lines[] = '-----------------------------------------';
    $lines[] = '';

    // Assume prices are VAT-inclusive at 12%
    $VAT_RATE = 0.12;
    $vatPortion = $subtotal * ($VAT_RATE / (1 + $VAT_RATE));
    $netOfVat = $subtotal - $vatPortion;

    // Use consistent total/label width so amounts align and don't overflow
    $numericCols = 3 + 2 + 7 + 1 + 8; // qty(3) + spaces(2) + price(7) + space(1) + amt(8)
    $totalLineWidth = $nameColumnWidth + $numericCols;

    $netStr = number_format($netOfVat, 2);
    $vatStr = number_format($vatPortion, 2);
    $totalStr = number_format($subtotal, 2);

    $lines[] = str_pad('SUBTOTAL', $totalLineWidth - strlen($netStr)) . str_pad($netStr, strlen($netStr), ' ', STR_PAD_LEFT);
    $lines[] = str_pad('VAT (12%)', $totalLineWidth - strlen($vatStr)) . str_pad($vatStr, strlen($vatStr), ' ', STR_PAD_LEFT);
    $lines[] = '=========================================';
    $lines[] = str_pad('TOTAL', $totalLineWidth - strlen($totalStr)) . str_pad($totalStr, strlen($totalStr), ' ', STR_PAD_LEFT);
    $lines[] = '=========================================';

    // Payment details (if stored on order)
    $paymentAmount = isset($order['payment_amount']) ? floatval($order['payment_amount']) : null;
    $changeAmount = isset($order['change_amount']) ? floatval($order['change_amount']) : null;
    $paymentMethod = isset($order['payment_method']) && $order['payment_method'] ? strtoupper($order['payment_method']) : 'CASH';

    if ($paymentAmount !== null) {
        $lines[] = '';
        $payStr = number_format($paymentAmount, 2);
        $lines[] = str_pad('Paid (' . $paymentMethod . ')', $totalLineWidth - strlen($payStr)) . str_pad($payStr, strlen($payStr), ' ', STR_PAD_LEFT);
        if ($changeAmount !== null && $changeAmount > 0) {
            $chgStr = number_format($changeAmount, 2);
            $lines[] = str_pad('Change', $totalLineWidth - strlen($chgStr)) . str_pad($chgStr, strlen($chgStr), ' ', STR_PAD_LEFT);
        }
    }
    $lines[] = '';
    $lines[] = '=========================================';
    $lines[] = '     Thank you for your purchase!';
    $lines[] = '         Please come again soon';
    $lines[] = '=========================================';
    $lines[] = '';

    // Generate and output PDF
    $pdf_content = generateSimplePDF($lines);
    echo $pdf_content;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

/**
 * Generate a valid PDF in thermal receipt format (80mm width).
 */
function generateSimplePDF($lines) {
    $objects = [];
    
    // Object 1: Catalog
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    
    // Object 2: Pages
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    
    // Thermal receipt: 80mm width = ~227 points, height auto
    // Object 3: Page (80mm x variable height thermal format)
    // Increase height to reduce chance of crowding/overlap on longer receipts
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 227 800] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
    
    // Object 4: Content stream (text)
    $text_content = buildTextContent($lines, 227);
    $objects[4] = "<< /Length " . strlen($text_content) . " >>\nstream\n" . $text_content . "\nendstream";
    
    // Object 5: Font definition
    $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
    
    // Build PDF with correct byte offsets
    $pdf = "%PDF-1.4\n%âãÏÓ\n";
    $offsets = [];
    
    foreach ($objects as $num => $obj) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "$num 0 obj\n" . $obj . "\nendobj\n";
    }
    
    // Xref table
    $xref_offset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    
    // Trailer
    $pdf .= "trailer\n";
    $pdf .= "<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref_offset . "\n";
    $pdf .= "%%EOF";
    
    return $pdf;
}

/**
 * Build the PDF text content stream with smaller font for thermal receipt.
 */
function buildTextContent($lines, $pageWidth) {
    $content = "BT\n";
    // Use slightly smaller font and increased line spacing to avoid overlap
    $content .= "/F1 8 Tf\n";  // 8pt for tighter fit
    $content .= "10 780 Td\n"; // Start near top (with increased page height)
    $content .= "1 TL\n"; // set text leading operator (leading = 1: used with Td adjustments)

    foreach ($lines as $line) {
        // Escape backslashes and parentheses in PDF strings
        $line = str_replace('\\', '\\\\', $line);
        $line = str_replace('(', '\\(', $line);
        $line = str_replace(')', '\\)', $line);
        $content .= "(" . $line . ") Tj\n";
        $content .= "0 -12 Td\n"; // increase vertical spacing to avoid overlapping
    }
    
    $content .= "ET\n";
    return $content;
}



