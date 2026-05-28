<?php
header('Content-Type: application/json');
$cars = require __DIR__ . '/../config/cars.php';
$result = [];
foreach ($cars as $id => $name) {
    $result[] = ['id' => $id, 'name' => $name];
}
echo json_encode($result);
