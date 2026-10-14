<?php
require 'd:/KSR GURU JI/includes/db.php';
$pdo = getDBConnection();
echo "Patients: " . $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn() . "\n";
echo "Doctors: " . $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn() . "\n";
echo "Appointments: " . $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn() . "\n";
echo "Consultations: " . $pdo->query("SELECT COUNT(*) FROM consultations")->fetchColumn() . "\n";
echo "Prescriptions: " . $pdo->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn() . "\n";
echo "Orders: " . $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() . "\n";
echo "Total Payments: " . $pdo->query("SELECT SUM(total) FROM orders")->fetchColumn() . "\n";
