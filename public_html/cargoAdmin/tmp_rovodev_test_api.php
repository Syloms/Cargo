<?php
/**
 * Test the get_claim_details API directly
 */

// Get claim ID from URL
$claimId = isset($_GET['id']) ? intval($_GET['id']) : 1;

// Call the API
$apiUrl = "https://cargoph.online/cargoAdmin/api/insurance/admin/get_claim_details.php?id=$claimId";

echo "<h2>Testing API: get_claim_details.php</h2>";
echo "<p><strong>API URL:</strong> <a href='$apiUrl' target='_blank'>$apiUrl</a></p>";
echo "<hr>";

$response = file_get_contents($apiUrl);

echo "<h3>Raw API Response:</h3>";
echo "<textarea style='width:100%; height:400px;'>$response</textarea>";
echo "<hr>";

$data = json_decode($response, true);

echo "<h3>Parsed JSON:</h3>";
echo "<pre>";
print_r($data);
echo "</pre>";

if (isset($data['success']) && $data['success']) {
    echo "<hr><h3>Evidence Photos Check:</h3>";
    
    if (isset($data['data']['evidence_photos'])) {
        echo "<p><strong>Evidence Photos Found:</strong> " . count($data['data']['evidence_photos']) . " photos</p>";
        echo "<pre>";
        print_r($data['data']['evidence_photos']);
        echo "</pre>";
        
        if (count($data['data']['evidence_photos']) > 0) {
            echo "<h4>Photo Preview:</h4>";
            foreach ($data['data']['evidence_photos'] as $index => $photo) {
                echo "<div style='margin:10px; border:1px solid #ccc; padding:10px;'>";
                echo "<p><strong>Photo " . ($index + 1) . ":</strong> $photo</p>";
                echo "<img src='$photo' style='max-width:300px; max-height:200px;' onerror=\"this.style.border='2px solid red'; this.alt='Image not found'\">";
                echo "</div>";
            }
        }
    } else {
        echo "<p style='color:red;'>❌ evidence_photos field NOT in API response</p>";
    }
    
    echo "<hr><h3>Claimant Contact:</h3>";
    echo "<p><strong>Contact:</strong> " . ($data['data']['claimant']['contact'] ?? 'NULL/Empty') . "</p>";
    
    echo "<hr><h3>Approved Amount:</h3>";
    echo "<p><strong>Approved Amount:</strong> ₱" . number_format($data['data']['approved_amount'] ?? 0, 2) . "</p>";
}

?>

<hr>
<form method="get">
    <label>Test with Claim ID: </label>
    <input type="number" name="id" value="<?php echo $claimId; ?>" min="1">
    <button type="submit">Test</button>
</form>
