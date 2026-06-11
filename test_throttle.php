<?php
// Crea un archivo llamado 'test_throttle.php' en la raíz y ejecútalo con: php test_throttle.php
$url = 'http://recaudacion.test/api/v1/consulta-deuda-rodaje-bancos';
$token = 'ouFqVbEMcfUOlJgcyczr0kYiTQSan6UFF2qRHK1HryWz9MF5Hwhu6XR9smk3'; // <--- Pon tu token aquí
$placa = 'XBA4552';

for ($i = 1; $i <= 65; $i++) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['placa' => $placa]));

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Petición #$i: Código de estado [$status]\n";

    if ($status === 429) {
        echo "\n¡ÉXITO! Se alcanzó el límite de solicitudes.\n";
        echo "RESPUESTA JSON:\n" . $response . "\n";
        break;
    }
}
