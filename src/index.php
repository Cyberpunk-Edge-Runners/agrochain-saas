<?php
require_once "db.php";

try {
    $stmt = $pdo->query("SELECT * FROM tenants");
    $tenants = $stmt->fetchAll();
} catch (PDOException $e){
    die("Error loadin page data, $e");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroChain SaaS Test</title>
</head>
<body class="bg-slate-100 min-h-screen p-8 text-slate-800">

    <div class="max-w-md mx-auto bg-white rounded-xl shadow-md p-6 mt-10">
        <h1 class="text-2xl font-bold text-emerald-600 mb-4">🌾 AgroChain Local Test</h1>
        <p class="text-green-600 font-semibold mb-6">✅ Successfully connected to MySQL!</p>

        <h2 class="text-lg font-medium text-slate-700 mb-3">SaaS Tenants Found:</h2>
        <ul class="space-y-3">
            <?php 
            // 3. Loop through each tenant and output their name and subdomain
            foreach ($tenants as $tenant) {
                echo '<li class="bg-slate-50 p-4 rounded-lg border border-slate-200 flex justify-between items-center">';
                echo '  <span class="font-bold text-slate-800">' . htmlspecialchars($tenant['name']) . '</span>';
                echo '  <span class="text-sm bg-emerald-100 text-emerald-800 py-1 px-2 rounded">' . htmlspecialchars($tenant['subdomain']) . '</span>';
                echo '</li>';
            }
            ?>
        </ul>
    </div>
</html>