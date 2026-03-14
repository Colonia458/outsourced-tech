<?php
/**
 * Batch Product Image Downloader
 * 
 * This script downloads product images from manufacturer websites
 * and saves them to assets/images/products/
 * 
 * Usage: php download-images.php
 * 
 * IMPORTANT: Run this from the web root directory
 */

echo "=======================================\n";
echo "Product Image Batch Downloader\n";
echo "=======================================\n\n";

// Configuration
$download_dir = __DIR__ . '/assets/images/products/';
$log_file = __DIR__ . '/logs/image-downloads.log';

// Ensure download directory exists
if (!is_dir($download_dir)) {
    mkdir($download_dir, 0755, true);
    echo "Created directory: $download_dir\n";
}

// Product image URLs (SKU => URL mapping)
$products = [
    'LAP-DELL-001' => 'https://i.dell.com/is/image/DellContent/content/dam/ss2/product-images/dell-client-products/notebooks/dell-premium/da14250/media-gallery/platinum/touch/notebook-dell-premium-da14250t-sl-gallery-1.psd?fmt=png-alpha&pscan=auto&scl=1&hei=804&wid=1086&qlt=100,1&resMode=sharp2&size=1086,804&chrss=full',
    'LAP-HP-001' => 'https://m.media-amazon.com/images/I/717dbOxYc8L.jpg',
    'LAP-ASUS-001' => 'https://dlcdnwebimgs.asus.com/files/media/5e2878d7-7466-4222-9cb7-53e6c0e57781/v1/features/images/large/1x/s10/main.jpg',
    'LAP-MAC-001' => 'https://www.apple.com/newsroom/images/product/mac/standard/Apple-WWDC22-MacBook-Air-hero-220606_big.jpg.large.jpg',
    'LAP-LEN-001' => 'https://p2-ofp.static.pub//fes/cms/2025/03/17/d9q3a69ghlfo581hy7joxx04e82vaf476855.jpg',
    'LAP-DELL-002' => 'https://m.media-amazon.com/images/I/61QF9GA62XL.jpg',
    'NET-CISC-001' => 'https://www.cisco.com/c/dam/assets/support/product-images/series/series-switches-catalyst-2960-x-series-switches-alternate1.jpg',
    'NET-TP-001' => 'https://static.tp-link.com/TL-SG1008D(UN)8.0-01_1499931146312v.jpg',
    'NET-TP-002' => 'https://m.media-amazon.com/images/I/51RoyFl5dEL._AC_UF894,1000_QL80_.jpg',
    'NET-UBI-001' => 'https://cdn.ecomm.ui.com/products/259686b4-ae75-411c-90bc-e4040e38ca56/8ddcf98f-1c0c-48da-87c3-7cf0447ff8bd.png',
    'NET-MIK-001' => 'https://cdn.mikrotik.com/web-assets/rb_images/1975_lg.webp',
    'NET-TP-003' => 'https://static.tp-link.com/TL-SG1016D_UN_7.0_01_1499779718727n.jpg',
    'NET-DLI-001' => 'https://www.dlink.com/-/media/Product%20Pages/DGS/1210/Comparison/DGS121028F1Front.png',
    'NET-CISC-002' => 'https://m.media-amazon.com/images/I/71gZFk6ydAL.jpg',
    'ACC-MOU-001' => 'https://resource.logitech.com/w_800,c_lpad,ar_4:3,q_auto,f_auto,dpr_1.0/d_transparent.gif/content/dam/logitech/en/products/mice/mx-master-3s-business-wireless-mouse/gallery/mx-master-3s-for-business-gallery-1.png?v=1',
    'ACC-MOU-002' => 'https://hp.widen.net/content/oa33on20zn/png/oa33on20zn.png?w=800&h=600&dpi=72&color=ffffff00',
    'ACC-KB-001' => 'https://cdn.mos.cms.futurecdn.net/F263WYuhRnN8NUjcyDokTJ-2000-80.jpg',
    'ACC-KB-002' => 'https://m.media-amazon.com/images/I/81t0A4G-3PL.jpg',
    'ACC-CAB-001' => 'https://m.media-amazon.com/images/I/510PRMkCZAL._AC_UF1000,1000_QL80_.jpg',
    'ACC-CAB-002' => 'https://i.ebayimg.com/00/s/NTQ5WDcwMA==/z/FMQAAOSwZphhhzk0/$_10.JPG?set_id=8800005007',
    'ACC-CHG-001' => 'https://m.media-amazon.com/images/I/6118HOJajHL._AC_UF894,1000_QL80_.jpg',
    'ACC-CHG-002' => 'https://m.media-amazon.com/images/I/61bHDVGcdAL._AC_UF894,1000_QL80_.jpg',
    'ACC-HUB-001' => 'https://m.media-amazon.com/images/I/61k4+DrQ08L.jpg',
    'ACC-HUB-002' => 'https://m.media-amazon.com/images/I/51+U60trRlL.jpg',
    'ACC-HEA-001' => 'https://www.apple.com/newsroom/images/product/audio/standard/Apple_beats-studio-pro-black-3up_071823.jpg.large.jpg',
    'ACC-WEB-001' => 'https://us.maxgaming.com/bilder/artiklar/7797.jpg?m=1588925877',
    'PHN-APP-001' => 'https://www.apple.com/newsroom/images/product/iphone/standard/Apple-iPhone-14-iPhone-14-Plus-hero-220907_Full-Bleed-Image.jpg.large.jpg',
    'PHN-SAM-001' => 'https://image-us.samsung.com/us/smartphones/galaxy-s23-ultra/images/galaxy-s23-ultra-highlights-kv.jpg',
    'PHN-RED-001' => 'https://i02.appmifile.com/2_operator_sg/06/03/2023/e1747d55b1a25c610053b35956c2bfa3.png',
    'TAB-APP-001' => 'https://cdsassets.apple.com/live/SZLF0YNV/images/sp/111840_sp884-ipad-10gen-960.png',
    'TAB-SAM-001' => 'https://img.global.news.samsung.com/in/wp-content/uploads/2022/01/10516_Galaxy-Tab-A8-LFD_1920x1080-1.jpg',
    'STR-SSD-001' => 'https://images.samsung.com/is/image/samsung/p6pim/us/mz-v8p1t0b-am/gallery/us-980-pro-nvme-m2-ssd-mz-v8p1t0b-am-550536602.jpg',
    'STR-SSD-002' => 'https://webobjects2.cdw.com/is/image/CDW/4627400?wid=784&hei=477&resMode=bilin&fit=fit,1',
    'STR-HDD-001' => 'https://www.disctech.com/SCASite/product_images/WD10EZEX_1000-1.jpg',
    'STR-USB-001' => 'https://service.pcconnection.com/images/inhouse/994AD2D2-E09A-4B94-9745-DC082119367D.jpg',
    'STR-USB-002' => 'https://service.pcconnection.com/images/inhouse/92BA3E07-88B7-42BA-9F3F-F2BD03F07CA0.jpg',
    'STR-SDC-001' => 'https://service.pcconnection.com/images/inhouse/D652B646-B551-4691-80CE-0992322ADB7B.jpg',
    'DES-DELL-001' => 'https://cdn.cs.1worldsync.com/29/50/2950e949-a0ce-4028-809d-4486d4dbdb84.jpg',
    'DES-HP-001' => 'https://cdn.cs.1worldsync.com/f3/61/f3615c55-5060-4b76-aace-7336d7ce6c2a.jpg',
    'PRT-HP-001' => 'https://apiv2.exceldisc.com/media/40692/hp-laserjet-pro-mfp-4103dw-printer-a4-print-copy-scan-42-ppm-1200-x-1200-dpi-resolution-80000-pages-duty-cycle.png',
    'PRT-CAN-001' => 'https://bcscomputer.net/userfiles/product/1024x768/0002adb4e36e62339c1b5a7e24b087b0.jpg',
    'PRT-EP-001' => 'https://mediaserver.goepson.com/ImConvServlet/imconv/daa964d607720c9407027bcc4db455594a5477b6/1200Wx1200H?use=banner&hybrisId=B2C&assetDescr=EcoTank-L3250-690x460-1',
];

echo "Total products to download: " . count($products) . "\n\n";

$success_count = 0;
$fail_count = 0;
$failed_downloads = [];

// Set timeout
ini_set('max_execution_time', 300);
ini_set('default_socket_timeout', 30);

foreach ($products as $sku => $url) {
    echo "Downloading [$sku]... ";
    
    // Determine file extension from URL or default to jpg
    $extension = 'jpg';
    if (preg_match('/\.(png|jpg|jpeg|gif|webp)(\?|$)/i', $url, $matches)) {
        $extension = strtolower($matches[1]);
        if ($extension === 'jpeg') $extension = 'jpg';
    }
    
    $filename = strtolower(str_replace(' ', '-', $sku)) . '.' . $extension;
    $filepath = $download_dir . $filename;
    
    // Download the image
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200 && !empty($image_data)) {
        // Check if it's actually an image
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_buffer($finfo, $image_data);
        finfo_close($finfo);
        
        if (strpos($mime_type, 'image/') === 0) {
            file_put_contents($filepath, $image_data);
            echo "OK (saved as $filename, " . strlen($image_data) . " bytes)\n";
            $success_count++;
        } else {
            echo "FAILED (not an image, got: $mime_type)\n";
            $failed_downloads[$sku] = $url;
            $fail_count++;
        }
    } else {
        echo "FAILED (HTTP $http_code" . ($error ? ", $error" : "") . ")\n";
        $failed_downloads[$sku] = $url;
        $fail_count++;
    }
}

echo "\n=======================================\n";
echo "Download Summary\n";
echo "=======================================\n";
echo "Successful: $success_count\n";
echo "Failed: $fail_count\n";
echo "Download directory: $download_dir\n\n";

if (!empty($failed_downloads)) {
    echo "Failed Downloads:\n";
    foreach ($failed_downloads as $sku => $url) {
        echo "  - $sku: $url\n";
    }
    echo "\nYou may need to download these manually.\n";
}

echo "\nNext steps:\n";
echo "1. Verify the downloaded images in $download_dir\n";
echo "2. Run the database import script (if created) to add images to database\n";
