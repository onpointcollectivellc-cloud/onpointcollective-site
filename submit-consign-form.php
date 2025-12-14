<?php
// submit-consign-form.php

// --- 1. CONFIGURATION: Update these variables with your actual details ---
$receiving_email = 'onpointcollectivellc@gmail.com'; // CRITICAL: Your Brokerage Email
$admin_email = 'webmaster@onpointcollectivellc.art'; // CRITICAL: Your website's sender email
$upload_directory = 'secure_uploads/'; // CRITICAL: Folder you must create on your server
$minimum_price = 10000;
$success_redirect = 'confirmation.html';

// --- 2. SECURITY & INITIAL CHECKS ---
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // Redirect if accessed directly
    header("Location: index.html");
    exit;
}

// Generate a Unique Tracking ID
$tracking_id = 'OPC-' . date('Ymd') . '-' . substr(time(), -5);

// Create upload directory if it doesn't exist
if (!is_dir($upload_directory)) {
    // Attempt to create directory with permissions 755
    mkdir($upload_directory, 0755, true);
}

$errors = [];
$uploaded_files = [];
$consignor_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);


// --- 3. DATA VALIDATION ---

// Required Field Checks
$required_fields = ['artworkTitle', 'artistName', 'email', 'desiredPrice', 'mediumMaterials', 'currentLocation', 'agreement'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $errors[] = ucfirst($field) . " is required.";
    }
}

// Price Floor Check ($10K+)
$desiredPrice = isset($_POST['desiredPrice']) ? floatval($_POST['desiredPrice']) : 0;
if ($desiredPrice < $minimum_price) {
    $errors[] = "Desired Selling Price must be at least $" . number_format($minimum_price);
}


// --- 4. HANDLE FILE UPLOADS (We focus on the Primary Image for essential functionality) ---

if (empty($errors)) {
    $file_field = 'primaryImage';
    if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$file_field]['tmp_name'];
        
        // Sanitize and create a unique file name: [TrackingID]_[ArtistName]_[Type].[Ext]
        $file_extension = pathinfo($_FILES[$file_field]['name'], PATHINFO_EXTENSION);
        $safe_artist_name = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $_POST['artistName']));
        $new_file_name = $tracking_id . '_' . $safe_artist_name . '_PRIMARY.' . $file_extension;
        $destination = $upload_directory . $new_file_name;

        // Move the file from the temporary location to your secure folder
        if (move_uploaded_file($file_tmp_name, $destination)) {
            $uploaded_files[] = 'Primary Image: ' . $destination;
        } else {
            $errors[] = "Failed to move primary image to the secure directory.";
        }
    } else {
         $errors[] = "Primary Image upload failed or was missing.";
    }
}

// --- 5. PROCESS RESULTS AND NOTIFY BROKERAGE ---

if (!empty($errors)) {
    // If errors exist, stop and show the error to the user
    echo "<h1>Submission Error!</h1>";
    echo "<p>Your submission failed due to the following reasons:</p><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul><p>Please use your browser's back button to correct the form.</p>";
    exit;
} else {
    // SUCCESS: Construct and send the internal notification email

    $subject = "ASSET INTAKE | ID: " . $tracking_id . " | " . $_POST['artworkTitle'] . " by " . $_POST['artistName'];
    
    // Format the email body using the collected data
    $email_body = "A new conceptual art asset has been submitted (ID: " . $tracking_id . ").\n\n";
    $email_body .= "-----------------------------------\n";
    $email_body .= "ASSET VALUE & DETAILS:\n";
    $email_body .= "Desired Price: $" . number_format($desiredPrice) . "\n";
    $email_body .= "Artist: " . $_POST['artistName'] . "\n";
    $email_body .= "Title: " . $_POST['artworkTitle'] . "\n";
    $email_body .= "Medium: " . $_POST['mediumMaterials'] . "\n";
    $email_body .= "COA Status: " . $_POST['coaStatus'] . "\n";
    $email_body .= "File Location on Server: " . $uploaded_files[0] . "\n\n";
    
    $email_body .= "CONSIGNOR CONTACT:\n";
    $email_body .= "Name: " . $_POST['consignorName'] . "\n";
    $email_body .= "Email: " . $consignor_email . "\n";
    $email_body .= "Phone: " . (isset($_POST['phone']) ? $_POST['phone'] : 'N/A') . "\n";
    $email_body .= "Discretionary Notes:\n" . $_POST['discretionaryNotes'] . "\n";
    $email_body .= "-----------------------------------\n";

    // Headers for reliable email delivery
    $headers = "From: " . $admin_email . "\r\n";
    $headers .= "Reply-To: " . $consignor_email . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Send the email to the brokerage
    mail($receiving_email, $subject, $email_body, $headers);

    
    // 6. REDIRECT USER TO SUCCESS PAGE
    header('Location: ' . $success_redirect);
    exit;
}

?>
