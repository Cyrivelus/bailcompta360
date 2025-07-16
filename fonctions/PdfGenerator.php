<?php
// fonctions/PdfGenerator.php (using an external tool)

/**
 * Generates a simple PDF from text content by creating an image and converting it.
 * WARNING: This method requires an external tool like ImageMagick (convert command)
 * or Ghostscript (gs command) to be installed on your server and accessible via PHP's exec().
 * This is NOT native PDF generation in PHP.
 *
 * @param string $content The text content to put in the PDF.
 * @param string $outputFilePath The full path where the PDF will be saved (e.g., 'path/to/document.pdf').
 * @return bool True on success, false on failure.
 */
function generatePdfFromTextViaExternalTool(string $content, string $outputFilePath): bool {
    // Step 1: Create a temporary image file from the text
    $tempImageFile = sys_get_temp_dir() . '/' . uniqid('pdf_temp_img_') . '.png';
    $fontSize = 20;
    $imageWidth = 800;
    $lineHeight = $fontSize * 1.5; // Estimate line height
    $padding = 20;

    // Calculate estimated image height based on content
    $lines = explode("\n", $content);
    $imageHeight = (count($lines) * $lineHeight) + (2 * $padding);

    // Create a blank image
    $image = imagecreatetruecolor($imageWidth, (int)$imageHeight);
    $backgroundColor = imagecolorallocate($image, 255, 255, 255); // White background
    $textColor = imagecolorallocate($image, 0, 0, 0); // Black text

    imagefill($image, 0, 0, $backgroundColor);

    // Set font path (replace with a real font path on your server, e.g., Arial.ttf)
    // For production, ensure you have a font file accessible.
    $font = 'C:\Windows\Fonts\Arial.ttf'; // Common path on Windows Server, adjust as needed

    if (!file_exists($font)) {
        error_log("Font file not found at: " . $font);
        // Fallback to a system font if possible or fail.
        // For simple text, you might use imageString if no font is available, but it's very basic.
        imagestring($image, 5, $padding, $padding, "Font not found. Displaying basic text:", $textColor);
        $y = $padding + 20; // Adjust for next line
    } else {
         $y = $padding;
    }


    // Write text to the image
    foreach ($lines as $line) {
        // imagettftext requires font, size, angle, x, y, color, fontfile, text
        if (file_exists($font)) {
             imagettftext($image, $fontSize, 0, $padding, $y + $fontSize, $textColor, $font, $line);
        } else {
             imagestring($image, 5, $padding, $y, $line, $textColor);
        }

        $y += $lineHeight;
    }

    imagepng($image, $tempImageFile);
    imagedestroy($image);

    // Step 2: Convert the image to PDF using an external command
    // You would need ImageMagick's `convert` command installed on your Windows Server.
    // Download ImageMagick: https://imagemagick.org/script/download.php
    // Ensure its bin directory is in your system's PATH or provide the full path to convert.exe.
    $command = "\"C:\\Program Files\\ImageMagick-7.1.1-Q16\\convert.exe\" \"{$tempImageFile}\" \"{$outputFilePath}\""; // Adjust path to convert.exe

    // For security, carefully sanitize inputs if they come from user data.
    // For this example, we assume $content is controlled.
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);

    // Clean up the temporary image file
    unlink($tempImageFile);

    if ($returnVar === 0) {
        logActivity("PDF généré via outil externe: {$outputFilePath}");
        return true;
    } else {
        logError("Échec de la génération du PDF via outil externe. Commande: {$command}. Erreur: " . implode("\n", $output));
        return false;
    }
}

// Helper for logging errors (assuming gestion_logs.php has logApplicationError)
function logError(string $message) {
    if (function_exists('logApplicationError')) {
        logApplicationError($message);
    } else {
        error_log($message);
    }
}

// Helper for logging activity (assuming gestion_logs.php has logUserActivity)
function logActivity(string $message) {
    if (function_exists('logUserActivity')) {
        logUserActivity($message);
    } else {
        error_log($message);
    }
}
?>