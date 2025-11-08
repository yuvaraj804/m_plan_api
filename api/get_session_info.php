<?php
header("Content-Type: application/json");
session_start(); 

// generate random reference number
function generateRefNumber() {
    $year = date("Y");
    $randomNum = rand(1000, 9999);
    return "IT-{$year}-{$randomNum}";
}
$_SESSION['guser_name'] ='Minerva';
$_SESSION['guser_id'] =2;
$_SESSION['csrf_token']='12345';
$userId = $_SESSION['guser_id'] ?? 2;
$userName = $_SESSION['guser_name'] ?? 'Minerva';
// $token =$_SESSION['csrf_token'];
$data = [
    "success"   => true,
    "ref_no"    => generateRefNumber(),
    "user_id"   => $userId,
    "user_name" => $userName,
    // "token" => $token,
    "datetime"  => date("Y-m-d H:i:s")
];

echo json_encode($data);
