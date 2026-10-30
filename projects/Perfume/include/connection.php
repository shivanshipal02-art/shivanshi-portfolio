<?php
$conn = new mysqli("localhost", "root", "", "perfume");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
