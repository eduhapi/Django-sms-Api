<?php
// Function to get the current date in EAT and generate the log file name
function getLogFileName() {
    $timezone = new DateTimeZone('Africa/Nairobi'); // EAT time zone
    $date = new DateTime('now', $timezone);
    return 'mifos_payments_LogFile_' . $date->format('d_m_Y') . '.txt';
}

// Define the log file name based on the current date in EAT
$mifosresfile = getLogFileName();

// Function to update Mifos X and send acknowledgment
function updateMifosX($jsonData, $rawData) {
    global $mifosresfile;

    $transactionDate = date("d M Y", strtotime($jsonData['TransTime'])); // Fixed date format
    $amount = $jsonData['TransAmount'];
    $accountReference = $jsonData['BillRefNumber'];

    // Log the raw data
    file_put_contents($mifosresfile, "Raw Data: " . $rawData . "\n--------------------------\n", FILE_APPEND | LOCK_EX);

    // Validate account reference; it should contain only digits
    if (empty($accountReference) || !preg_match('/^\d+$/', $accountReference)) {
        file_put_contents($mifosresfile, "Failed to provide a valid account for this transaction sent from Mobile: " . $jsonData['MSISDN'] . "\n--------------------------\n", FILE_APPEND | LOCK_EX);
        return;
    }

    // Mifos X payment API endpoint
    $mifosUrl = "https://13.212.166.148/fineract-provider/api/v1/savingsaccounts/$accountReference/transactions?command=deposit";

    // Prepare the payment data dynamically
    $paymentData = array(
        "locale" => "en",
        "dateFormat" => "dd MMMM yyyy", // Corrected date format
        "transactionDate" => $transactionDate,
        "transactionAmount" => $amount,
        "paymentTypeId" => 1, // Adjust based on actual payment type ID mapping
        "accountNumber" => $accountReference, // Assuming account number maps to account reference
        "checkNumber" => $jsonData['TransID'], // Using transaction ID as check number for reference
        "routingCode" => "rou124", // Provide appropriate routing code if applicable
        "receiptNumber" => "rec124", // Provide appropriate receipt number if applicable
        "bankNumber" => "ban124" // Provide appropriate bank number if applicable
    );

    // Encode the payment data to JSON
    $paymentDataJson = json_encode($paymentData);

    // Prepare the HTTP headers
    $headers = array(
        "Content-Type: application/json",
        "Fineract-Platform-TenantId: default",
        "Authorization: Basic " . base64_encode("mifos:Bigman2024") // Change this to your actual credentials
    );

    // Initialize cURL for deposit transaction
    $ch = curl_init($mifosUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true); // Setting this to POST request
    curl_setopt($ch, CURLOPT_POSTFIELDS, $paymentDataJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL certificate verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Ignore SSL hostname verification

    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Log the transaction request and response
    file_put_contents($mifosresfile, "Request: " . $paymentDataJson . "\nResponse: " . $response . "\n--------------------------\n", FILE_APPEND | LOCK_EX);

    // If transaction was successful, fetch additional details and send acknowledgment
    if ($httpCode == 200) {
        // Fetch additional details from Mifos X
        // Here we will use the account number to run the API to get client details
        $accountNumber = $accountReference; // Assuming account number and account reference are the same
        $mifosClientUrl = "https://13.212.166.148/fineract-provider/api/v1/clients/{$accountNumber}";

        // Initialize cURL for fetching client details
        $clientCh = curl_init($mifosClientUrl);
        curl_setopt($clientCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($clientCh, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Fineract-Platform-TenantId: default",
            "Authorization: Basic " . base64_encode("mifos:Bigman2024") // Change this to your actual credentials
        ));
        curl_setopt($clientCh, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL certificate verification
        curl_setopt($clientCh, CURLOPT_SSL_VERIFYHOST, false); // Ignore SSL hostname verification

        // Execute the request to fetch client details
        $clientResponse = curl_exec($clientCh);
        $clientHttpCode = curl_getinfo($clientCh, CURLINFO_HTTP_CODE);
        $clientError = curl_error($clientCh);
        curl_close($clientCh);

        // If client details are fetched successfully
        if ($clientHttpCode == 200) {
            $clientData = json_decode($clientResponse, true);
            // Extract client details
            $firstname = $clientData['firstname'];
            $lastname = $clientData['lastname'];
            $mobileNo = $clientData['mobileNo'];
        } else {
            // Log error if unable to fetch client details
            file_put_contents($mifosresfile, "Failed to fetch client details: HTTP Code - " . $clientHttpCode . "\nResponse: " . $clientResponse . "\nError: " . $clientError . "\n--------------------------\n", FILE_APPEND | LOCK_EX);
            // You may handle this error condition as per your requirement
            return;
        }

        // Extract resourceId from Mifos X response
        $mifosXResponse = json_decode($response, true);
        $resourceId = $mifosXResponse['resourceId'];

        // Prepare acknowledgment data
        $ackData = array(
            "paymentDate" => $transactionDate,
            "paidAmount" => $amount,
            "accountReference" => $accountReference,
            "transactionId" => $jsonData['TransID'],
            "phoneNumber" => $mobileNo,
            "fullName" => $firstname . " " . $lastname,
            "invoiceName" => "Savings deposit",
            "externalReference" => $resourceId
        );

        // Encode acknowledgment data to JSON
        $ackDataJson = json_encode($ackData);

        // Send acknowledgment to the endpoint
        $ackEndpoint = "https://app.eastakiba.co.ke/ack";
        $ackHeaders = array(
            "Content-Type: application/json"
        );

        $ackCh = curl_init($ackEndpoint);
        curl_setopt($ackCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ackCh, CURLOPT_POST, true);
        curl_setopt($ackCh, CURLOPT_POSTFIELDS, $ackDataJson);
        curl_setopt($ackCh, CURLOPT_HTTPHEADER, $ackHeaders);

        // Execute the acknowledgment request
        $ackResponse = curl_exec($ackCh);
        $ackHttpCode = curl_getinfo($ackCh, CURLINFO_HTTP_CODE);
        $ackError = curl_error($ackCh);
        curl_close($ackCh);

        // Log the acknowledgment response or error
        if ($ackHttpCode == 200) {
            file_put_contents($mifosresfile, "Acknowledgment sent successfully: " . $ackResponse . "\n--------------------------\n", FILE_APPEND | LOCK_EX);
        } else {
            file_put_contents($mifosresfile, "Failed to send acknowledgment: " . $ackError . "\nHTTP Code: " . $ackHttpCode . "\nResponse: " . $ackResponse . "\n--------------------------\n", FILE_APPEND | LOCK_EX);
        }

        // Return acknowledgment data as response to Postman
        header('Content-Type: application/json');
        echo $ackDataJson;
    } else {
        // Log the error if the transaction failed
        file_put_contents($mifosresfile, "Mifos X Error: " . $error . "\nHTTP Code: " . $httpCode . "\nResponse: " . $response . "\n--------------------------\n", FILE_APPEND | LOCK_EX);
        
        // Return error response as JSON
        header('Content-Type: application/json');
        echo json_encode(array("resmsg" => "Failed to process transaction", "rescode" => $httpCode));
    }
}

// Get the raw POST data
$postData = file_get_contents('php://input');

// Check if we received any data
if ($postData) {
    // Decode the JSON data
    $jsonData = json_decode($postData, true);

    // Call the function to update Mifos X and send acknowledgment
    updateMifosX($jsonData, $postData);
} else {
    // Respond with an error message if no data received
    header('Content-Type: application/json');
    echo json_encode(array("resmsg" => "No data received", "rescode" => "400"));
}
?>
